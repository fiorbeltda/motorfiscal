"""Capture independent settlement checks with exclusive evidence writes."""
from pathlib import Path
import subprocess
import hashlib
import json
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base52-settlement-draft-20261009'
label = sys.argv[1]
assert label.replace('-', '').replace('_', '').isalnum()
log = E / (label + '.log')
record = E / (label + '.json')
assert not log.exists() and not record.exists()
command = sys.argv[2:]
result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
with log.open('x', encoding='utf-8') as stream:
    stream.write(result.stdout + result.stderr)
with record.open('x', encoding='utf-8') as stream:
    json.dump({'command': command, 'exit_code': result.returncode,
               'log_sha256': hashlib.sha256(log.read_bytes()).hexdigest()}, stream, indent=2)
print(result.stdout + result.stderr)
sys.exit(result.returncode)
