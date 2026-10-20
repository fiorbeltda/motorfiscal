"""Verify general financial arithmetic and reproduce the integrated yield predecessor."""
import json,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-variable-yield-20261006-27'
V='2026.10.06-27'
for source in sorted((ROOT/f'resources/fiscal/scenarios/consumption-phase4b-{V}').glob('financial-dere-*.json')):
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan','fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest=bundles/consumption-phase4b-{V}.json'],cwd=ROOT,capture_output=True,encoding='utf-8',check=True)
 v=json.loads(p.stdout);stages={s['stage']:s for s in v['trace']}
 assert v['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
 assert stages['FINANCIAL_OPERATION_AND_BASE']['base']['net_base']=='800.00'
 assert stages['FINANCIAL_GROSS_DEBITS']['ibs_nominal']=='0.80'
 assert stages['FINANCIAL_GROSS_DEBITS']['cbs_nominal']=='7.20'
 target=ROOT/f'resources/fiscal/expected/consumption-phase4b-{V}/{source.name}'
 expected={k:v[k] for k in ['status','reason_codes','missing_facts','decision_hash']}
 if target.exists():assert json.loads(target.read_text(encoding='utf-8'))==expected
 else:
  target.parent.mkdir(parents=True,exist_ok=True);target.write_text(json.dumps(expected,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
for script,args,log in [
 ('verify_phase4b_financial_credit_cut.py',[V],'general-regressions.log'),
 ('verify_phase4b_dere_daily_memory_cut.py',[],'predecessor-memory-regression.log'),
 ('verify_phase4b_dere_yield_cut.py',[],'predecessor-yield-regression.log')]:
 p=subprocess.run(['python',f'scripts/{script}',*args],cwd=ROOT,capture_output=True,encoding='utf-8')
 out=E/log
 if out.exists():assert out.read_text(encoding='utf-8')==p.stdout+p.stderr
 else:out.write_text(p.stdout+p.stderr,encoding='utf-8')
 assert p.returncode==0,p.stdout+p.stderr
 print(log,'PASS',flush=True)
