"""Exclusive112 checks and87 runtime hashes; no overwrite or automatic restart."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib
import json
import subprocess
import sys

R=Path(__file__).resolve().parents[1];E=Path(r'D:\ProjetoSaasExports\phase4b-price112-integration-20261019')
label,*args=sys.argv[1:];assert label.replace('-','').isalnum()and args
assert not any((E/(label+suffix)).exists()for suffix in['.json','.stdout','.stderr'])
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
accepted=json.loads((R/'storage/app/phase4b-dated108-development-20261019/dated108-development-validation.json').read_text())
paths=set(accepted['runtime_hashes'])|{'app/Modules/Fiscal/ConsumptionReform/'+name for name in[
 'PartialExportContractPrices110.php','PartialExportIssPriceComposition111.php',
 'DocumentPriceFactProjection112.php','PartialExportDocumentPrices112.php']}
assert len(paths)==87
before={p:sha(R/p)for p in sorted(paths)}
command=[r'D:\Docker\DockerDesktop\resources\bin\docker.exe','compose','exec','-T','app','php','-d','memory_limit=512M',*args]
started=datetime.now(timezone.utc).isoformat()
with(E/(label+'.stdout')).open('xb')as out,(E/(label+'.stderr')).open('xb')as err:
    proc=subprocess.run(command,cwd=R,stdout=out,stderr=err)
after={p:sha(R/p)for p in sorted(paths)}
report={'command':command,'started_at':started,'finished_at':datetime.now(timezone.utc).isoformat(),
 'exit_code':proc.returncode,'runtime_hashes':before,'runtime_changed_during_check':[p for p in before if before[p]!=after[p]],
 'stdout_sha256':sha(E/(label+'.stdout')),'stderr_sha256':sha(E/(label+'.stderr'))}
with(E/(label+'.json')).open('x',encoding='utf-8')as f:json.dump(report,f,indent=2)
print(json.dumps({'label':label,'exit_code':proc.returncode,'runtime_files':87,
 'runtime_changed_during_check':report['runtime_changed_during_check']}),flush=True)
for channel in['stdout','stderr']:
    print((E/(label+'.'+channel)).read_text(encoding='utf-8',errors='replace')[-4500:],flush=True)
sys.exit(proc.returncode if before==after else 1)
