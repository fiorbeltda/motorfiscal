"""Preserve the pre-question-fix122 cut before changing code or tests."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

repo = Path(__file__).resolve().parents[1]
base = Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021')
target = base / 'question-fix-v2'


def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


assert not target.exists(), 'Exclusive preservation directory exists'
runtime = json.loads((base / 'credit122-targeted-validation.json').read_text(encoding='utf-8'))['runtime_hashes']
for relative, digest in runtime.items():
    assert sha(repo / relative) == digest, relative
assert len(runtime) == 94
gap = json.loads((base / 'question-gap-v1/question-gap-observation.json').read_text(encoding='utf-8'))
for witness in gap['witnesses']:
    assert sha(Path(witness['snapshot'])) == witness['snapshot_sha256']
historical = base / 'historical-replay-v1/credit122-historical-validation.json'
assert sha(historical) == 'ddffca53a90188148c06c90d8b3cedb12ba24a2201e5198f823fb6505b349520'
target.mkdir()
records = []
for relative in [
    'app/Modules/Fiscal/ConsumptionReform/CreditInput122.php',
    'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'tests/Fiscal/Unit/CreditInput122Test.php',
    'tests/Fiscal/Integration/CreditInput122IntegrationTest.php',
]:
    source = repo / relative
    destination = target / 'before' / relative
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source, destination)
    assert sha(source) == sha(destination)
    records.append({'source': relative, 'archive': str(destination), 'sha256': sha(source)})
with (target / 'before-question-fix-validation.json').open('x', encoding='utf-8') as stream:
    json.dump({'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'PRE_QUESTION_FIX122_PRESERVED',
               'runtime_hashes': runtime, 'archives': records,
               'original_question_gap_sha256': sha(base / 'question-gap-v1/question-gap-observation.json'),
               'historical1441_sha256': sha(historical), 'code_changed': False}, stream, indent=2)
print('PASS:94 runtime hashes,4 gap witnesses and4 source/test archives verified')
