"""Verify replayed local monthly candidates; preserve all event XMLs without remote actions."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.02-17'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'
PREFIX = 'storage/app/phase4b-dere-monthly-closing-20261002-17'


def execute(args):
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args],
                            cwd=ROOT, capture_output=True, encoding='utf-8', check=False)
    if result.returncode != 0:
        raise RuntimeError(result.stdout + result.stderr)
    return json.loads(result.stdout)


snapshot = PREFIX + '-snapshot.json'
args = ['fiscal:rtc:run', f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/financial-borrower-credit-loan-2026.json',
        f'--bundle-manifest={BUNDLE}']
if not (ROOT / snapshot).exists():
    args.append(f'--snapshot={snapshot}')
result = execute(args)
assert result['status'] == 'ACTION_REQUIRED' and result['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
assert execute(['fiscal:rtc:replay', snapshot])['replay_matches']
candidate = PREFIX + '-candidate.xml'
report_path = PREFIX + '-report.json'
args = ['fiscal:dere:project-monthly-closing', snapshot,
        f'resources/fiscal/scenarios/dere-local-{VERSION}/qualified-monthly-closing.json', f'--bundle-manifest={BUNDLE}']
if not (ROOT / report_path).exists():
    args += [f'--candidate-output={candidate}', f'--report={report_path}']
report = execute(args)
stored = json.loads((ROOT / report_path).read_text(encoding='utf-8'))
for key in ['decision_hash', 'snapshot_hash', 'monthly_input_hash', 'projection', 'event_validations']:
    assert stored[key] == report[key], key
assert report['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_MONTHLY_CANDIDATES' and report['production_ready'] is False
assert report['projection']['period_closed'] is False and report['projection']['transmission_performed'] is False
assert set(stored['event_outputs']) == {'D1101', 'D1121', 'D1199'}
for event, filename in stored['event_outputs'].items():
    validation = report['event_validations'][event]
    assert validation['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and validation['unsigned_candidate_structure_checked']
    assert len(validation['errors']) == 1 and validation['production_ready'] is False
    data = (ROOT / filename).read_bytes()
    assert hashlib.sha256(data).hexdigest() == validation['xml_sha256']
    root = ET.fromstring(data)
    namespace = root.tag.split('}')[0][1:]
    ns = {'d': namespace}
    assert root.find('.//d:perApur', ns).text == '2026-09'
    assert root.find('.//d:nrInsc', ns).text == '11111111'
    assert root.find('.//d:tpOper', ns).text == '1' and root.find('.//d:tpAmb', ns).text == '2'
    if event == 'D1101':
        accounts = root.findall('.//d:infoConta', ns)
        assert len(accounts) == 3
        assert [r.find('d:vApur', ns).text for r in accounts] == ['1000.00', '10000.00', '200.00']
    elif event == 'D1121':
        assert root.find('.//d:vDed', ns).text == '200.00' and root.find('.//d:vDedTotal', ns).text == '800.00'
    else:
        assert root.find('.//d:indInexistDedu', ns) is None and root.find('.//d:gUtilizBCN', ns) is None
    print(event, validation['xml_sha256'])

for family in ['factoring', 'securitization']:
    filename = f'financial-dere-{family}-2026.json'
    value = execute(['fiscal:rtc:run', f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{filename}', f'--bundle-manifest={BUNDLE}'])
    assert value['status'] == 'ACTION_REQUIRED' and value['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
    tax = {row['stage']: row for row in value['trace']}['FINANCIAL_GROSS_DEBITS']
    assert tax['base'] == '800.00' and tax['ibs_nominal'] == '0.80' and tax['cbs_nominal'] == '7.20'
    expected = {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']}
    target = ROOT / f'resources/fiscal/expected/consumption-phase4b-{VERSION}/{filename}'
    target.parent.mkdir(parents=True, exist_ok=True)
    if target.exists():
        assert json.loads(target.read_text(encoding='utf-8')) == expected
    else:
        target.write_text(json.dumps(expected, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
process = subprocess.run(['python', 'scripts/verify_phase4b_financial_credit_cut.py', VERSION], cwd=ROOT, check=False)
assert process.returncode == 0
