"""Exclusive check capture for staged municipal97 code, outside accepted runtime."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document97-sp-primary-research-20261016-v2')
label, *args = sys.argv[1:]
assert label.replace('-', '').isalnum()
assert all(not (E / (label + suffix)).exists() for suffix in ['.stdout', '.stderr', '.json'])
cmd = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T',
    *(['--user', 'root'] if label.startswith('format-') else []), 'app', 'php', '-d', 'memory_limit=512M', *args]
record = {'command': cmd, 'started_at': datetime.now(timezone.utc).isoformat()}
proc = subprocess.run(cmd, cwd=ROOT, capture_output=True)
record.update(exit_code=proc.returncode, finished_at=datetime.now(timezone.utc).isoformat())
for channel in ['stdout', 'stderr']:
    raw = getattr(proc, channel)
    with (E / (label + '.' + channel)).open('xb') as stream: stream.write(raw)
    record[channel + '_sha256'] = hashlib.sha256(raw).hexdigest()
with (E / (label + '.json')).open('x', encoding='utf-8') as stream: json.dump(record, stream, indent=2)
if proc.returncode == 0 and label.startswith('native'):
    payload = json.loads(proc.stdout)
    print(json.dumps({k: v for k, v in payload.items() if k != 'cases'}))
else:
    print(json.dumps(record))
    print(proc.stdout.decode(errors='replace')[-1800:] + proc.stderr.decode(errors='replace')[-1800:])
assert proc.returncode == 0
