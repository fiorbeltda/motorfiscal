"""Exclusive check evidence for119; never reuse labels or restart a live process."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[1]
EVIDENCE = Path(r'D:\ProjetoSaasExports\phase4b-material-input119-integration-20261020')
label = sys.argv[1]
assert label and all(c.isalnum() or c in '-_' for c in label)
destination = EVIDENCE / label
assert not destination.exists(), 'Exclusive check label already exists'
destination.mkdir()
command = sys.argv[2:]
assert command
start = datetime.now(timezone.utc).isoformat()
def save(name, value):
    with (destination/name).open('x', encoding='utf-8') as f:
        json.dump(value, f, ensure_ascii=False, indent=2)
with (destination/'stdout.log').open('xb') as out, (destination/'stderr.log').open('xb') as err:
    before = time.monotonic()
    process = subprocess.Popen(command, cwd=ROOT, stdout=out, stderr=err)
    save('process-start.json', {'started_at': start, 'pid': process.pid, 'command': command})
    print(f'LIVE:{label};host child PID:{process.pid}', flush=True)
    code = process.wait()
report = {'started_at': start, 'finished_at': datetime.now(timezone.utc).isoformat(),
          'command': command, 'exit_code': code, 'seconds': time.monotonic()-before,
          'stdout_sha256': hashlib.sha256((destination/'stdout.log').read_bytes()).hexdigest(),
          'stderr_sha256': hashlib.sha256((destination/'stderr.log').read_bytes()).hexdigest(),
          'production_ready': False}
save('execution.json', report)
print(json.dumps(report), flush=True)
sys.exit(code)
