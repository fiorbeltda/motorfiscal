"""Capture prototype composition with structured success verification and compact output."""
from pathlib import Path
import hashlib
import json
import subprocess
import sys
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
label = sys.argv[1]
assert label.replace('-', '').isalnum()
log = E / (label + '.log')
record = E / (label + '.json')
assert not log.exists() and not record.exists()
command = sys.argv[2:]
result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
with log.open('x', encoding='utf-8') as stream:
    stream.write(result.stdout + result.stderr)
assert result.returncode == 0, result.stderr
data = json.loads(result.stdout)
assert data['result']['status'] == 'LOCAL_MONTHLY_CURRENT_BASE_COMPOSITION_RECONCILED', data['result']
assert data['financial_replay_matches'] is True and data['production_ready'] is False
path = E / 'composition-native-result.json'
if path.exists():
    assert json.loads(path.read_text(encoding='utf-8')) == data
else:
    with path.open('x', encoding='utf-8') as stream:
        json.dump(data, stream, indent=2)
with record.open('x', encoding='utf-8') as stream:
    json.dump({'command': command, 'exit_code': result.returncode, 'structured_result_checked': True,
        'log_sha256': hashlib.sha256(log.read_bytes()).hexdigest()}, stream, indent=2)
print(json.dumps({'status': data['result']['status'], 'composition': data['result']['calculation_input'],
    'pools': data['result']['calculation']['pools'], 'prototype_installed': False}, ensure_ascii=False))
