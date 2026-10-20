"""Bind audited public corpus to unchanged55 runtime generation without editing frozen report."""
from pathlib import Path
import hashlib
import json
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-56-corpus-preparation-20261013'
P = ROOT / 'storage/app/phase4b-dere9121-55-command-integration-20261013'

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

record = json.loads((P / 'integration-progress-validation.json').read_text(encoding='utf-8'))
for item in record['implementation']:
    assert sha(ROOT / item['path']) == item['sha256']
report = E / 'corpus-development-validation.json'
assert json.loads(report.read_text(encoding='utf-8'))['case_count'] == 20
with (E / 'corpus-runtime-source-binding.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'AUDITED56_CORPUS_BOUND_TO_UNCHANGED55_RUNTIME_GENERATION',
        'corpus_report_sha256': sha(report), 'prior55_runtime_report_sha256': sha(P / 'integration-progress-validation.json'),
        'runtime_implementation': record['implementation'],
        'document_sha256': sha(ROOT / 'docs/fiscal/phase4b-dere9121-56-public-corpus-development.md'),
        'full_suite_after55_verified': False, 'production_ready': False}, stream, indent=2)
print('PASS: audited56 corpus bound to unchanged55 runtime hashes; frozen reports preserved')
