"""Verify partial financial calculations and separate completed general integration."""
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.02-12'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'


def execute(args):
    p = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args],
        cwd=ROOT, capture_output=True, encoding='utf-8', check=False)
    v = json.loads(p.stdout)
    if 'error' in v:
        raise RuntimeError(v)
    return v


def save(path, value):
    target = ROOT/path
    payload = json.dumps(value, ensure_ascii=False, indent=2)+'\n'
    if target.exists() and target.read_text(encoding='utf-8') != payload:
        raise RuntimeError(f'Refusing to overwrite {target}')
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(payload, encoding='utf-8')


reasons = {'dispensed': 'RTC_FINANCIAL_CREDIT_AND_DOCUMENT_PENDING', 'not-dispensed': 'RTC_FINANCIAL_CREDIT_AND_DOCUMENT_PENDING',
    'factoring': 'RTC_FINANCIAL_CREDIT_AND_DOCUMENT_PENDING',
    'unknown-condition': 'RTC_FINANCIAL_TAX_2026_COLLECTION_CONDITION_UNKNOWN',
    'negative-base': 'RTC_FINANCIAL_TAX_NEGATIVE_PERIOD_BASE_RULE_REQUIRED',
    'mixed-cooperative': 'RTC_FINANCIAL_TAX_COOPERATIVE_MIXED_RATE_ALLOCATION_REQUIRED'}
for name, reason in reasons.items():
    filename = f'financial-gross-debits-{name}-2026.json'
    prefix = f'storage/app/phase4b-financial-gross-debits-{name}-20261002-12'
    snapshot = prefix+'-snapshot.json'
    args = ['fiscal:rtc:run', f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{filename}', f'--bundle-manifest={BUNDLE}']
    captured = name in ['dispensed', 'not-dispensed']
    if captured and not (ROOT/snapshot).exists():
        args.append(f'--snapshot={snapshot}')
    value = execute(args)
    assert value['status'] == 'ACTION_REQUIRED' and value['reason_codes'] == [reason], value
    assert value['decision'] == [], value
    stages = {row['stage']: row for row in value['trace']}
    tax = stages['FINANCIAL_GROSS_DEBITS']
    if name in ['dispensed', 'not-dispensed', 'factoring']:
        assert tax['status'] == 'RESOLVED' and tax['base'] == '800.00'
        assert tax['ibs_nominal'] == '0.80' and tax['cbs_nominal'] == '7.20'
        assert tax['collection_before_period_credit_offset'] == ({'IBS': '0.80', 'CBS': '7.20'} if name == 'not-dispensed' else {'IBS': '0.00', 'CBS': '0.00'})
        assert tax['payment_performed'] is False and tax['credit_granted'] is False and tax['document_approved'] is False
    if captured:
        save(prefix+'-result.json', value)
        assert execute(['fiscal:rtc:replay', snapshot])['replay_matches']
        print(name, value['decision_hash'])
    save(f'resources/fiscal/expected/consumption-phase4b-{VERSION}/{filename}',
        {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})
for name in ['bank-software', 'savings-bank-software', 'payment-institution-software']:
    filename = f'integrated-financial-tax-general-{name}-2026.json'
    prefix = f'storage/app/phase4b-financial-tax-general-{name}-20261002-12'
    snapshot = prefix+'-snapshot.json'
    args = ['fiscal:scenario:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}/{filename}', f'--bundle-manifest={BUNDLE}']
    if not (ROOT/snapshot).exists():
        args.append(f'--snapshot={snapshot}')
    value = execute(args)
    assert value['status'] == 'VALID_WITH_WARNINGS' and value['missing_facts'] == [], value
    rtc = value['decision']['consumption_reform']['decision']
    assert rtc['financial_operation']['qualification']['regime'] == 'GENERAL_ART184'
    assert rtc['ibs']['nominal_amount'] == '1.00' and rtc['cbs']['nominal_amount'] == '9.00'
    assert value['decision']['iss']['calculation']['tax_amount'] == '20.00'
    assert value['decision']['federal_withholdings']['total_withholding'] == '61.50'
    save(prefix+'-result.json', value)
    assert execute(['fiscal:decision:replay', snapshot])['replay_matches']
    report_path, candidate = prefix+'-xml-report.json', prefix+'-candidate.xml'
    report = json.loads((ROOT/report_path).read_text(encoding='utf-8')) if (ROOT/report_path).exists() else execute([
        'fiscal:dps:project-xml', snapshot, 'resources/fiscal/scenarios/dps-local-2026.10.01-2/federal-identity.json',
        f'--bundle-manifest={BUNDLE}', f'--candidate-output={candidate}', f'--report={report_path}'])
    assert report['reason'] == 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT' and len(report['schema_validation']['errors']) == 1
    assert report['production_ready'] is False and report['decision_hash'] == value['decision_hash']
    assert hashlib.sha256((ROOT/candidate).read_bytes()).hexdigest() == report['schema_validation']['xml_sha256']
    save(f'resources/fiscal/expected/consumption-integrated-{VERSION}/{filename}',
        {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})
    print(name, value['decision_hash'])
for family, command in [('consumption-phase4b', 'fiscal:rtc:corpus'), ('consumption-integrated', 'fiscal:corpus:run')]:
    corpus = execute([command, f'resources/fiscal/scenarios/{family}-{VERSION}',
        f'--expected=resources/fiscal/expected/{family}-{VERSION}', f'--bundle-manifest={BUNDLE}'])
    assert corpus['valid'], corpus
    save(f'storage/app/phase4b-financial-tax-{family}-corpus-20261002-12.json', corpus)
gate = execute(['fiscal:rtc:production-assess', f'--bundle-manifest={BUNDLE}', f'--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json'])
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == 20
save('storage/app/phase4b-production-assessment-20261002-12.json', gate)
print('Verified6 partial RTC/3 integrated general cases/5 replays/3 XML candidates; blockers:', len(gate['blockers']))
