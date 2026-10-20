"""Record native integration progress and unresolved acceptance gates without weakening historical hashes."""
from pathlib import Path
from decimal import Decimal, ROUND_HALF_EVEN
import hashlib
import json
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-55-command-integration-20261013'
P = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}

accepted = read(ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009/command-development-validation.json')
command = ROOT / 'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php'
archive = E / 'before-public-selection-monthly-command.php'
for item in accepted['implementation']:
    assert sha(archive if ROOT / item['path'] == command else ROOT / item['path']) == item['sha256']
quality = []
for label in ['public-planner-initial-tests', 'public-planner-initial-phpstan', 'public-selection-legacy-tests',
    'public-selection-phpstan-all', 'public-selection-pint-all', 'public-monthly-exact-replay',
    'public-own-financial-replay', 'public-settlement-native-v3-capture', 'public-settlement-after-format-replay',
    'public-bundle-reproduction', 'public-production-check']:
    record = read(P / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(P / (label + '.log')), label
    quality.append(ref(P / (label + '.json')))
assert read(P / 'public-production-check.log')['ready'] is False
old = read(ROOT / 'resources/fiscal/catalogs/rtc-codes-2026.10.09-28-draft52.json')
new = read(ROOT / 'resources/fiscal/catalogs/rtc-codes-2026.10.13-28-draft54.json')
assert old['dere_negative_base_local_settlement'] == new['dere_negative_base_local_settlement']
proposal = read(E / 'monthly-report.json')['negative_base_settlement']
assert proposal == read(P / 'public-settlement-native-result.json')['result']
assert proposal['return_knowledge_cutoff_qualified'] and not proposal['received_return_verified']
for tax, root, deduction, rate, value in [('IBS', '1010I', '50.00', '.001', '0.74'), ('CBS', '1010C', '10.00', '.009', '7.02')]:
    pool = proposal['assessed_pools'][root]
    assert pool['positive_base'] == '790.27' and pool['deduction'] == deduction
    assessed = Decimal('790.27') - Decimal(deduction)
    assert Decimal(pool['assessed_base']) == assessed
    assert format((assessed * Decimal(rate)).quantize(Decimal('.01'), rounding=ROUND_HALF_EVEN), 'f') == value
    assert proposal['ibs_nominal_proposal' if tax == 'IBS' else 'cbs_nominal_proposal'] == value
native = read(E / 'native-monthly-replay-validation.json')
assert native['exact_replay'] and len(native['xml_sha256']) == 6
for name, path in read(E / 'monthly-report.json')['event_outputs'].items():
    assert sha(ROOT / path) == native['xml_sha256'][name]
files = [command, ROOT / 'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferCurrentBaseCompositionQualifier.php',
    ROOT / 'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferNegativeBaseSettlementPlanner.php',
    ROOT / 'tests/Fiscal/Unit/DerePublicOfferNegativeBaseSettlement54Test.php', Path(__file__)]
with (E / 'integration-progress-validation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'PUBLIC_D9121_NATIVE_MONTHLY_INTEGRATION_PROGRESS_VALIDATED_ACCEPTANCE_PENDING',
        'reader_and_public_planner_tests': 27, 'reader_and_public_planner_assertions': 105,
        'legacy_monthly_tests': 19, 'legacy_monthly_assertions': 128, 'native_xmls': 6, 'exact_monthly_replay': True,
        'accepted53_command_archive': ref(archive), 'quality': quality, 'implementation': [ref(path) for path in files],
        'specific_command55_negative_tests_complete': False, 'candidate27_26_25_regressions_after55_complete': False,
        'full_suite_after55_complete': False, 'scope54_complete': False, 'phase4b_complete': False,
        'production_ready': False}, stream, indent=2)
print('PASS: own54 policy/snapshot, native public55 selection, six XMLs, legacy tests and independent taxes; acceptance pending')
