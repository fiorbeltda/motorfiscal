"""Capture one third sale at a time and compress immutable bytes before the next case."""
import json,hashlib,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];E=ROOT/'storage/app/phase4b-realization-third-sale-integrated-draft-20261008-28';S=ROOT/'resources/fiscal/scenarios/tvm-third-sale-integrated-2026.10.08-28-draft38'
records=[]
for fixture in sorted(S.glob('*-current.json')):
 case=fixture.name.removesuffix('-current.json');log=E/(case+'-capture.log');assert not log.exists()
 cmd=['docker','compose','exec','-T','-e','TVM_THIRD_PERIOD_SEPTEMBER_CAPTURE=1','app','php','-d','memory_limit=512M','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesThirdPeriodSeptemberTest.php','--compact','--filter',case]
 r=subprocess.run(cmd,cwd=ROOT,capture_output=True,encoding='utf-8');log.write_text(r.stdout+r.stderr);assert r.returncode==0,r.stdout+r.stderr
 record={'case':case,'command':cmd,'exit_code':r.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest(),'files':[]}
 for suffix in ['financial','month']:
  p=S/(case+'-'+suffix+'.json');before=hashlib.sha256(p.read_bytes()).hexdigest()
  if p.stat().st_size>1000000:
   c=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert c.returncode==0
  after=hashlib.sha256(p.read_bytes()).hexdigest();assert before==after;record['files'].append({'path':p.relative_to(ROOT).as_posix(),'sha256':after})
 records.append(record);print(case,flush=True)
p=E/'capture-preservation.json';assert not p.exists();p.write_text(json.dumps(records,indent=2))
