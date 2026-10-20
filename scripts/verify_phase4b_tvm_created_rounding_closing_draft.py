"""Audit third-period opening transport without claiming third-period tax acceptance."""
import hashlib,json,re,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft34';E=ROOT/'storage/app/phase4b-realization-created-rounding-closing-draft-20261008-28';S=F/('scenarios/tvm-created-rounding-closing-'+V)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as stream:stream.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
docker=['docker','compose','exec','-T','app'];commands={
 'opening-tests':['docker','compose','exec','-T','-e','TVM_CREATED_ROUNDING_CLOSING_CAPTURE=1','app','php','-d','memory_limit=512M','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesCreatedRoundingClosingTest.php','--compact'],
 'tests':docker+['php','-d','memory_limit=512M','vendor/bin/pest','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],'pint':docker+['vendor/bin/pint','--test'],
 'reproduce-bundle':['python','scripts/build_phase4b_tvm_created_rounding_closing_draft.py'],
 'regression33':['python','scripts/verify_phase4b_tvm_historical_sale_rounding_draft.py','corpus'],
 'regression32':['python','scripts/verify_phase4b_tvm_public_explicit_closing_draft.py','corpus'],
 'regression30':['python','scripts/verify_phase4b_tvm_public_gross_rounding_draft.py','corpus'],
 'regression29':['python','scripts/verify_phase4b_tvm_public_signed_release_draft.py','corpus'],
 'regression27':['python','scripts/verify_phase4b_tvm_public_release_only_draft.py','corpus'],
 'regression25':['python','scripts/verify_phase4b_tvm_public_other_movements_draft.py','corpus'],
 'regression23':['python','scripts/verify_phase4b_tvm_public_intermediate_sale_draft.py','corpus'],
 'regression21':['python','scripts/verify_phase4b_tvm_public_mixed_history_draft.py','corpus'],
 'regression18':['python','scripts/verify_phase4b_tvm_public_negative_yield_draft.py','corpus'],
 'candidate27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],
 'candidate26':['python','scripts/verify_phase4b_dere_daily_memory_cut.py'],
 'candidate25':['python','scripts/verify_phase4b_dere_yield_cut.py']}
name=sys.argv[1]
if name in commands:
 log=E/(name+'.log');record=E/(name+'.json');assert not log.exists() and not record.exists(),'Refusing to overwrite evidence.'
 p=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8')
 with log.open('x',encoding='utf-8') as stream:stream.write(p.stdout+p.stderr)
 result={'command':commands[name],'exit_code':p.returncode,'log_sha256':sha(log)};match=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr))
 if match:result.update(tests=int(match[1]),assertions=int(match[2]))
 save(record,result);print(json.dumps(result));sys.exit(p.returncode)
if name=='gate':
 p=subprocess.run(docker+['php','artisan','fiscal:rtc:production-assess','--bundle-manifest=bundles/consumption-phase4b-'+V+'.json','--controls=resources/fiscal/production/phase4b-controls-'+V+'.json'],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==1,p.stdout+p.stderr
 result=json.loads(p.stdout);assert not result['ready'] and result['official_source_hashes_valid']==49;save(E/'production-assessment.json',result);print(json.dumps({'production_ready':False,'official_sources':49}));sys.exit(0)
assert name=='report'
quality={key:read(E/(key+'.json')) for key in commands};assert all(record['exit_code']==0 for record in quality.values())
for key,record in quality.items():assert record['command']==commands[key] and sha(E/(key+'.log'))==record['log_sha256']
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
cases=[]
for p in sorted(S.glob('*-opening.json')):
 f=read(p);case=p.stem.removesuffix('-opening');result=read(E/(case+'-opening-result.json'));assert result['status']=='RESOLVED' and result['public_created_rounding_closing_replayed'] and not result['production_ready']
 for key,want in f['expected'].items():assert result[key]==want,(case,key)
 assert sha(ROOT/f['source_path'])==f['source_sha256'];cases.append({'case':case,'source_snapshot':f['source_path'],'source_sha256':f['source_sha256'],'opening_result_sha256':sha(E/(case+'-opening-result.json')),'monthly_tax_accepted':False})
assert len(cases)==10
research=read(E/'research.json');assert sha(ROOT/research['reference_research'])==research['reference_sha256'] and not research['normative_homologation_claimed'];gate=read(E/'production-assessment.json');assert not gate['ready']
for source in read(F/('bundles/consumption-phase4b-'+V+'.json'))['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
save(E/'progress-report.json',{'status':'DRAFT_CREATED_ROUNDING_THIRD_PERIOD_OPENING_PROGRESS_ONLY','development_bundle':V,'latest_locally_accepted_candidate':'2026.10.06-27','quality':quality,'opening_cases':cases,'research':research,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':49,'third_period_monthly_tax_accepted':False,'scope28_completed':False,'production_ready':False,'pending':['Third-period disposal with both covered and uncovered rounding','Full three-month chain using completed official monthly series','Broader prior histories and funds','Integral scope28 and phase4b acceptance']});print(json.dumps({'historical_artifacts_checked':len(baseline),'third_period_monthly_tax_accepted':False,'production_ready':False}))
