"""Exclusive108 integration checks with D logs and current runtime hashes;no overwrite or suite restart."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019')
label,*args=sys.argv[1:]
assert label.replace('-','').isalnum() and args
assert not any((E/(label+x)).exists() for x in ['.json','.stdout','.stderr'])
def sha(p):
    with p.open('rb') as f:return hashlib.file_digest(f,'sha256').hexdigest()
old=json.loads((ROOT/'storage/app/phase4b-identity104-development-20261019/identity104-development-validation.json').read_text(encoding='utf-8'))
paths=set(old['runtime_hashes'])|{'app/Modules/Fiscal/ConsumptionReform/'+n for n in [
    'PartialExportIssMaterialDate105.php','PartialExportIssDatedResults106.php','PartialExportDatedDpsIdentity107.php',
    'DatedDocumentFactProjection108.php']}
before={p:sha(ROOT/p) for p in sorted(paths)}
cmd=[r'D:\Docker\DockerDesktop\resources\bin\docker.exe','compose','exec','-T','app','php','-d','memory_limit=512M',*args]
start=datetime.now(timezone.utc).isoformat()
proc=subprocess.run(cmd,cwd=ROOT,capture_output=True)
report={'command':cmd,'started_at':start,'finished_at':datetime.now(timezone.utc).isoformat(),
    'exit_code':proc.returncode,'runtime_hashes':before}
for ch in ['stdout','stderr']:
    with (E/(label+'.'+ch)).open('xb') as f:f.write(getattr(proc,ch))
    report[ch+'_sha256']=sha(E/(label+'.'+ch))
with (E/(label+'.json')).open('x',encoding='utf-8') as f:json.dump(report,f,indent=2)
print(json.dumps({'label':label,'exit_code':proc.returncode,'runtime_hashes':len(before),
    'stdout_sha256':report['stdout_sha256']}),flush=True)
print(proc.stdout.decode(errors='replace')[-4500:]+proc.stderr.decode(errors='replace')[-2000:],flush=True)
assert before=={p:sha(ROOT/p) for p in sorted(paths)}
assert proc.returncode==0
