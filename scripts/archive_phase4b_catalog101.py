"""Archive accepted98 state and verify99/100 preparations before source/reference101 integration."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-integration-20261016')
F = Path(r'D:\ProjetoSaasExports\phase4b-sp98-full-suite-20261016')
A = ROOT / 'storage/app/phase4b-sp98-development-20261016/sp98-development-validation.json'
S = Path(r'D:\ProjetoSaasExports\phase4b-catalog100-reference-preparation-20261016')
assert not E.exists()
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
assert sha(A) == '29b80efd975c9a5597938aefd55e77a6f0fca7d7dd779efc9e220342b3a8fad7'
pre = json.loads((F / 'preflight.json').read_text(encoding='utf-8'))
for p, h in pre['input_hashes'].items(): assert sha(Path(p)) == h, p
for p, h in pre['runtime_hashes'].items(): assert sha(ROOT / p) == h, p
prep = json.loads((S / 'catalog100-reference-preparation-validation.json').read_text(encoding='utf-8'))
assert prep['native_cases'] == 2981 and prep['native_checks'] == 37029 and prep['runtime_integrated'] is False
assert sha(S / 'CClassTribReference100-formatted.php') == prep['native_file_hashes']['CClassTribReference100.php']
E.mkdir()
archive = {}
for p in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
    'resources/monitoring/task-progress.json', 'resources/monitoring/objective-review.json',
    'resources/monitoring/roadmap.json', 'docs/fiscal/phase4b-status.md', 'docs/fiscal/implementation-status.md']:
    target = E / 'before-integration' / p
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(ROOT / p, target)
    assert sha(ROOT / p) == sha(target)
    archive[p] = sha(target)
with (E / 'before-integration-archive.json').open('x', encoding='utf-8') as stream:
    json.dump({'created_at': datetime.now(timezone.utc).isoformat(), 'acceptance98_sha256': sha(A),
        'frozen98_inputs_unchanged': len(pre['input_hashes']), 'runtime98_hashes_unchanged': len(pre['runtime_hashes']),
        'preparation100_sha256': sha(S / 'catalog100-reference-preparation-validation.json'), 'archived': archive}, stream, indent=2)
print('PASS:7577 accepted98 inputs/77 runtime hashes;predecessor archived;100 matched', flush=True)
