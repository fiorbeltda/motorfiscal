"""Freeze original scope inventory and reproduced export gap, without claiming integration."""
from pathlib import Path
import hashlib, json

ROOT = Path(__file__).resolve().parents[1]
I = ROOT / 'storage/app/phase4b-original-requirements72-audit-v3-20261014'
P = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
def read(path): return json.loads(path.read_text(encoding='utf-8'))
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()

inventory = read(I / 'original-requirements-inventory.json')
assert len(inventory['requirements']) == 80
assert sha(Path(inventory['original_plan_path'])) == inventory['original_plan_sha256']
assert {r['group'] for r in inventory['requirements']} == {'4B.' + str(i) for i in range(1, 9)}
engine = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php'
assert sha(engine) == inventory['engine_sha256']
assert sha(ROOT / inventory['bundle_path']) == inventory['bundle_sha256']
gap = read(I / 'partial-export-gap-native-result.json')['cases']
assert set(gap) == {'BRL', 'USD'}
assert 'RTC_PARTIAL_EXPORT_CURRENCY_CONVERSION_NOT_MATERIALIZED' in gap['USD']['result']['reason_codes']
assert 'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN' in gap['BRL']['result']['reason_codes']
assert inventory['confirmed_partial_export_fx_gap']['active_partial_export_policy'] is True
preparation = read(P / 'partial-export-fx-preparation.json')
converter = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ImportFxQuoteConverter.php'
assert sha(converter) == preparation['current_converter_sha256']
assert sha(P / 'planned-partial-export-quote.php') == preparation['prototype_sha256']
for source in preparation['sources']: assert sha(ROOT / source['path']) == source['sha256']
native = read(P / 'partial-export-quote-native-result.json')
assert len(native['cases']) == 23 and native['app_installed'] is False
paths = [I / 'original-requirements-inventory.json', I / 'original-phase4b-requirements.txt',
         I / 'partial-export-gap-native-result.json', I / 'exercise-partial-export-gap.php',
         P / 'planned-partial-export-quote.php', P / 'partial-export-fx-preparation.json',
         P / 'partial-export-quote-native-result.json', P / 'exercise-partial-export-quote.php']
for label in ['scope72-export-gap-capture', 'scope72-export-gap-replay', 'scope73-numeric-capture', 'scope73-numeric-replay', 'scope73-numeric-type']:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(Q / (label + '.log'))
    paths.extend([Q / (label + '.json'), Q / (label + '.log')])
report = {'status': 'ORIGINAL_SCOPE_PRESERVED_PARTIAL_EXPORT_FX_GAP_REPRODUCED_NUMERIC_PROTOTYPE_VERIFIED',
          'original_requirement_items': 80, 'native_gap_control_cases': 2, 'prototype_numeric_cases': 23,
          'current_runtime_unchanged': True, 'prototype_integration_complete': False,
          'material_origin_qualified_by_prototype': False, 'scope73_complete': False, 'phase4b_complete': False,
          'production_ready': False,
          'evidence': [{'path': p.relative_to(ROOT).as_posix(), 'sha256': sha(p)} for p in paths]}
with (I / 'requirements-export-preparation-validation.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, indent=2)
print('PASS:80 original requirements, reproduced active export FX gap and 23 numeric-only cases; runtime unchanged')
