"""Capture research/prototype checks and verify structured lifecycle success before recording native results."""
from pathlib import Path
import hashlib,json,subprocess,sys
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-59-version-preparation-20261013'
label=sys.argv[1]
assert label.replace('-','').isalnum()
log=E/(label+'.log')
record=E/(label+'.json')
assert not log.exists() and not record.exists()
command=sys.argv[2:]
result=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
with log.open('x',encoding='utf-8') as stream:
    stream.write(result.stdout+result.stderr)
with record.open('x',encoding='utf-8') as stream:
    json.dump({'command':command,'exit_code':result.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()},stream,indent=2)
if label.startswith('version-native-') and result.returncode==0:
    data=json.loads(result.stdout)
    count=12 if '-v2-' in label else 10
    assert data['status']=='PUBLIC_VERSION_PROTOTYPE_EXERCISES_PASSED' and len(data['results'])==count
    path=E/('version-native-v2-result.json' if count==12 else 'version-native-result.json')
    if path.exists():
        assert json.loads(path.read_text(encoding='utf-8'))==data
    else:
        with path.open('x',encoding='utf-8') as stream:
            json.dump(data,stream,ensure_ascii=False,indent=2)
    print('PASS:4 lifecycle prefixes and'+str(count-4)+' negative cases; structured native capture/replay checked')
else:
    print((result.stdout+result.stderr)[-2500:])
raise SystemExit(result.returncode)
