"""Audit local settlement, original debits, stock conservation and temporal knowledge."""
from pathlib import Path
from decimal import Decimal, ROUND_HALF_EVEN
import hashlib
import json
import re
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base52-settlement-draft-20261009'
P = ROOT / 'storage/app/phase4b-negative-base51-current-bases-preparation-20261009'
OLD = ROOT / 'storage/app/phase4b-negative-base50-closing-draft-20261009'
F = ROOT / 'resources/fiscal'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}


quality = []
for label in ['settlement-final-tests', 'settlement-final-phpstan', 'settlement-final-pint',
              'settlement-native-capture-check', 'settlement-native-replay-check', 'bundle-reproduction', 'production-check']:
    path = E / (label + '.json')
    record = read(path)
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(E / (label + '.log'))
    quality.append(ref(path))
log = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'settlement-final-tests.log').read_text(encoding='utf-8'))
assert '36 passed' in log and '88 assertions' in log
prior = [P / 'current-base-development-validation.json', P / 'composition-development-validation.json',
         OLD / 'command-development-validation.json']
for path in prior:
    for item in read(path)['implementation']:
        assert sha(ROOT / item['path']) == item['sha256'], item['path']
bundle_path = F / 'bundles/consumption-phase4b-2026.10.09-28-draft52.json'
bundle = read(bundle_path)
catalog_path = F / bundle['resource_paths']['rtc_code_catalog']
catalog = read(catalog_path)
controls_path = F / bundle['production_controls']['path']
assert digest(catalog) == bundle['content_hashes']['rtc_code_catalog']
assert digest(read(controls_path)) == bundle['production_controls']['sha256']
for source in bundle['official_sources']:
    assert sha(F / 'sources/official' / source['file']) == source['sha256']
policy = catalog['dere_negative_base_local_settlement']
assert policy['source_hash'] == digest({k: v for k, v in policy.items() if k != 'source_hash'})
gate = read(E / 'production-assessment.json')
assert gate == read(E / 'production-check.log')
assert gate['ready'] is False and gate['official_source_hashes_valid'] == 51
capture = read(E / 'settlement-native-result.json')
inputs = capture['input']
result = capture['result']
assert capture['original_financial_replay_matches'] is True
assert capture['original_financial_snapshot_sha256'] == sha(OLD / 'command-financial-snapshot.json')
assert inputs['policy'] == policy and inputs['known'] == policy['published_at']
assert result['policy_hash'] == policy['source_hash'] and result['request_hash'] == digest(inputs['request'])
assert result['status'] == 'LOCAL_BCN_TAX_SETTLEMENT_PROPOSED'
assert result['original_gross_debits'] == inputs['gross']
assert result['original_gross_debits_hash'] == digest(inputs['gross'])
assert result['composition']['operation_hash'] == digest(inputs['operation'])
assert result['composition']['monthly_input_hash'] == digest(inputs['month'])
assert result['consumption']['parameters_hash'] == digest(inputs['request']['parameters'])
assert result['consumption']['current_bases_hash'] == digest({
    root: pool['positive_base'] for root, pool in result['composition']['calculation']['pools'].items()})
assert result['assessed_pools']['1010I']['assessed_base'] == '841.09'
assert result['assessed_pools']['1010C']['assessed_base'] == '881.09'
for root, pool in result['assessed_pools'].items():
    assert Decimal(pool['positive_base']) - Decimal(pool['deduction']) == Decimal(pool['assessed_base'])
    assert sum((Decimal(row['used']) for row in pool['allocations']), Decimal(0)) == Decimal(pool['deduction'])
for row in result['stock_after_proposed_consumption']:
    assert Decimal(row['available_after_return']) - Decimal(row['proposed_current_use']) == Decimal(row['available_after_proposed_consumption'])
assert {row['codBCN']: row['available_after_proposed_consumption'] for row in result['stock_after_proposed_consumption']} == {
    '1010I20260600': '0.00', '1010C20260600': '80.00', '1010C20260801': '0.00'}
for tax, component in result['proposed_tax_components'].items():
    independent = (Decimal(component['assessed_base']) * Decimal(component['percent']) / 100).quantize(
        Decimal('.00000001'), rounding=ROUND_HALF_EVEN).quantize(Decimal('.01'), rounding=ROUND_HALF_EVEN)
    assert component['nominal_amount'] == format(independent, 'f')
assert result['ibs_nominal_proposal'] == '0.84' and result['cbs_nominal_proposal'] == '7.93'
for field in ['received_return_verified', 'expiry_boundary_qualified', 'dependent_periods_reprocessed',
              'eligible_for_posting', 'production_ready', 'legacy_tax_effects_recalculated']:
    assert result[field] is False
assert capture['native_monthly_command_integration_complete'] is False
failure = read(E / 'settlement-initial-phpstan.json')
assert failure['exit_code'] != 0 and failure['log_sha256'] == sha(E / 'settlement-initial-phpstan.log')
files = ['app/Modules/Fiscal/ConsumptionReform/DereFinancialNegativeBaseSettlementPlanner.php',
         'tests/Fiscal/Unit/DereFinancialNegativeBaseSettlement52Test.php',
         'scripts/build_phase4b_negative_base52_bundle.py', 'scripts/capture_phase4b_negative_base52_check.py',
         'scripts/capture_phase4b_negative_base52_settlement.py', 'scripts/audit_phase4b_negative_base52_settlement.py',
         'docs/fiscal/phase4b-negative-base-settlement52-development.md']
report = {'status': 'LOCAL_RECONCILED_BCN_TAX_PROPOSAL_VALIDATED_NOT_NATIVE_MONTHLY_INTEGRATION',
          'bundle': bundle['version'], 'targeted_tests': 36, 'targeted_assertions': 88,
          'quality': quality, 'implementation': [ref(ROOT / name) for name in files],
          'knowledge': [ref(bundle_path), ref(catalog_path), ref(controls_path)],
          'capture': ref(E / 'settlement-native-result.json'),
          'production_assessment': ref(E / 'production-assessment.json'), 'official_sources_verified': 51,
          'prior_implementation_reports_verified': [ref(path) for path in prior],
          'preserved_phpstan_failure': ref(E / 'settlement-initial-phpstan.json'),
          'preserved_planner_source': ref(E / 'before-event-list-type-guard.php'),
          'current_base_prior_stock_consumption_tax_proposal_connected': True,
          'original_gross_debits_preserved': True, 'stock_and_base_conservation_verified': True,
          'native_monthly_command_integration_complete': False, 'own_bundle_financial_snapshot_captured': False,
          'full_suite_after52_run': False, 'scope51_complete': False, 'scope28_complete': False,
          'phase4b_complete': False, 'production_ready': False}
path = E / 'settlement-development-validation.json'
raw = json.dumps(report, ensure_ascii=False, indent=2) + '\n'
if path.exists():
    assert path.read_text(encoding='utf-8') == raw
else:
    with path.open('x', encoding='utf-8') as stream:
        stream.write(raw)
print('PASS: own52 local BCN tax proposal; 36/88; native replay, independent tax arithmetic and stock conservation; production blocked')
