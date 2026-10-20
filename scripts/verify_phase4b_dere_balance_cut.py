"""Persist and verify replayed local D1101 evidence; no remote actions."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.02-15'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'
PREFIX = 'storage/app/phase4b-dere-balancete-20261002-15'


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
projection_args = ['fiscal:dere:project-balancete', snapshot,
                   f'resources/fiscal/scenarios/dere-local-{VERSION}/qualified-trial-balance.json', f'--bundle-manifest={BUNDLE}']
if not (ROOT / report_path).exists():
    projection_args += [f'--candidate-output={candidate}', f'--report={report_path}']
report = execute(projection_args)
stored = json.loads((ROOT / report_path).read_text(encoding='utf-8'))
for key in ['decision_hash', 'snapshot_hash', 'book_and_identity_hash', 'projection', 'schema_validation']:
    assert stored[key] == report[key], key
assert report['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and report['production_ready'] is False
assert report['schema_validation']['unsigned_candidate_structure_checked']
assert len(report['schema_validation']['errors']) == 1
data = (ROOT / candidate).read_bytes()
assert hashlib.sha256(data).hexdigest() == report['schema_validation']['xml_sha256']
root = ET.fromstring(data)
ns = {'d': 'http://www.dere.gov.br/schemas/evtBalancete/v1_0_1'}
assert root.find('d:evtBalancete', ns).get('id') == 'DeRE11011111111110001912026100112000000001'
assert root.find('.//d:perApur', ns).text == '2026-09'
assert root.find('.//d:tpOper', ns).text == '1' and root.find('.//d:tpAmb', ns).text == '2'
assert root.find('.//d:nrRecibo', ns) is None
rows = root.findall('.//d:infoConta', ns)
assert len(rows) == 3
expected = [('interest', 'C', '1000.00'), ('principal', 'D', '10000.00'), ('funding', 'D', '200.00')]
for row, (code, nature, amount) in zip(rows, expected):
    values = {element.tag.split('}')[-1]: element.text for element in row}
    assert values['cCta'] == code and values['natSaldoFinal'] == nature
    assert values['vSaldoInic'] == '0.00' and values['vSaldoFinal'] == amount and values['vApur'] == amount
    assert values['vMovDebt'] == (amount if nature == 'D' else '0.00')
    assert values['vMovCred'] == (amount if nature == 'C' else '0.00')
assert report['projection']['balance']['linked_financial_entries'] == 3
print('D1101 replay and original XSD body verified:', report['schema_validation']['xml_sha256'])
