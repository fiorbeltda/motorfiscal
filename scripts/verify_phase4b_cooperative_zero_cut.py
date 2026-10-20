"""Verify qualification and zero mechanism before recording golden decisions."""
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.02-9'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'


def execute(args):
    process = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args],
                             cwd=ROOT, capture_output=True, encoding='utf-8', check=False)
    value = json.loads(process.stdout)
    if 'error' in value:
        raise RuntimeError(value)
    return value


def save(path, value):
    target = ROOT / path
    payload = json.dumps(value, ensure_ascii=False, indent=2) + '\n'
    if target.exists() and target.read_text(encoding='utf-8') != payload:
        raise RuntimeError(f'Refusing to overwrite {target}')
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(payload, encoding='utf-8')


negative = {
    'missing-proof': ('ACTION_REQUIRED', 'PROOF_MISSING'),
    'wrong-document': ('INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT'),
    'wrong-parties': ('INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT'),
    'option-expired': ('ACTION_REQUIRED', 'PROOF_OUTSIDE_PERIOD'),
    'option-not-effective': ('INVALID', 'CONDITION_NOT_MET:option_registered_and_effective'),
    'unknown-membership': ('ACTION_REQUIRED', 'CONDITION_UNPROVEN:membership_or_participation_valid'),
    'nonregular-member': ('INVALID', 'MEMBER_REGULAR_REGIME_REQUIRED'),
    'wrong-route-parties': ('INVALID', 'ROUTE_PARTIES_CONFLICT'),
    'current-year-option': ('INVALID', 'PRIOR_YEAR_OPTION_CONFLICT'),
    'missing-regulatory-evidence': ('ACTION_REQUIRED', 'EVIDENCE_MISSING:option_regulatory_validation_evidence_id'),
    'financial-service': ('ACTION_REQUIRED', 'OTHER_OPERATION_RULE_REQUIRED'),
    'future-observation': ('INVALID', 'OPTION_PERIOD_CONFLICT'),
}
positive = ['cooperative-to-member', 'member-to-cooperative', 'startup-option', 'individual-member-with-4a']
snapshots = ['cooperative-to-member', 'member-to-cooperative', 'individual-member-with-4a']
for name in positive + list(negative):
    full_name = f'integrated-cooperative-zero-{name}-2026'
    prefix = f'storage/app/phase4b-cooperative-zero-{name}-20261002-9'
    snapshot = prefix + '-snapshot.json'
    args = ['fiscal:scenario:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}/{full_name}.json', f'--bundle-manifest={BUNDLE}']
    if name in snapshots and not (ROOT / snapshot).exists():
        args.append(f'--snapshot={snapshot}')
    value = execute(args)
    if name in positive:
        assert value['status'] == 'VALID_WITH_WARNINGS' and value['missing_facts'] == [], value
        rtc = value['decision']['consumption_reform']
        assert rtc['reason_codes'] == ['RTC_COOPERATIVE_ZERO_RATE']
        decision = rtc['decision']
        assert decision['base']['taxable'] == '1000.00'
        assert decision['classification']['cclasstrib'] == '200020'
        assert decision['ibs']['status'] == decision['cbs']['status'] == 'ZERO_RATE'
        assert decision['ibs']['nominal_amount'] == decision['cbs']['nominal_amount'] == '0.00'
        assert decision['treatment']['incidence'] == 'TAXABLE'
        assert decision['treatment']['acquirer_ordinary_credit_treatment'] == 'DENIED_ART49'
        assert decision['treatment']['supplier_previous_credit_treatment'] == 'PRESERVED_ART52_SUBJECT_TO_PROOF'
        assert value['decision']['iss']['calculation']['tax_amount'] == '50.00'
        if name == 'individual-member-with-4a':
            assert value['decision']['federal_withholdings']['status'] == 'RESOLVED'
            assert value['decision']['federal_withholdings']['total_withholding'] == '0.00'
        else:
            assert value['decision']['federal_withholdings']['status'] == 'NOT_REQUESTED'
        if name in snapshots:
            save(prefix + '-result.json', value)
            assert execute(['fiscal:decision:replay', snapshot])['replay_matches']
            identity = 'resources/fiscal/scenarios/dps-local-2026.10.02-6/family-identity.json' if name == 'individual-member-with-4a' else 'resources/fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json'
            report_path, candidate = prefix + '-xml-report.json', prefix + '-candidate.xml'
            if (ROOT / report_path).exists():
                report = json.loads((ROOT / report_path).read_text(encoding='utf-8'))
            else:
                report = execute(['fiscal:dps:project-xml', snapshot, identity, f'--bundle-manifest={BUNDLE}', f'--candidate-output={candidate}', f'--report={report_path}'])
            assert report['reason'] == 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT', report
            assert len(report['schema_validation']['errors']) == 1 and report['production_ready'] is False
            assert report['decision_hash'] == value['decision_hash']
            assert hashlib.sha256((ROOT / candidate).read_bytes()).hexdigest() == report['schema_validation']['xml_sha256']
            print(name, value['decision_hash'])
    else:
        status, reason = negative[name]
        assert value['status'] == status and value['reason_codes'] == ['RTC_COOPERATIVE_ZERO_' + reason], (name, value)
    save(f'resources/fiscal/expected/consumption-integrated-{VERSION}/{full_name}.json',
         {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})
corpus = execute(['fiscal:corpus:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}',
    f'--expected=resources/fiscal/expected/consumption-integrated-{VERSION}', f'--bundle-manifest={BUNDLE}'])
assert corpus['valid'], corpus
save('storage/app/phase4b-cooperative-zero-corpus-20261002-9.json', corpus)
gate = execute(['fiscal:rtc:production-assess', f'--bundle-manifest={BUNDLE}', f'--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json'])
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == 20
save('storage/app/phase4b-production-assessment-20261002-9.json', gate)
print('Verified16 integrated cases/3 snapshots/3 XML candidates; blockers:', len(gate['blockers']))
