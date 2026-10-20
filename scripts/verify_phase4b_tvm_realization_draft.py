"""Capture quality checks and immutable historical evidence for unfinished scope28."""
import hashlib,json,re,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-realization-draft-20261006-28'
E.mkdir(parents=True,exist_ok=True)
def save(path,value):
 if path.exists():assert json.loads(path.read_text(encoding='utf-8'))==value,path
 else:path.write_text(json.dumps(value,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
def h(path):return hashlib.sha256(path.read_bytes()).hexdigest()
name=sys.argv[1]
commands={'tests':['vendor/bin/pest','--compact'],'phpstan':['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],
 'pint':['vendor/bin/pint','--test']}
if name in commands:
 log=E/(name+'.log');record=E/(name+'.json')
 assert not log.exists() and not record.exists(),'Existing evidence cannot be overwritten.'
 p=subprocess.run(['docker','compose','exec','-T','app',*commands[name]],cwd=ROOT,capture_output=True,encoding='utf-8')
 log.write_text(p.stdout+p.stderr,encoding='utf-8')
 clean=re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr)
 summary={'command':commands[name],'exit_code':p.returncode,'log_sha256':h(log)}
 if name=='tests':
  m=re.search(r'(\d+) passed.*?\((\d+) assertions\)',clean)
  if m:summary.update(tests=int(m[1]),assertions=int(m[2]))
 save(record,summary)
 print(json.dumps(summary));sys.exit(p.returncode)
assert name=='report'
checks={n:json.loads((E/(n+'.json')).read_text(encoding='utf-8')) for n in commands}
assert all(v['exit_code']==0 for v in checks.values()),checks
baseline=json.loads((ROOT/'storage/app/phase4b-variable-yield-20261006-27/historical-before.json').read_text(encoding='utf-8'))
changed=[p for p,d in baseline.items() if not (ROOT/p).is_file() or h(ROOT/p)!=d]
assert not changed,changed
fixtures={p.relative_to(ROOT).as_posix():h(p) for p in sorted((ROOT/'resources/fiscal/scenarios/tvm-realization-draft-2026.10.06-28').glob('*.json'))}
assert len(fixtures)==12
report={'status':'DRAFT_RECONCILIATION_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'quality':checks,'independent_numeric_oracle':'PYTHON_DECIMAL_PRECISION_100_HALF_EVEN_8_2',
 'component_tests':29,'component_assertions':323,'synthetic_fixtures':fixtures,
 'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,
 'fiscal_base_integration_performed':False,'historical_fiscal_snapshot_replay_performed':False,
 'xml_projection_performed':False,'production_ready':False,
 'pending':['Replay and bind actual prior fiscal snapshots','Qualify loss deductions and legal conditions',
 'Integrate component origins and accounting bridges in D1101/D2101','Resolve proved gross rounding residues',
 'Finish scope28 bundle corpus snapshots XML regressions and acceptance audit']}
save(E/'progress-report.json',report)
print(json.dumps({'status':report['status'],'quality':checks,'historical_checked':len(baseline),'changed':changed}))
