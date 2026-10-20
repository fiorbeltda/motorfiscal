"""Exclusive staged95 checks; result binding is not government or production approval."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-iss-export95-preparation-20261016')
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
label, *args = sys.argv[1:]
assert label.replace('-', '').isalnum()
for suffix in ['.stdout', '.stderr', '.json']:
    assert not (E / (label + suffix)).exists(), 'Exclusive label required'
command = [D, 'compose', 'exec', '-T', *(['--user', 'root'] if label.startswith('format-') else []),
           'app', 'php', '-d', 'memory_limit=512M', *args]
started = datetime.now(timezone.utc).isoformat()
proc = subprocess.run(command, cwd=ROOT, capture_output=True)
record = {'command': command, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(), 'exit_code': proc.returncode}
for channel in ['stdout', 'stderr']:
    raw = getattr(proc, channel)
    with (E / (label + '.' + channel)).open('xb') as stream:
        stream.write(raw)
    record[channel + '_sha256'] = hashlib.sha256(raw).hexdigest()
if label.startswith('native') and proc.returncode == 0:
    payload = json.loads(proc.stdout)
    record['structured_native_pass'] = payload['status'] == 'ISS95_NATIVE_INDEPENDENT_RESULT_BINDING_CASES_PASSED' and payload['passed'] == 116
    record['native_cases'] = payload['passed']
    record['checks'] = payload['checks']
with (E / (label + '.json')).open('x', encoding='utf-8') as stream:
    json.dump(record, stream, indent=2)
print(json.dumps(record), flush=True)
if proc.returncode != 0:
    print(proc.stdout.decode(errors='replace')[-1800:] + proc.stderr.decode(errors='replace')[-1800:], flush=True)
assert proc.returncode == 0 and record.get('structured_native_pass', True)
