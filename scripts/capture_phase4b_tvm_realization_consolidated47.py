"""Capture native consolidated cases while preserving directed test output."""
import subprocess,json,hashlib,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];E=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28';p=E/'directed-tests.log';assert not p.exists();cmd=['docker','compose','exec','-T','-e','TVM_REALIZATION_CONSOLIDATED_CAPTURE=1','app','php','-d','memory_limit=512M','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesConsolidation47KnowledgeTest.php','--compact'];r=subprocess.run(cmd,cwd=ROOT,capture_output=True,encoding='utf-8');p.write_text(r.stdout+r.stderr,encoding='utf-8');v={'command':cmd,'exit_code':r.returncode,'log_sha256':hashlib.sha256(p.read_bytes()).hexdigest()};m=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',r.stdout+r.stderr))
if m:v.update(tests=int(m[1]),assertions=int(m[2]))
(E/'directed-tests.json').write_text(json.dumps(v,indent=2)+'\n',encoding='utf-8');print(r.stdout+r.stderr);sys.exit(r.returncode)
