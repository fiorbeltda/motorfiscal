"""Record history-replay progress without declaring the integral realization scope accepted."""
import copy,hashlib,json,re,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];E=ROOT/'storage/app/phase4b-realization-history-draft-20261006-28'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['vendor/bin/pest','--compact'],
 'component-tests':['docker','compose','exec','-T','-e','TVM_HISTORY_CAPTURE=1','app','vendor/bin/pest',
  'tests/Fiscal/Unit/FinancialSecuritiesRecognitionHistoryTest.php','tests/Fiscal/Unit/FinancialSecuritiesRealizationTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],
 'pint':docker+['vendor/bin/pint','--test'],
 'regression27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py']}
name=sys.argv[1]
if name=='pint-final':commands[name]=commands['pint']
if name in commands:
 log=E/(name+'.log');record=E/(name+'.json');assert not log.exists() and not record.exists(),'Refusing to overwrite evidence.'
 p=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8')
 log.write_text(p.stdout+p.stderr,encoding='utf-8');clean=re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr)
 summary={'command':commands[name],'exit_code':p.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()}
 m=re.search(r'(\d+) passed.*?\((\d+) assertions\)',clean)
 if m:summary.update(tests=int(m[1]),assertions=int(m[2]))
 save(record,summary);print(json.dumps(summary));sys.exit(p.returncode)
if name=='replays':
 results=[]
 for stem in ['private-history','public-history','current-realization-history']:
  source=E/(stem+'-snapshot.json')
  p=subprocess.run(docker+['php','scripts/replay_phase4b_tvm_history_draft.php',source.relative_to(ROOT).as_posix()],cwd=ROOT,capture_output=True,encoding='utf-8')
  assert p.returncode==0,p.stdout+p.stderr
  result=json.loads(p.stdout);assert result['replay_matches'] and not result['scope28_completed'] and not result['production_ready']
  save(E/(stem+'-replay.json'),result);results.append({'snapshot':source.name,'result_hash':result['result_hash']})
 base=read(E/'current-realization-history-snapshot.json')
 mutations=[]
 bad=copy.deepcopy(base);bad['input']['position']['period']='2026-08';mutations.append(('unsealed-input',bad,'Draft snapshot input hash mismatch.'))
 bad=copy.deepcopy(base);bad['rule']['history_policy']['permitted_bundle_manifest_hashes']=[]
 bad['input_hash']=digest({k:v for k,v in bad.items() if k not in ['result','input_hash']});mutations.append(('resealed-policy',bad,'Captured realization policy differs from the frozen draft contract.'))
 bad=copy.deepcopy(base);bad['input']['recognition_history']['fiscal_sources'][0]['snapshot']['result']['decision']={'forged':True}
 bad['input_hash']=digest({k:v for k,v in bad.items() if k not in ['result','input_hash']});mutations.append(('resealed-fiscal-result',bad,None))
 for stem,bad,error in mutations:
  source=E/('tampered-'+stem+'-snapshot.json');save(source,bad)
  p=subprocess.run(docker+['php','scripts/replay_phase4b_tvm_history_draft.php',source.relative_to(ROOT).as_posix()],cwd=ROOT,capture_output=True,encoding='utf-8')
  assert p.returncode==1,p.stdout+p.stderr
  result=json.loads(p.stdout)
  if error:assert result['error']==error,result
  else:assert not result['replay_matches'] and result['result']['reason']=='RTC_TVM_HISTORY_FISCAL_SNAPSHOT_REPLAY_MISMATCH'
  save(E/('tampered-'+stem+'-replay.json'),result)
 save(E/'replays.json',{'valid':True,'replays':results,'guards':len(mutations)});print('Three history/current snapshots replayed;three tampering guards passed.');sys.exit(0)
assert name=='report'
checks={n:read(E/((n if n!='pint' else 'pint-final')+'.json')) for n in commands};assert all(v['exit_code']==0 for v in checks.values())
replays=read(E/'replays.json');assert replays['valid'] and len(replays['replays'])==3 and replays['guards']==3
baseline=read(ROOT/'storage/app/phase4b-variable-yield-20261006-27/historical-before.json')
changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
report={'status':'DRAFT_HISTORY_LINK_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'quality':checks,'replays':replays,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,
 'official_prior_daily_rates':21,'prior_memory_calendar_days':31,'history_policy_revision':'v2_with_actual_august_observation_time',
 'current_disposal':{'prior_return_released':'40.00','deferred_fair_value_released':'80.00','new_accounting_gain':'80.00',
  'ordinary_current_component_reconciled':'160.00','prior_assessed_return_remaining':'60.00'},
 'public_prior_split':{'assessed':'98.17','excluded_art195':'41.83','removed_pis_cofins':'10.00'},
 'historical_fiscal_snapshot_replay_performed':True,'fiscal_base_integration_performed':False,'xml_projection_performed':False,'production_ready':False,
 'pending':['Complete the remaining stock/history/negative/fund/multiple-title paths',
 'Qualify current deductions and integrate fiscal origins','Prove accounting bridges and D1101/D2101 projections',
 'Finish integral scope28 bundle corpus document replays and acceptance audit']}
save(E/'progress-report.json',report);print(json.dumps({'status':report['status'],'quality':checks,'historical_checked':len(baseline),'changed':changed}))
