"""Audit combined successor; full acceptance requires the actual terminal suite."""
from pathlib import Path
import hashlib, json, re, sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
O = ROOT / 'storage/app/phase4b-fx70-origin-preparation-20261014'
def read(path): return json.loads(path.read_text(encoding='utf-8'))
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()

full = '--full' in sys.argv
paths = []
for label in ['combined71-final-directed', 'combined71-type', 'combined71-style', 'combined71-origin-corpus', 'combined71-credit-replays-v2'] + (['combined71-full-suite'] if full else []):
    record = read(E / (label + '.json'))
    assert record['exit_code'] == 0
    assert record['log_sha256'] == sha(E / (label + '.log'))
    paths.extend([E / (label + '.json'), E / (label + '.log')])
directed = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'combined71-final-directed.log').read_text(encoding='utf-8'))
assert '62 passed' in directed and '207 assertions' in directed
origin = read(O / 'integrated-origin-capture-validation.json')
assert len(origin['cases']) == 9 and len(origin['old68_compatibility']) == 14
for case in origin['cases']:
    assert case['exact_replay'] and sha(O / (case['name'] + '-origin-snapshot.json')) == case['snapshot_sha256']
replay_dir = E / 'replay-v2'
replays = read(replay_dir / 'post-fix-replay-validation.json')
assert len(replays['cases']) == 18
for case in replays['cases']:
    assert case['exact_replay'] and sha(ROOT / case['path']) == case['sha256']
    assert sha(replay_dir / (Path(case['path']).stem + '-replay.log')) == case['log_sha256']
assert sha(E / 'before-combined-install-converter.php') == read(E / 'exact-quote-preparation-validation.json')['current_converter_sha256']
suite = None
if full:
    log = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'combined71-full-suite.log').read_text(encoding='utf-8'))
    match = re.search(r'Tests:\s+2233 passed \((\d+) assertions\)', log)
    assert match, 'Expected actual terminal result for all 2233 tests'
    suite = {'tests': 2233, 'assertions': int(match[1])}
paths.extend([O / 'integrated-origin-capture-validation.json', replay_dir / 'post-fix-replay-validation.json'])
implementation = [ROOT / name for name in ['app/Modules/Fiscal/ConsumptionReform/ImportFxQuoteConverter.php',
    'app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php',
    'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'tests/Fiscal/Unit/ImportFxMaterialOrigin70Test.php', 'tests/Fiscal/Unit/ImportFxExactToken71Test.php',
    'resources/fiscal/bundles/consumption-phase4b-2026.10.14-70-draft1.json',
    'resources/fiscal/catalogs/rtc-codes-2026.10.14-70-draft1.json',
    'resources/fiscal/production/phase4b-controls-2026.10.14-70-draft1.json']]
report = {'status': 'COMBINED_ORIGIN_OFFSET_EXACT_TOKEN_DEVELOPMENT_ACCEPTED' if full else 'COMBINED_ORIGIN_OFFSET_EXACT_TOKEN_DIRECTED_PROGRESS_VERIFIED',
    'directed_tests': 62, 'directed_assertions': 207, 'new_integrated_decisions': 9, 'exact_prior_fx_credit_replays': 18,
    'full_suite_complete': full, 'full_suite': suite,
    'implementation': [{'path': p.relative_to(ROOT).as_posix(), 'sha256': sha(p)} for p in implementation],
    'evidence': [{'path': p.relative_to(ROOT).as_posix(), 'sha256': sha(p)} for p in paths],
    'non_usd_material_origin_complete': False, 'cross_currency_complete': False,
    'document_obligation_complete': False, 'full_scope68_complete': False, 'phase4b_complete': False, 'production_ready': False}
target = E / ('combined-integration-development-validation.json' if full else 'combined-integration-progress-validation.json')
with target.open('x', encoding='utf-8') as stream: json.dump(report, stream, indent=2)
print('PASS:combined successor evidence verified; broad scope remains pending; full=' + str(full))
