"""Accept initial synthetic public monthly selection only after full55 quality and historical preservation."""
from pathlib import Path
import hashlib
import json
import re
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-55-command-integration-20261013'
P = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}

quality = []
for label in ['public-command55-tests', 'public-command55-phpstan', 'public-command55-test-format',
    'public-command55-full-suite', 'public-command55-candidate27', 'public-command55-candidate26',
    'public-command55-candidate25', 'public-command55-legacy-compatibility',
    'public-command55-phpstan-all', 'public-command55-pint-all']:
    record = read(P / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(P / (label + '.log')), label
    quality.append(ref(P / (label + '.json')))
text = re.sub(r'\x1b\[[0-9;]*m', '', (P / 'public-command55-full-suite.log').read_text(encoding='utf-8'))
counts = re.search(r'(\d+) passed \((\d+) assertions\)', text)
assert counts and int(counts[1]) == 2033 and int(counts[2]) == 44811
for item in read(E / 'integration-progress-validation.json')['implementation']:
    assert sha(ROOT / item['path']) == item['sha256'], item['path']
legacy = read(E / 'legacy-monthly-compatibility.json')
assert len(legacy['cases']) == 3 and all(row['projection_and_event_hashes_match'] for row in legacy['cases'])
native = read(E / 'native-monthly-replay-validation.json')
assert native['exact_replay'] and sha(E / 'monthly-report.json') == native['report_sha256']
report = read(E / 'monthly-report.json')
assert report['negative_base_settlement'] == read(P / 'public-settlement-native-result.json')['result']
assert len(report['event_outputs']) == 6 and report['production_ready'] is False
for event, output in report['event_outputs'].items():
    assert sha(ROOT / output) == native['xml_sha256'][event]
    assert report['event_validations'][event]['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
assert not read(P / 'public-production-check.log')['ready']
source = ROOT / 'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php'
assert legacy['current_source_sha256'] == sha(source)
accepted53 = read(ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009/command-development-validation.json')
archive = E / 'before-public-selection-monthly-command.php'
for item in accepted53['implementation']:
    assert sha(archive if ROOT / item['path'] == source else ROOT / item['path']) == item['sha256']
files = [source, ROOT / 'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php',
    ROOT / 'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferCurrentBaseCompositionQualifier.php',
    ROOT / 'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferNegativeBaseSettlementPlanner.php',
    ROOT / 'tests/Fiscal/Integration/DerePublicOfferMonthlyCommand55Test.php',
    ROOT / 'tests/Fiscal/Unit/DerePublicOfferDebtReturn54Test.php',
    ROOT / 'tests/Fiscal/Unit/DerePublicOfferNegativeBaseSettlement54Test.php', Path(__file__)]
with (E / 'monthly-development-validation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'INITIAL_SYNTHETIC_D9121_PUBLIC_MONTHLY_SETTLEMENT_SELECTION_VALIDATED',
        'full_tests': int(counts[1]), 'full_assertions': int(counts[2]), 'quality': quality,
        'implementation': [ref(path) for path in files], 'accepted53_source_archive': ref(archive),
        'current_return_processing_known_at_checked': True, 'native_monthly_xmls': 6, 'exact_monthly_replay': True,
        'wider_public_corpus_complete': False, 'public_no_movement_settlement_complete': False,
        'public_event_version_chain_complete': False, 'government_return_authenticated': False,
        'scope54_complete': False, 'scope28_complete': False, 'phase4b_complete': False, 'production_ready': False}, stream, indent=2)
print('PASS: initial synthetic public55 selection, whole suite, regressions, six XMLs and accepted53 archive; wider54 pending')
