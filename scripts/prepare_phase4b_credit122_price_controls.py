"""Preserve price builder templates and bind the completed question-repair cut."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

repo = Path(__file__).resolve().parents[1]
base = Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021')
root = base / 'price-controls-v1'


def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


assert not root.exists(), 'Exclusive price-control preparation already exists'
comparison = base / 'question-fix-v2/question-result-comparison.json'
proof = read(comparison)
assert proof['compared_prior_results'] == 176
assert proof['entire_results_identical'] == 156 and proof['declared_question_corrections'] == 20
native = base / 'credit-cli-v2/credit122-cli-validation.json'
assert read(native)['native_decisions'] == read(native)['exact_replays'] == 180
for path, digest in read(native)['runtime_hashes'].items():
    assert sha(repo / path) == digest, path
for name in ['material-input-prices-2026.10.21-122-v1', 'material-input-prices-upstream-2026.10.21-122-v1']:
    assert not (repo / 'resources/fiscal/scenarios' / name).exists()
for name in ['credit122-price-expectations.json', 'credit122-price-upstream-expectations.json']:
    assert not (repo / 'tests/Fixtures' / name).exists()
assert not (repo / 'tests/Fiscal/Integration/Credit122DocumentPricesTest.php').exists()
seed = base / 'credit122-upstream-source-seed-v1.json'
assert sha(seed) == 'dbd780c4e18cf148e4afa42588c3c49de52f76b1aa6a13494473045d1c8cbcb2'
root.mkdir()
records = []
for path in ['scripts/build_phase4b_credit122_price_scenarios.php',
             'scripts/build_phase4b_credit122_price_upstream.php',
             'tests/Fiscal/Integration/Material119DocumentPricesTest.php']:
    source = repo / path
    target = root / 'before' / path
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source, target)
    assert sha(source) == sha(target)
    records.append({'source': path, 'archive': str(target), 'sha256': sha(source)})
with (root / 'before-price-controls-validation.json').open('x', encoding='utf-8') as stream:
    json.dump({'created_at': datetime.now(timezone.utc).isoformat(),
               'status': 'PRICE122_TEMPLATES_AND_PRIOR_QUESTION_CUT_PRESERVED',
               'archives': records, 'seed_sha256': sha(seed), 'native180_sha256': sha(native),
               'comparison176_sha256': sha(comparison), 'runtime_hashes': read(native)['runtime_hashes'],
               'installed_price_scenarios': False, 'full122_acceptance': False}, stream, indent=2)
print('PASS:price templates preserved;180 native/replays and176 comparisons bound;target paths absent')
