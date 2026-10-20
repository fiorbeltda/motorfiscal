"""Freeze reviewed, typed, replayed export prototype; separate from live development acceptance."""
from pathlib import Path
from decimal import Decimal, ROUND_HALF_UP
import hashlib, json
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
def read(path): return json.loads(path.read_text(encoding='utf-8'))
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
def money(value): return str(value.quantize(Decimal('0.01'), rounding=ROUND_HALF_UP))

paths = []
for label in ['scope73-prototypes-final-style-v2', 'scope73-prototypes-final-type', 'scope73-quote-v2-replay', 'scope73-transaction-v2-replay', 'scope73-engine-v2-replay']:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(Q / (label + '.log'))
    paths.extend([Q / (label + '.json'), Q / (label + '.log')])
assert len(read(E / 'partial-export-quote-native-result.json')['cases']) == 23
transactions = read(E / 'partial-export-transaction-native-result.json')['cases']
assert len(transactions) == 21
assert sum(c['result']['status'] == 'LOCAL_PARTIAL_EXPORT_FX_TRANSACTION_BOUND' for c in transactions.values()) == 1
integrated = read(E / 'partial-export-engine-native-result.json')['cases']
assert len(integrated) == 7
nominal = next(row for row in integrated['valid']['result']['trace'] if row['stage'] == 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION')
gross = Decimal(money(Decimal('1000.00') * Decimal('5.1795')))
foreign = Decimal(money(gross * Decimal('40') / Decimal('100')))
domestic = gross - foreign
assert nominal['foreign_immune_gross_brl'] == money(foreign)
assert nominal['domestic_taxable_base_brl'] == money(domestic)
assert nominal['ibs_state_nominal_brl'] == money(domestic * Decimal('0.001'))
assert nominal['ibs_municipal_nominal_brl'] == '0.00'
assert nominal['cbs_nominal_brl'] == money(domestic * Decimal('0.009'))
assert 'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN' in integrated['valid']['result']['reason_codes']
ibs = read(E / 'ibs-conversion-partial-export-source.json')
assert sha(ROOT / ibs['source_path']) == ibs['source_sha256']
assert sha(E / 'ibs-conversion-and-partial-export-pages.txt') == ibs['excerpt_sha256']
engine = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php'
assert sha(engine) == sha(E / 'before-partial-export-engine.php')
transaction = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php'
assert sha(transaction) == sha(E / 'before-export-transaction-qualifier.php')
implementation = [E / name for name in ['planned-partial-export-quote-v2.php', 'planned-partial-export-transaction.php',
    'planned-partial-export-engine.php', 'exercise-partial-export-quote-v2.php', 'exercise-partial-export-transaction-v2.php', 'exercise-partial-export-engine-v2.php']]
paths.extend(E / name for name in ['partial-export-quote-native-result.json', 'partial-export-transaction-native-result.json',
    'partial-export-engine-native-result.json', 'ibs-conversion-partial-export-source.json', 'ibs-conversion-and-partial-export-pages.txt'])
report = {'status': 'PARTIAL_EXPORT_FX_PREPARATION_QUALITY_AND_PRIMARY_IBS_CBS_BASIS_VERIFIED',
    'numeric_cases': 23, 'transaction_cases': 21, 'engine_prototype_cases': 7,
    'independent_decimal': {'converted_gross_brl': money(gross), 'foreign_share_brl': money(foreign), 'domestic_share_brl': money(domestic)},
    'implementation': [{'path': p.relative_to(ROOT).as_posix(), 'sha256': sha(p)} for p in implementation],
    'evidence': [{'path': p.relative_to(ROOT).as_posix(), 'sha256': sha(p)} for p in paths],
    'current_engine_sha256': sha(engine), 'current_import_transaction_sha256': sha(transaction),
    'app_installed': False, 'own_export_bundle_published': False, 'document_obligation_complete': False,
    'scope73_complete': False, 'phase4b_complete': False, 'production_ready': False}
with (E / 'partial-export-fx-preparation-validation.json').open('x', encoding='utf-8') as stream: json.dump(report, stream, indent=2)
print('PASS:23 numeric,21 transaction,7 engine prototype cases; independent Decimal; primary IBS clauses; live runtime unchanged')
