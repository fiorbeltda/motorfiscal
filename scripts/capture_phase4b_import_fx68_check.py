"""Capture immutable import-FX integration quality evidence."""
from pathlib import Path
import hashlib,json,subprocess,sys
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'
label=sys.argv[1]
assert label.replace('-','').isalnum()
log=E/(label+'.log');record=E/(label+'.json')
assert not log.exists() and not record.exists()
command=sys.argv[2:]
result=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
with log.open('x',encoding='utf-8') as f:f.write(result.stdout+result.stderr)
with record.open('x',encoding='utf-8') as f:json.dump({'command':command,'exit_code':result.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()},f,indent=2)
try:
    data=json.loads(result.stdout);print(json.dumps({'exit_code':result.returncode,'status':data.get('status'),'reason_codes':data.get('reason_codes'),'replay_matches':data.get('replay_matches')}))
except ValueError:print((result.stdout+result.stderr)[-2400:])
raise SystemExit(result.returncode)
