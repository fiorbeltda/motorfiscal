"""Verify general article184 integration without claiming specific financial completion."""
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.02-11'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'


def execute(args):
    process = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args],
                             cwd=ROOT, capture_output=True, encoding='utf-8', check=False)
    value = json.loads(process.stdout)
    if 'error' in value:
        raise RuntimeError(value)
    return value


def save(path, value):
    target = ROOT/path
    payload = json.dumps(value, ensure_ascii=False, indent=2)+'\n'
    if target.exists() and target.read_text(encoding='utf-8') != payload:
        raise RuntimeError(f'Refusing to overwrite {target}')
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(payload, encoding='utf-8')


positive = ['bank-software', 'savings-bank-software', 'payment-institution-software']
negative = {
    'missing-proof': ('ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_PROOF_MISSING'),
    'wrong-supplier': ('INVALID', 'RTC_FINANCIAL_SCOPE_PARTIES_OR_DOCUMENT_CONFLICT'),
    'wrong-document': ('INVALID', 'RTC_FINANCIAL_SCOPE_PARTIES_OR_DOCUMENT_CONFLICT'),
    'unknown-registry': ('ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_SUPERVISION_UNPROVEN'),
    'future-observation': ('INVALID', 'RTC_FINANCIAL_SCOPE_PROOF_PERIOD_CONFLICT'),
    'expired-proof': ('ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_PROOF_OUTSIDE_PERIOD'),
    'unknown-nonfinancial-scope': ('ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_NON_FINANCIAL_SCOPE_UNPROVEN'),
    'unknown-role': ('ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_SUPERVISION_UNPROVEN'),
    'wrong-general-kind': ('INVALID', 'RTC_FINANCIAL_GENERAL_OPERATION_KIND_CONFLICT'),
    'specific-ledger-missing': ('ACTION_REQUIRED', 'RTC_FINANCIAL_QUALIFIED_LEDGER_MISSING'),
}
for name in positive+list(negative):
    full_name = f'integrated-financial-scope-{name}-2026'
    prefix = f'storage/app/phase4b-financial-scope-{name}-20261002-11'
    snapshot = prefix+'-snapshot.json'
    args = ['fiscal:scenario:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}/{full_name}.json', f'--bundle-manifest={BUNDLE}']
    if name in positive and not (ROOT/snapshot).exists():
        args.append(f'--snapshot={snapshot}')
    value = execute(args)
    if name in positive:
        assert value['status'] == 'VALID_WITH_WARNINGS' and value['missing_facts'] == [], value
        rtc = value['decision']['consumption_reform']['decision']
        assert rtc['financial_operation']['qualification']['regime'] == 'GENERAL_ART184'
        assert rtc['financial_operation']['base'] is None
        assert rtc['base']['taxable'] == '1000.00'
        assert rtc['ibs']['nominal_amount'] == '1.00' and rtc['cbs']['nominal_amount'] == '9.00'
        assert value['decision']['iss']['calculation']['tax_amount'] == '20.00'
        assert value['decision']['federal_withholdings']['total_withholding'] == '61.50'
        save(prefix+'-result.json', value)
        assert execute(['fiscal:decision:replay', snapshot])['replay_matches']
        report_path, candidate = prefix+'-xml-report.json', prefix+'-candidate.xml'
        if (ROOT/report_path).exists():
            report = json.loads((ROOT/report_path).read_text(encoding='utf-8'))
        else:
            report = execute(['fiscal:dps:project-xml', snapshot,
                'resources/fiscal/scenarios/dps-local-2026.10.01-2/federal-identity.json',
                f'--bundle-manifest={BUNDLE}', f'--candidate-output={candidate}', f'--report={report_path}'])
        assert report['reason'] == 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT', report
        assert len(report['schema_validation']['errors']) == 1 and report['production_ready'] is False
        assert report['decision_hash'] == value['decision_hash']
        assert hashlib.sha256((ROOT/candidate).read_bytes()).hexdigest() == report['schema_validation']['xml_sha256']
        print(name, value['decision_hash'])
    else:
        status, reason = negative[name]
        assert value['status'] == status and value['reason_codes'] == [reason], (name, value)
    save(f'resources/fiscal/expected/consumption-integrated-{VERSION}/{full_name}.json',
        {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})
corpus = execute(['fiscal:corpus:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}',
    f'--expected=resources/fiscal/expected/consumption-integrated-{VERSION}', f'--bundle-manifest={BUNDLE}'])
assert corpus['valid'], corpus
save('storage/app/phase4b-financial-scope-corpus-20261002-11.json', corpus)
gate = execute(['fiscal:rtc:production-assess', f'--bundle-manifest={BUNDLE}', f'--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json'])
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == 20
save('storage/app/phase4b-production-assessment-20261002-11.json', gate)
print('Verified13 integrated scope cases/3 snapshots/3 XML candidates; blockers:', len(gate['blockers']))
