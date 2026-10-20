"""Exclusive native/type/style evidence for isolated103 files on D and container /tmp."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-identity103-local-preparation-20261016')
D=r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
label,*args=sys.argv[1:]
assert label.replace('-','').isalnum()
assert args
assert not any((E/(label+ext)).exists() for ext in ['.json','.stdout','.stderr'])
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
files=['PartialExportDpsIdentity103.php','probe103.php','identity103-cases.json']
container=subprocess.run([D,'compose','ps','-q','app'],cwd=ROOT,capture_output=True,text=True,check=True).stdout.strip()
assert container
subprocess.run([D,'compose','exec','-T','app','mkdir','-p','/tmp/phase4b-identity103'],cwd=ROOT,check=True)
for name in files:
    subprocess.run([D,'cp',str(E/name),container+':/tmp/phase4b-identity103/'+name],cwd=ROOT,check=True,capture_output=True)
subprocess.run([D,'compose','exec','-T','-u','root','app','chown','www-data:www-data',
    *['/tmp/phase4b-identity103/'+name for name in files]],cwd=ROOT,check=True,capture_output=True)
before={name:sha(E/name) for name in files}
command=[D,'compose','exec','-T','app','php','-d','memory_limit=512M',*args]
started=datetime.now(timezone.utc).isoformat()
proc=subprocess.run(command,cwd=ROOT,capture_output=True)
record={'command':command,'started_at':started,'finished_at':datetime.now(timezone.utc).isoformat(),
    'exit_code':proc.returncode,'native_file_hashes':before}
for channel in ['stdout','stderr']:
    with (E/(label+'.'+channel)).open('xb') as stream:stream.write(getattr(proc,channel))
    record[channel+'_sha256']=sha(E/(label+'.'+channel))
with (E/(label+'.json')).open('x',encoding='utf-8') as stream:json.dump(record,stream,indent=2)
print(json.dumps(record),flush=True)
if proc.returncode:print(proc.stdout.decode(errors='replace')[-3500:]+proc.stderr.decode(errors='replace')[-2000:],flush=True)
elif args[0].endswith('probe103.php'):
    result=json.loads(proc.stdout)
    print(json.dumps({k:v for k,v in result.items() if k!='results'}),flush=True)
assert before=={name:sha(E/name) for name in files}
assert proc.returncode==0
