"""Verify accepted101 and isolated103 before versioned integration; preserve predecessors on D."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019')
S = Path(r'D:\ProjetoSaasExports\phase4b-identity103-local-preparation-20261016')
F = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-full-suite-20261016')
A = ROOT / 'storage/app/phase4b-catalog101-development-20261016/catalog101-development-validation.json'

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

assert not E.exists()
assert sha(A) == '900bd64cfa7a0eb7775974f936a9d12369929b12028c59fe50e3932a2ed11360'
accepted = read(A)
frozen = read(F / 'preflight.json')['input_hashes']
for p, h in frozen.items():
    assert sha(Path(p)) == h, p
for p, h in accepted['runtime_hashes'].items():
    assert sha(ROOT / p) == h, p
prep = read(S / 'identity103-preparation-validation.json')
assert prep['status'] == 'IDENTITY103_LOCAL_PAIR_PREPARATION_VERIFIED_NOT_INTEGRATED'
assert prep['native_cases'] == 128 and prep['native_checks'] == 2350
for name, h in prep['native_file_hashes'].items():
    assert sha(S / name) == h, name
E.mkdir()
archive = {}
for p in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
          'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
          'resources/monitoring/task-progress.json', 'resources/monitoring/objective-review.json',
          'resources/monitoring/roadmap.json']:
    target = E / 'before-integration' / p
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(ROOT / p, target)
    assert sha(ROOT / p) == sha(target)
    archive[p] = sha(target)
with (E / 'before-integration-archive.json').open('x', encoding='utf-8') as stream:
    json.dump({'created_at': datetime.now(timezone.utc).isoformat(), 'acceptance101_sha256': sha(A),
               'frozen101_inputs_unchanged': len(frozen), 'runtime101_hashes_unchanged': len(accepted['runtime_hashes']),
               'preparation103_sha256': sha(S / 'identity103-preparation-validation.json'), 'archived': archive}, stream, indent=2)
print('PASS:9272 accepted101 inputs/78 runtime hashes;103 verified;predecessors archived', flush=True)
