"""Verify current TVM integration progress; preserve prior evidence and integral-scope limits."""
import copy,json,hashlib,re,subprocess,sys
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];E=ROOT/'storage/app/phase4b-realization-base-draft-20261006-28'
F=ROOT/'resources/fiscal';V='2026.10.06-28-draft';S=F/f'scenarios/tvm-base-{V}';B=f'bundles/consumption-phase4b-{V}.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['vendor/bin/pest','--compact'],
 'component-tests':['docker','compose','exec','-T','-e','TVM_CURRENT_CAPTURE=1','app','vendor/bin/pest','tests/Fiscal/Unit/FinancialSecuritiesRealizationBaseTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],
 'pint':docker+['vendor/bin/pint','--test'],
 'regression27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],
 'history-replays':['python','scripts/verify_phase4b_tvm_history_draft.py','replays']}
name=sys.argv[1]
if name in commands:
 log=E/(name+'.log');record=E/(name+'.json');assert not log.exists() and not record.exists(),'Refusing to overwrite evidence.'
 p=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8');log.write_text(p.stdout+p.stderr,encoding='utf-8')
 summary={'command':commands[name],'exit_code':p.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()}
 m=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr))
 if m:summary.update(tests=int(m[1]),assertions=int(m[2]))
 save(record,summary);print(json.dumps(summary));sys.exit(p.returncode)
def run(args):
 p=subprocess.run(docker+['php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==0,p.stdout+p.stderr
 return json.loads(p.stdout)
if name=='corpus':
 cases=[]
 for case,base,revenue,deductions in [('mixed-realization','1010.00','310.00','0.00'),('first-gain','900.00','200.00','0.00'),
  ('first-loss','600.00','0.00','100.00'),('first-offsetting','700.00','200.00','200.00')]:
  snapshot=E/(case+'-snapshot.json');args=['fiscal:rtc:run',(S/(case+'-financial.json')).relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
  result=run(args);assert result['status']=='ACTION_REQUIRED' and result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
  stages={r['stage']:r for r in result['trace']};financial=stages['FINANCIAL_OPERATION_AND_BASE']['base'];tax=stages['FINANCIAL_GROSS_DEBITS']
  assert financial['status']=='RESOLVED' and financial['net_base']==base and tax['status']=='RESOLVED' and tax['base']==base
  binding=financial['realization_bindings']['tvm1'];assert binding['revenue']==revenue and binding['deductions']==deductions and not binding['production_ready']
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[key])==(Decimal(base)*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  replay=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]);assert replay['replay_matches']
  save(E/(case+'-replay.json'),replay);cases.append({'case':case,'net_base':base,'component_revenue':revenue,'component_deductions':deductions,'decision_hash':result['decision_hash'],'replay_matches':True})
 save(E/'corpus.json',{'cases':cases,'current_base_integration_performed':True,'xml_projection_performed':False,'scope28_completed':False,'production_ready':False})
 print(json.dumps(cases));sys.exit(0)
if name=='guards':
 base=read(E/'mixed-realization-snapshot.json');results=[]
 for case in ['changed-ledger','changed-policy','changed-result']:
  snapshot=copy.deepcopy(base)
  if case=='changed-ledger':snapshot['input']['facts']['rtc.financial_ledger'][0]['entries'][0]['amount']='1001.00'
  elif case=='changed-policy':snapshot['knowledge']['documents']['rtc_code_catalog']['financial_operation']['base']['securities_realization']['status']='INACTIVE'
  else:snapshot['result']['decision_hash']='0'*64
  path=E/('tampered-'+case+'-snapshot.json');save(path,snapshot)
  p=subprocess.run(docker+['php','artisan','fiscal:rtc:replay',path.relative_to(ROOT).as_posix()],cwd=ROOT,capture_output=True,encoding='utf-8')
  assert p.returncode==1,p.stdout+p.stderr
  result=json.loads(p.stdout);assert not result.get('replay_matches',False)
  save(E/('tampered-'+case+'-replay.json'),result);results.append({'case':case,'rejected':True})
 save(E/'guards.json',{'guards':results});print(json.dumps(results));sys.exit(0)
if name=='gate':
 p=subprocess.run(docker+['php','artisan','fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json'],cwd=ROOT,capture_output=True,encoding='utf-8')
 assert p.returncode==1,p.stdout+p.stderr
 result=json.loads(p.stdout);assert not result['ready'] and result['official_source_hashes_valid']==48 and 'error' not in result,result
 save(E/'production-assessment.json',result);print(json.dumps({'ready':result['ready'],'official_sources':48}));sys.exit(0)
assert name=='report'
quality={n:read(E/(n+'.json')) for n in commands};assert all(v['exit_code']==0 for v in quality.values())
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
corpus=read(E/'corpus.json');manifest=read(F/B)
gate=read(E/'production-assessment.json');guards=read(E/'guards.json');assert not gate['ready'] and len(guards['guards'])==3
for source in manifest['official_sources']:
 assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256'],source['id']
report={'status':'DRAFT_CURRENT_TVM_BASE_INTEGRATION_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'development_bundle':V,'quality':quality,'corpus':corpus,'guards':guards,'official_sources_verified':len(manifest['official_sources']),
 'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'production_ready':False,
 'pending':['Prove accounting bridges and D1101/D2101 projection for qualified components',
 'Integrate public-offer realizations with produced-return cap and fair-value subaccounts',
 'Complete remaining negative historical/fund/multiple-title/rounding cases and integral scope28 acceptance']}
save(E/'progress-report.json',report);print(json.dumps(report))
