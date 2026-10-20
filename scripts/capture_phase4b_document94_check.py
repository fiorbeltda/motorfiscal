"""Exclusive staged checks on D; native structured evidence required, not only process exit."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document94-base-reconciliation-20261016')
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
label, *args = sys.argv[1:]
assert label.replace('-', '').replace('_','').isalnum()
for suffix in ['.stdout','.stderr','.json']: assert not (E / (label + suffix)).exists()
command = [D, 'compose','exec','-T',*(['--user','root'] if label.startswith('format-') else []),'app','php','-d','memory_limit=512M',*args]
started = datetime.now(timezone.utc).isoformat()
result = subprocess.run(command, cwd=ROOT, capture_output=True)
record = {'command':command,'started_at':started,'finished_at':datetime.now(timezone.utc).isoformat(),'exit_code':result.returncode}
for channel in ['stdout','stderr']:
    raw = getattr(result, channel)
    with (E / (label + '.' + channel)).open('xb') as stream: stream.write(raw)
    record[channel + '_sha256'] = hashlib.sha256(raw).hexdigest()
if label.startswith('native') and result.returncode == 0:
    try:
        payload = json.loads(result.stdout)
        record['structured_native_pass'] = payload['status'] == 'STAGED94_NATIVE_CASES_AND_DECIMAL_ORACLES_PASSED' and payload['passed'] == 56
        record['native_cases'] = payload['passed']; record['checks'] = payload['checks']
    except (ValueError,KeyError): record['structured_native_pass'] = False
with (E / (label + '.json')).open('x', encoding='utf-8') as stream: json.dump(record, stream, indent=2)
print(json.dumps(record), flush=True)
if result.returncode != 0: print(result.stdout.decode(errors='replace')[-1800:] + result.stderr.decode(errors='replace')[-1800:])
assert result.returncode == 0 and record.get('structured_native_pass',True), 'Failed check preserved under exclusive label'
