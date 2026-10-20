"""Audit local consumption evidence without turning arithmetic into monthly acceptance."""
from pathlib import Path
from hashlib import sha256
import json
import re

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base49-preparation-20261009'
OLD = ROOT / 'storage/app/phase4b-private-consolidation48-draft-20261009-28'


def digest(path):
    return sha256(path.read_bytes()).hexdigest()


def ref(path):
    return {'path': str(path.relative_to(ROOT)).replace('\\', '/'), 'sha256': digest(path)}


previous = [OLD / 'regression-positive-validation-report.json', E / 'parameters-development-validation.json',
            E / 'return-development-validation.json']
for path in previous:
    record = json.loads(path.read_text(encoding='utf-8'))
    for item in record.get('implementation_hashes', record.get('implementation', [])):
        assert digest(ROOT / item['path']) == item['sha256'], item['path']
research = json.loads((E / 'research-contract.json').read_text(encoding='utf-8'))
for item in research['sources']:
    assert digest(ROOT / item['path']) == item['sha256'], item['path']
observed = json.loads((E / 'ibs-negative-base-source-observation.json').read_text(encoding='utf-8'))
assert digest(ROOT / observed['path']) == observed['sha256']
assert digest(E / 'res-cgibs-6-financial-negative-base-page97.txt') == observed['excerpt_sha256']
quality = {}
for label in ['consumption-final-pint', 'consumption-final-tests', 'consumption-final-phpstan',
              'consumption-native-capture', 'consumption-native-replay']:
    path = E / (label + '.json')
    record = json.loads(path.read_text(encoding='utf-8'))
    assert record['exit_code'] == 0
    assert digest(E / (label + '.log')) == record['log_sha256']
    quality[label] = ref(path)
log = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'consumption-final-tests.log').read_text(encoding='utf-8'))
assert '65 passed' in log and '222 assertions' in log
inputs = json.loads((E / 'synthetic-consumption-input.json').read_text(encoding='utf-8'))
result = json.loads((E / 'synthetic-consumption-result.json').read_text(encoding='utf-8'))
assert inputs['synthetic'] is True and inputs['prior_xml_sha256'] == digest(E / 'synthetic-return.xml')
prior = json.loads((E / 'synthetic-return-reader.json').read_text(encoding='utf-8'))
assert inputs['prior'] == prior['result']['normalized_return']
assert result['status'] == 'LOCAL_CONSUMPTION_PLANNED'
assert result['plans'][0]['deduction'] == '40.00' and result['plans'][0]['assessed_base'] == '10.00'
assert result['plans'][1]['deduction'] == '20.00' and result['plans'][1]['allocations'][0]['remaining'] == '0.00'
assert result['warnings'] == [{'code': 'AVISO_CONFERIR_SALDO_BCN', 'codBCN': '1010C20260901',
                              'requested': '90.00', 'available': '20.00'}]
assert result['received_return_verified'] is False and result['eligible_for_posting'] is False
assert result['production_ready'] is False
files = ['app/Modules/Fiscal/ConsumptionReform/DereNegativeBaseConsumptionPlanner.php',
         'tests/Fiscal/Unit/DereNegativeBaseConsumption49Test.php',
         'scripts/capture_phase4b_negative_base49_consumption.py',
         'scripts/audit_phase4b_negative_base49_consumption.py',
         'docs/fiscal/phase4b-negative-base-consumption49-development.md']
report = {'status': 'LOCAL_ARITHMETIC_CHAIN_VALIDATED_NOT_MONTHLY_CLOSE_ACCEPTANCE',
          'targeted_tests': 65, 'targeted_assertions': 222, 'quality': quality,
          'implementation': [ref(ROOT / path) for path in files],
          'previous_implementation_reports_verified': [ref(path) for path in previous],
          'sources': [ref(E / 'research-contract.json'), ref(E / 'ibs-negative-base-source-observation.json')],
          'captures': [ref(E / 'synthetic-consumption-input.json'), ref(E / 'synthetic-consumption-result.json')],
          'historical_artifact_corpus_reaudited_this_step': False,
          'full_suite_after49_run': False, 'monthly_integration_complete': False,
          'return_reception_verified': False, 'expiry_boundary_qualified': False,
          'reopening_reconciliation_complete': False, 'scope28_complete': False,
          'phase4b_complete': False, 'production_ready': False}
path = E / 'consumption-development-validation.json'
raw = json.dumps(report, ensure_ascii=False, indent=2) + '\n'
if path.exists():
    assert path.read_text(encoding='utf-8') == raw
else:
    with path.open('x', encoding='utf-8') as stream:
        stream.write(raw)
print('PASS:65 directed tests/222 assertions, native reader-to-consumption replay and hashes; monthly acceptance pending')
