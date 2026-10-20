"""Capture native shared-asset fixtures and preserve the directed test command output."""
import subprocess,json,hashlib,sys,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];E=ROOT/'storage/app/phase4b-realization-shared-asset-current-draft-20261009-28';log=E/'directed-tests.log';assert not log.exists()
cmd=['docker','compose','exec','-T','-e','TVM_SHARED_ASSET_43_CAPTURE=1','app','php','-d','memory_limit=512M','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesSharedAssetHistoricalTest.php','--compact'];r=subprocess.run(cmd,cwd=ROOT,capture_output=True,encoding='utf-8');log.write_text(r.stdout+r.stderr,encoding='utf-8');v={'command':cmd,'exit_code':r.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()};m=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',r.stdout+r.stderr))
if m:v.update(tests=int(m[1]),assertions=int(m[2]))
(E/'directed-tests.json').write_text(json.dumps(v,indent=2)+'\n',encoding='utf-8');print(r.stdout+r.stderr);sys.exit(r.returncode)
