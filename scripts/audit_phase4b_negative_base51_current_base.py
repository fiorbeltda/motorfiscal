"""Audit arithmetic against an independent Decimal oracle, preserving original captures."""
from pathlib import Path
from decimal import Decimal, localcontext, ROUND_HALF_EVEN
import hashlib
import json
import re
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base51-current-bases-preparation-20261009'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}


quality = []
for label in ['current-base-tests', 'current-base-pint', 'current-base-phpstan',
              'current-base-capture-check', 'current-base-native-replay']:
    path = E / (label + '.json')
    item = read(path)
    assert item['exit_code'] == 0 and item['log_sha256'] == sha(E / (label + '.log'))
    quality.append(ref(path))
log = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'current-base-tests.log').read_text(encoding='utf-8'))
assert '18 passed' in log and '44 assertions' in log
research = read(E / 'current-bases-research-contract.json')
observation = read(E / 'table12-2-source-observation.json')
assert sha(ROOT / observation['source_path']) == observation['source_sha256']
assert sha(ROOT / observation['excerpt_path']) == observation['excerpt_sha256']
for item in research['sources']:
    assert sha(ROOT / item['path']) == item['sha256']
    assert sha(ROOT / item['excerpt']['path']) == item['excerpt']['sha256']
capture = read(E / 'current-base-native-capture.json')
assert capture['monthly_engine_integration_complete'] is False
assert capture['composition_source_qualified'] is False and capture['rate_source_qualified'] is False
assert capture['production_ready'] is False and capture['negative_base_consumption_applied'] is False
with localcontext() as context:
    context.prec = 70
    def intermediate(value):
        return value.quantize(Decimal('.00000001'), rounding=ROUND_HALF_EVEN)

    def money(value):
        return format(value.quantize(Decimal('.01'), rounding=ROUND_HALF_EVEN), 'f')

    for name, item in capture['inputs'].items():
        result = capture['results'][name]
        assert result['input_hash'] == digest(item)
        assert result['status'] == 'LOCAL_CURRENT_BASE_ARITHMETIC_CALCULATED'
        general = Decimal(item['general_revenue']) + Decimal(item['public_offer_debt_return'])
        transition = Decimal(item['transition_revenue'])
        revenues = general + transition
        total = revenues - Decimal(item['other_deductible_expenses']) - Decimal(item['allocated_funding_expenses'])
        total += Decimal(item['export_reversal']) + Decimal(item['cooperative_reversal'])
        general_gross = intermediate(total * general / revenues) if item['transition_present'] else intermediate(total)
        transition_gross = intermediate(total * transition / revenues) if item['transition_present'] else Decimal('0')
        rates = {tax: Decimal(rate) for tax, rate in item['rates'].items()}
        ibs_rate = rates['IBS_STATE'] + rates['IBS_MUNICIPAL']
        general_net = intermediate(general_gross / (1 + (ibs_rate + rates['CBS']) / 100))
        transition_net = intermediate(transition_gross / (1 + ibs_rate / 100))
        assert Decimal(result['general_net']) == general_net
        assert Decimal(result['transition_net']) == transition_net
        for pool, signed in [('I', general_net + transition_net), ('C', general_net)]:
            row = result['pools'][item['codBC'] + pool]
            assert Decimal(row['signed_current_base']) == signed
            assert row['positive_base'] == money(max(Decimal(0), signed))
            assert row['generated_negative'] == money(max(Decimal(0), -signed))
        assert result['composition_source_qualified'] is False
        assert result['rate_source_qualified'] is False and result['production_ready'] is False

failure = read(E / 'current-base-capture-wrapper-failure.json')
assert failure['native_command_exit_code'] == 0 and failure['wrapper_exit_code'] == 1
assert failure['capture_sha256'] == sha(E / 'current-base-native-capture.json')
assert failure['log_sha256'] == sha(E / 'current-base-native-capture.log')
files = ['app/Modules/Fiscal/ConsumptionReform/DereFinancialCurrentBaseCalculator.php',
         'tests/Fiscal/Unit/DereFinancialCurrentBase51Test.php',
         'scripts/capture_phase4b_negative_base51_check.py',
         'scripts/capture_phase4b_negative_base51_current_base.py',
         'scripts/audit_phase4b_negative_base51_current_base.py',
         'docs/fiscal/phase4b-negative-base-current-base51-development.md']
report = {'status': 'CURRENT_BASE_ARITHMETIC_FOUNDATION_VALIDATED_NOT_MONTHLY_ENGINE_INTEGRATION',
          'targeted_tests': 18, 'targeted_assertions': 44, 'native_cases': len(capture['inputs']),
          'independent_decimal_oracle_matches': True, 'quality': quality,
          'implementation': [ref(ROOT / name) for name in files],
          'capture': ref(E / 'current-base-native-capture.json'),
          'research': ref(E / 'current-bases-research-contract.json'),
          'table12_2_observation': ref(E / 'table12-2-source-observation.json'),
          'preserved_capture_record_failure': ref(E / 'current-base-capture-wrapper-failure.json'),
          'composition_source_qualified': False, 'rate_source_qualified': False,
          'monthly_engine_integration_complete': False, 'negative_base_consumption_applied': False,
          'full_suite_after_current_base51_run': False, 'scope51_complete': False,
          'scope28_complete': False, 'phase4b_complete': False, 'production_ready': False}
path = E / 'current-base-development-validation.json'
raw = json.dumps(report, ensure_ascii=False, indent=2) + '\n'
if path.exists():
    assert path.read_text(encoding='utf-8') == raw
else:
    with path.open('x', encoding='utf-8') as stream:
        stream.write(raw)
print('PASS: 18 tests/44 assertions; three native cases match Decimal; preserved failure and explicit integration limits')
