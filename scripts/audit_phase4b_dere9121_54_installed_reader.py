"""Verify installed reader and prototype arithmetic without claiming full54 integration."""
from pathlib import Path
from decimal import Decimal, ROUND_HALF_EVEN
import hashlib
import json
import re
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}

source = ROOT / 'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php'
installation = read(E / 'reader-source-installation.json')
assert sha(source) == installation['installed_sha256'] == installation['prototype_sha256']
quality = []
for label in ['reader-installed-final-tests', 'reader-installed-final-phpstan', 'reader-installed-test-format',
    'composition-routed-capture', 'composition-routed-replay', 'composition-directed-tests',
    'composition-planned-final-phpstan', 'composition-planned-format']:
    record = read(E / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(E / (label + '.log')), label
    quality.append(ref(E / (label + '.json')))
tests = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'reader-installed-final-tests.log').read_text(encoding='utf-8'))
assert '16 passed' in tests and '82 assertions' in tests
capture = read(E / 'composition-native-result.json')
values = capture['result']['calculation_input']
assert capture['financial_replay_matches'] and not capture['production_ready']
partial = Decimal(values['general_revenue']) + Decimal(values['public_offer_debt_return']) - Decimal(values['other_deductible_expenses']) - Decimal(values['allocated_funding_expenses'])
assert partial == Decimal('798.17')
base = (partial / Decimal('1.01')).quantize(Decimal('.00000001'), rounding=ROUND_HALF_EVEN)
assert base == Decimal('790.26732673')
assert capture['result']['calculation']['total_partial'] == str(partial)
for root in ['1010I', '1010C']:
    assert capture['result']['calculation']['pools'][root]['positive_base'] == format(base.quantize(Decimal('.01'), rounding=ROUND_HALF_EVEN), 'f')
assert values['general_revenue'] == '1000.00' and values['public_offer_debt_return'] == '98.17'
assert capture['result']['d9121_reconciliation']['total_vApur'] == '98.17'
files = [source, ROOT / 'tests/Fiscal/Unit/DerePublicOfferDebtReturn54Test.php',
    E / 'planned-DereFinancialPublicOfferCurrentBaseCompositionQualifier.php', E / 'exercise-composition-bindings.php', Path(__file__)]
with (E / 'installed-reader-and-composition-progress.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'D9121_READER_INSTALLED_AND_TESTED_PUBLIC_COMPOSITION_PROTOTYPE_RECONCILED',
        'reader_tests': 16, 'reader_assertions': 82, 'composition_binding_cases': 7,
        'independent_arithmetic': {'partial': str(partial), 'gross_down_base': str(base), 'public_return_counted_once': True},
        'quality': quality, 'implementation': [ref(p) for p in files],
        'composition_capture': ref(E / 'composition-native-result.json'),
        'initial_exception_output_with_zero_process_exit_accepted': False,
        'composition_installed': False, 'return_knowledge_cutoff_qualified': False,
        'temporal_policy_activated': False, 'monthly_command_integrated': False,
        'full_suite53_covers_installed_reader': False, 'scope54_complete': False, 'phase4b_complete': False,
        'production_ready': False}, stream, indent=2)
print('PASS: installed reader16/82; public composition exact replay, seven binding cases, independent arithmetic; full54 pending')
