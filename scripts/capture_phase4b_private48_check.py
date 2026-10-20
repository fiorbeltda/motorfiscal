"""Record successor48 validation without overwriting previous evidence."""
from pathlib import Path
import subprocess,json,hashlib,sys
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-private-consolidation48-draft-20261009-28'
name=sys.argv[1];p=E/(name+'.log');record=E/(name+'.json')
assert not p.exists() and not record.exists()
cmd=sys.argv[2:]
r=subprocess.run(cmd,cwd=ROOT,capture_output=True,encoding='utf-8')
with p.open('x',encoding='utf-8') as out:out.write(r.stdout+r.stderr)
with record.open('x',encoding='utf-8') as out:out.write(json.dumps({'command':cmd,'exit_code':r.returncode,'log_sha256':hashlib.sha256(p.read_bytes()).hexdigest()},indent=2)+'\n')
print(r.stdout+r.stderr);sys.exit(r.returncode)
