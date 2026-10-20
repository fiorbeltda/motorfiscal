"""Compare monthly amounts with independent arithmetic before preserving goldens."""
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "2026.10.02-7"
BUNDLE = f"bundles/consumption-phase4b-{VERSION}.json"


def execute(args):
    process = subprocess.run(["docker", "compose", "exec", "-T", "app", "php", "artisan", *args],
                             cwd=ROOT, capture_output=True, encoding="utf-8", check=False)
    value = json.loads(process.stdout)
    if "error" in value:
        raise RuntimeError(value)
    return value


def save(path, value):
    target = ROOT / path
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if target.exists() and target.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite {target}")
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(payload, encoding="utf-8")


positive = {'mixed': ('65.00', '-90.00'), 'balanced': ('0.00', '0.00'), 'adjusted': ('70.00', '-100.00'),
            'presumed-qualified': ('65.00', '-90.00'), 'after-close-extinction': ('65.00', '-90.00'),
            'family-4a': ('65.00', '-90.00')}
negative = {
    'incomplete': ('ACTION_REQUIRED', 'LEDGER_INCOMPLETE:all_establishments_included'),
    'future-period': ('ACTION_REQUIRED', 'PERIOD_NOT_CLOSED'),
    'wrong-owner': ('INVALID', 'OWNER_OR_CURRENCY_CONFLICT'),
    'duplicate': ('INVALID', 'ENTRY_INVALID:credits'),
    'excess-extinction': ('INVALID', 'EXTINCTION_EXCEEDS_DEBIT'),
    'wrong-extinction-tax': ('INVALID', 'EXTINCTION_LINK_CONFLICT'),
    'compensation-double-count': ('INVALID', 'EXTINCTION_NOT_ART27_III_V'),
    'unqualified-credit': ('ACTION_REQUIRED', 'ENTRY_NOT_QUALIFIED:credits'),
    'carryforward-overdrawn': ('INVALID', 'CARRYFORWARD_DISPOSITIONS_EXCEED_BALANCE'),
    'nominal-not-due': ('INVALID', 'DEBIT_DUE_STATUS_CONFLICT'),
    'future-evidence': ('INVALID', 'ENTRY_DATE_CONFLICT:credits'),
    'missing-prior-debts': ('ACTION_REQUIRED', 'LEDGER_INCOMPLETE:prior_debts_complete'),
    'unqualified-adjustment': ('ACTION_REQUIRED', 'ADJUSTMENT_RULE_NOT_QUALIFIED'),
    'unqualified-presumed': ('ACTION_REQUIRED', 'PRESUMED_CREDIT_RULE_NOT_QUALIFIED'),
}
snapshots = ['mixed', 'adjusted', 'family-4a']
for name in list(positive) + list(negative):
    full_name = f'integrated-monthly-assessment-{name}-2026'
    prefix = f'storage/app/phase4b-monthly-assessment-{name}-20261002-7'
    snapshot = prefix + '-snapshot.json'
    args = ['fiscal:scenario:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}/{full_name}.json', f'--bundle-manifest={BUNDLE}']
    if name in snapshots and not (ROOT / snapshot).exists():
        args.append(f'--snapshot={snapshot}')
    value = execute(args)
    if name in positive:
        assert value['status'] == 'VALID_WITH_WARNINGS' and value['missing_facts'] == [], value
        period = value['decision']['consumption_reform']['decision']['monthly_assessment']
        assert tuple(period['taxes'][tax]['final_balance'] for tax in ['IBS', 'CBS']) == positive[name], value
        assert period['taxes']['IBS']['credit_allocated_to_prior_debts'] == '10.00'
        assert period['taxes']['IBS']['carryforward'] == '20.00'
        assert period['payment_due_date'] is None and period['filing_due_date'] is None
        assert period['filing_performed'] is False and period['debt_confession'] is False and period['payment_performed'] is False
        if name == 'family-4a':
            assert value['decision']['iss']['incidence']['value'] == 'IMMUNE'
            assert value['decision']['federal_withholdings']['status'] == 'RESOLVED'
        else:
            assert value['decision']['iss']['calculation']['tax_amount'] == '50.00'
            assert value['decision']['iss']['municipal_parameters']['source_type'] == 'FIXTURE'
        if name in snapshots:
            save(prefix + '-result.json', value)
            assert execute(['fiscal:decision:replay', snapshot])['replay_matches']
            print(name, value['decision_hash'])
            report_path, candidate = prefix + '-xml-report.json', prefix + '-candidate.xml'
            identity = 'resources/fiscal/scenarios/dps-local-2026.10.02-6/family-identity.json' if name == 'family-4a' else 'resources/fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json'
            if (ROOT / report_path).exists():
                report = json.loads((ROOT / report_path).read_text(encoding='utf-8'))
            else:
                report = execute(['fiscal:dps:project-xml', snapshot, identity, f'--bundle-manifest={BUNDLE}', f'--candidate-output={candidate}', f'--report={report_path}'])
            assert report['reason'] == 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT', report
            assert len(report['schema_validation']['errors']) == 1 and report['production_ready'] is False
            assert report['decision_hash'] == value['decision_hash']
            assert hashlib.sha256((ROOT / candidate).read_bytes()).hexdigest() == report['schema_validation']['xml_sha256']
    else:
        status, suffix = negative[name]
        assert value['status'] == status and value['reason_codes'] == ['RTC_MONTHLY_ASSESSMENT_' + suffix], (name, value)
    save(f'resources/fiscal/expected/consumption-integrated-{VERSION}/{full_name}.json',
         {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})

corpus = execute(['fiscal:corpus:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}',
    f'--expected=resources/fiscal/expected/consumption-integrated-{VERSION}', f'--bundle-manifest={BUNDLE}'])
assert corpus['valid'], corpus
save('storage/app/phase4b-monthly-assessment-corpus-20261002-7.json', corpus)
gate = execute(['fiscal:rtc:production-assess', f'--bundle-manifest={BUNDLE}',
    f'--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json'])
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == 20
save('storage/app/phase4b-production-assessment-20261002-7.json', gate)
print('Verified 20 integrated cases / 3 snapshots / 3 XML candidates; blockers:', len(gate['blockers']))
