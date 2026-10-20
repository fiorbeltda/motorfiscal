"""Freeze exact monetary-token prototype evidence without broad acceptance."""
from pathlib import Path
from decimal import Decimal, ROUND_HALF_UP
import hashlib, json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
def read(path): return json.loads(path.read_text(encoding='utf-8'))
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()

labels = ['exact71-type', 'exact71-compatible-replay', 'exact71-boundary-final-replay', 'exact71-style-v2']
paths = []
for label in labels:
    record = read(E / (label + '.json'))
    assert record['exit_code'] == 0
    assert record['log_sha256'] == sha(E / (label + '.log'))
    paths.extend([E / (label + '.json'), E / (label + '.log')])
assert len(read(E / 'exact-token-replay-result.json')['cases']) == 23
boundary = read(E / 'exact-token-boundary-result.json')['cases']
assert len(boundary) == 2
expected = str((Decimal('1000000.00') * Decimal('9999999999.12345678')).quantize(Decimal('0.01'), rounding=ROUND_HALF_UP))
assert boundary['exact-rate']['planned']['converted_gross_brl'] == expected
assert boundary['exact-rate']['planned']['selected_quote']['rate'] == '9999999999.12345678'
assert boundary['exact-rate']['current']['converted_gross_brl'] != expected
assert boundary['string-rate']['planned']['reason'] == 'RTC_IMPORT_FX_POSITIVE_SALE_BRL_PER_UNIT_QUOTE_REQUIRED'
live = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ImportFxQuoteConverter.php'
assert sha(live) == sha(E / 'before-exact-quote-converter.php')
paths.extend(E / name for name in ['planned-exact-quote-converter.php', 'exercise-exact-token-replay.php', 'exercise-exact-token-boundary.php', 'exact-token-replay-result.json', 'exact-token-boundary-result.json'])
report = {'status': 'EXACT_MONETARY_TOKEN_PROTOTYPE_QUALITY_ACCEPTED', 'compatible_cases': 23,
          'synthetic_boundary_cases': 2, 'independent_decimal_expected': expected,
          'implementation': [{'path': str(p.relative_to(ROOT)).replace('\\', '/'), 'sha256': sha(p)} for p in paths],
          'current_converter_sha256': sha(live), 'app_installed': False,
          'full_scope68_complete': False, 'production_ready': False}
with (E / 'exact-quote-preparation-validation.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, indent=2)
print('PASS:23 exact replays, two synthetic boundaries, independent Decimal and recorded quality')
