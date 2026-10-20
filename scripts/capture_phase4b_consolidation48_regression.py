"""Capture common-knowledge inputs and compress only closed successor files."""
import subprocess,json,hashlib,time,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-private-consolidation48-draft-20261009-28'
S=ROOT/'resources/fiscal/scenarios/tvm-realization-consolidated-2026.10.09-28-draft48'
log=E/'regression87-recaptured.log';record=E/'regression87-recaptured.json'
assert not log.exists() and not record.exists()
cmd=['docker','compose','exec','-T','-e','TVM_REGRESSION48_CAPTURE=1','app','php','-d','memory_limit=512M','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesConsolidation48RegressionTest.php','--compact']
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
preserved={}
def compact_closed():
 for path in S.glob('*-financial.json'):
  # The paired month is written only after the financial stream has closed.
  if path.name in preserved or not path.with_name(path.name.replace('-financial.json','-month.json')).exists():continue
  before=sha(path)
  if path.stat().st_size>1000000:
   result=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(path)],capture_output=True)
   assert result.returncode==0
  assert sha(path)==before
  preserved[path.name]={'path':path.relative_to(ROOT).as_posix(),'sha256':before,'logical_size':path.stat().st_size}
with log.open('x',encoding='utf-8') as out:
 process=subprocess.Popen(cmd,cwd=ROOT,stdout=out,stderr=subprocess.STDOUT)
 while process.poll() is None:
  compact_closed();time.sleep(1)
 compact_closed()
with record.open('x',encoding='utf-8') as out:out.write(json.dumps({'command':cmd,'exit_code':process.returncode,'log_sha256':sha(log)},indent=2)+'\n')
with (E/'regression-capture-compression-preservation.json').open('x',encoding='utf-8') as out:out.write(json.dumps({'files':list(preserved.values()),'file_count':len(preserved),'scope28_completed':False},indent=2)+'\n')
print(log.read_text(encoding='utf-8'));sys.exit(process.returncode)
