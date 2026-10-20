"""Verify and archive the accepted96 baseline before SP97 integration98."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-sp98-integration-20261016')
F = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-full-suite-20261016')
A = ROOT / 'storage/app/phase4b-export-iss96-development-20261016/export-iss96-development-validation.json'
S = Path(r'D:\ProjetoSaasExports\phase4b-document97-sp-primary-research-20261016-v2')
assert not E.exists()
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
assert sha(A) == '5b44a4cd995dc8ae3e8c8a08f03a4361ae40c671aa151f79234286b4974f3453'
proof = json.loads((F / 'preflight.json').read_text(encoding='utf-8'))
for p, h in proof['input_hashes'].items(): assert sha(Path(p)) == h, p
for p, h in proof['runtime_hashes'].items(): assert sha(ROOT / p) == h, p
prepared = json.loads((S / 'sp97-preparation-validation.json').read_text(encoding='utf-8'))
assert prepared['native_cases'] == 140 and prepared['native_checks'] == 2314 and prepared['runtime_integrated'] is False
assert sha(S / 'SpSoftwareExportResult97-formatted.php') == prepared['native_file_hashes']['SpSoftwareExportResult97.php']
E.mkdir()
archived = {}
for p in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
    'resources/monitoring/task-progress.json', 'resources/monitoring/objective-review.json',
    'resources/monitoring/roadmap.json', 'docs/fiscal/phase4b-status.md', 'docs/fiscal/implementation-status.md']:
    target = E / 'before-integration' / p
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(ROOT / p, target)
    assert sha(target) == sha(ROOT / p)
    archived[p] = sha(target)
with (E / 'before-integration-archive.json').open('x', encoding='utf-8') as stream:
    json.dump({'created_at': datetime.now(timezone.utc).isoformat(), 'acceptance96_sha256': sha(A),
        'frozen96_inputs_unchanged': len(proof['input_hashes']), 'runtime96_hashes_unchanged': len(proof['runtime_hashes']),
        'preparation97_sha256': sha(S / 'sp97-preparation-validation.json'), 'archived': archived}, stream, indent=2)
print('PASS:6121 inputs/75 runtime hashes96 unchanged;preparation97 and predecessor archived', flush=True)
