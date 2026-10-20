"""Record the user-requested stop checkpoint; do not advance development."""
from pathlib import Path
import hashlib, json, re
from datetime import datetime, timezone
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
def read(path): return json.loads(path.read_text(encoding='utf-8'))
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
records = []
for label in ['scope73-native-corpus', 'scope73-directed-tests', 'scope73-phpstan-all', 'scope73-pint-all']:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(Q / (label + '.log'))
    records.append({'label': label, 'record_path': (Q / (label + '.json')).relative_to(ROOT).as_posix(), 'log_sha256': record['log_sha256']})
directed = re.sub(r'\x1b\[[0-9;]*m', '', (Q / 'scope73-directed-tests.log').read_text(encoding='utf-8'))
assert '50 passed' in directed and '213 assertions' in directed
prior = read(Q / 'combined-integration-development-validation.json')
assert prior['full_suite_complete'] and prior['full_suite'] == {'tests': 2233, 'assertions': 45597}
corpus = read(E / 'export-integrated-corpus-validation.json')
assert len(corpus['cases']) == 12 and len(corpus['prior_compatibility']) == 18
for case in corpus['cases']:
    assert sha(E / (case['name'] + '-export-snapshot.json')) == case['snapshot_sha256'] and case['exact_replay']
for case in corpus['prior_compatibility']: assert sha(ROOT / case['path']) == case['sha256'] and case['exact_replay']
paths = [ROOT / name for name in [
    'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/ConsumptionReform/PartialExportFxQuoteConverter.php',
    'app/Modules/Fiscal/ConsumptionReform/PartialExportFxTransactionQualifier.php',
    'tests/Fiscal/Unit/PartialExportFxTransaction73Test.php', 'tests/Fiscal/Integration/PartialExportFxEngine73Test.php',
    'resources/fiscal/bundles/consumption-phase4b-2026.10.14-73-draft1.json',
    'resources/fiscal/catalogs/rtc-codes-2026.10.14-73-draft1.json',
    'resources/fiscal/facts/consumption-phase4b-2026.10.14-73-draft1.json',
    'resources/fiscal/questions/consumption-integrated-2026.10.14-73-draft1.json',
    'resources/fiscal/production/phase4b-controls-2026.10.14-73-draft1.json']]
assert not (Q / 'scope73-full-suite.json').exists()
report = {'status': 'USER_REQUESTED_HANDOFF_CHECKPOINT_EXPORT73_INSTALLED_DIRECTED_VERIFIED_FULL_REGRESSION_PENDING',
    'recorded_at': datetime.now(timezone.utc).isoformat(), 'prior71_full_suite': prior['full_suite'],
    'current73_directed_tests': 50, 'current73_assertions': 213, 'native_cases': 12, 'prior_exact_replays': 18,
    'pint_files': 501, 'full_suite73_started': False,
    'directed_and_phpstan_captured_before_final_formatting': True, 'pint_captured_after_final_formatting': True,
    'implementation': [{'path': p.relative_to(ROOT).as_posix(), 'sha256': sha(p)} for p in paths],
    'quality_records': records, 'document_obligation_complete': False, 'scope73_complete': False,
    'phase4b_complete': False, 'production_ready': False,
    'next_action': 'Verify current hashes, run final directed checks if needed, then full Pest suite (expected 2266 tests) and new immutable development audit.'}
with (E / 'handoff-checkpoint-validation.json').open('x', encoding='utf-8') as stream: json.dump(report, stream, indent=2)
print('PASS:user-requested checkpoint preserved; export73 full suite not started; broad goal remains incomplete')
