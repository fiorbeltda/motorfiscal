"""Freeze the accepted94 state before changing the opt-in export/ISS flow."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-integration-20261016')
A = ROOT / 'storage/app/phase4b-document94-development-20261016/document94-development-validation.json'
def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()
assert not E.exists(), 'Exclusive archive'
assert sha(A) == 'ac7e8926f3d2ec9c4c93b975f63a240186b57879fca53bc7261591fd3b1d69ce'
accepted = json.loads(A.read_text(encoding='utf-8'))
proof = json.loads(Path(r'D:\ProjetoSaasExports\phase4b-document94-component-integration-20261016\preflight.json').read_text(encoding='utf-8'))
for path, digest in proof['input_hashes'].items():
    assert sha(Path(path)) == digest, path
for path, digest in accepted['runtime_hashes'].items():
    assert sha(ROOT / path) == digest, path
E.mkdir()
archive = {}
for path in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
             'resources/monitoring/task-progress.json', 'resources/monitoring/objective-review.json',
             'resources/monitoring/roadmap.json', 'docs/fiscal/phase4b-status.md', 'docs/fiscal/implementation-status.md']:
    target = E / 'before-integration' / path
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(ROOT / path, target)
    assert sha(ROOT / path) == sha(target)
    archive[path] = sha(target)
with (E / 'before-integration-archive.json').open('x', encoding='utf-8') as stream:
    json.dump({'created_at': datetime.now(timezone.utc).isoformat(), 'accepted94_sha256': sha(A),
               'frozen94_inputs_unchanged': len(proof['input_hashes']), 'runtime94_unchanged': len(accepted['runtime_hashes']),
               'archived': archive}, stream, ensure_ascii=False, indent=2)
print('PASS:5360 accepted94 inputs/72 runtime hashes unchanged; original engine and monitoring archived', flush=True)
