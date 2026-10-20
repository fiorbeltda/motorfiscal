"""Persist original local D1121 candidates and independently check documentary fields."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.02-16'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'
PREFIX = 'storage/app/phase4b-dere-deductions-20261002-16'


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
for kind in ['initial', 'continued']:
    candidate = PREFIX + f'-{kind}-candidate.xml'
    report_path = PREFIX + f'-{kind}-report.json'
    projection_args = ['fiscal:dere:project-deductions', snapshot,
                       f'resources/fiscal/scenarios/dere-local-{VERSION}/qualified-deductions-{kind}.json', f'--bundle-manifest={BUNDLE}']
    if not (ROOT / report_path).exists():
        projection_args += [f'--candidate-output={candidate}', f'--report={report_path}']
    report = execute(projection_args)
    stored = json.loads((ROOT / report_path).read_text(encoding='utf-8'))
    for key in ['decision_hash', 'snapshot_hash', 'deductions_and_identity_hash', 'projection', 'schema_validation']:
        assert stored[key] == report[key], key
    assert report['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and report['production_ready'] is False
    assert report['schema_validation']['unsigned_candidate_structure_checked']
    assert len(report['schema_validation']['errors']) == 1
    data = (ROOT / candidate).read_bytes()
    assert hashlib.sha256(data).hexdigest() == report['schema_validation']['xml_sha256']
    root = ET.fromstring(data)
    ns = {'d': 'http://www.dere.gov.br/schemas/evtRelDeducoes/v0_0_1'}
    assert root.find('d:evtRelDeducoes', ns).get('id') == 'DeRE11211111111110001912026100112000000001'
    assert root.find('.//d:perApur', ns).text == '2026-09'
    assert root.find('.//d:tpOper', ns).text == '1' and root.find('.//d:tpAmb', ns).text == '2'
    assert root.find('.//d:nrRecibo', ns) is None and root.find('.//d:finEvt', ns) is None
    rows = root.findall('.//d:infoDeducao', ns)
    assert len(rows) == 1
    row = rows[0]
    assert row.find('d:infoDFe/d:chDFe', ns).text == '1' * 44
    assert row.find('d:infoDFe/d:tpAtiv', ns).text == '01'
    assert row.find('d:infoDFe/d:dtEmi', ns).text == ('2026-09-20' if kind == 'initial' else '2026-08-20')
    assert row.find('d:detDeducao/d:vOper', ns).text == '1000.00'
    assert row.find('d:detDeducao/d:vDed', ns).text == '200.00'
    assert row.find('d:itemDFe/d:nItem', ns).text == '1'
    assert row.find('d:itemDFe/d:vItemDed', ns).text == '200.00'
    total = row.find('d:detDeducao/d:vDedTotal', ns)
    item_total = row.find('d:itemDFe/d:vItemDedTotal', ns)
    if kind == 'initial':
        assert total.text == '800.00' and item_total.text == '800.00'
    else:
        assert total is None and item_total is None
    assert report['projection']['deductions']['linked_financial_deductions'] == 1
    print(kind, 'D1121 replay and original XSD body verified:', report['schema_validation']['xml_sha256'])

# Preserve the other two specific financial cases before running the complete inherited corpus.
for family in ['factoring', 'securitization']:
    filename = f'financial-dere-{family}-2026.json'
    value = execute(['fiscal:rtc:run', f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{filename}', f'--bundle-manifest={BUNDLE}'])
    assert value['status'] == 'ACTION_REQUIRED' and value['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
    stages = {row['stage']: row for row in value['trace']}
    tax = stages['FINANCIAL_GROSS_DEBITS']
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
