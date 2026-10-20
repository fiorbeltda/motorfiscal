"""Verify the real CLI multi-title path, shared book, XSD, replay and preserved predecessors."""
import copy,hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from pathlib import Path
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.07-28-draft8'
E=ROOT/'storage/app/phase4b-realization-negative-history-draft-20261007-28';S=F/f'scenarios/tvm-negative-history-{V}'
B=f'bundles/consumption-phase4b-{V}.json';docker=['docker','compose','exec','-T','app']
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
commands={'tests':docker+['vendor/bin/pest','--compact'],
 'negative-history-tests':['docker','compose','exec','-T','-e','TVM_NEGATIVE_CAPTURE=1','app','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesNegativeHistoryTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],'pint':docker+['vendor/bin/pint','--test'],
 'regression27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],
 'multiple-title-replays':['python','scripts/verify_phase4b_tvm_multiple_titles_draft.py','corpus'],
 'fair-replays':['python','scripts/verify_phase4b_tvm_fair_value_draft.py','corpus'],
 'other-release-replays':['python','scripts/verify_phase4b_tvm_other_releases_draft.py','corpus'],
 'book-replays':['python','scripts/verify_phase4b_tvm_book_draft.py','corpus'],
 'base-replays':['python','scripts/verify_phase4b_tvm_base_draft.py','corpus'],
 'history-replays':['python','scripts/verify_phase4b_tvm_history_draft.py','replays']}
name=sys.argv[1]
if name in commands:
 log=E/(name+'.log');assert not log.exists(),'Refusing to overwrite evidence'
 p=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8');log.write_text(p.stdout+p.stderr,encoding='utf-8')
 result={'command':commands[name],'exit_code':p.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()}
 match=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr))
 if match:result.update(tests=int(match[1]),assertions=int(match[2]))
 save(E/(name+'.json'),result);print(json.dumps(result));sys.exit(p.returncode)
def run(args,code=0):
 p=subprocess.run(docker+['php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stdout+p.stderr
 return json.loads(p.stdout)
snapshot=E/'negative-history-snapshot.json'
if name=='corpus':
 prior=E/'prior-negative-snapshot.json';previous=run(['fiscal:rtc:replay',prior.relative_to(ROOT).as_posix()]);assert previous['replay_matches'];save(E/'prior-negative-replay.json',previous)
 args=['fiscal:rtc:run',(S/'negative-history-financial.json').relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
 if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
 decision=run(args);assert decision['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
 base=next(row['base'] for row in decision['trace'] if row['stage']=='FINANCIAL_OPERATION_AND_BASE')
 assert base['net_base']=='710.00' and len(base['realization_bindings'])==1
 assert base['realization_bindings']['tvm1']['revenue']=='150.00' and base['realization_bindings']['tvm1']['deductions']=='140.00'
 assert base['realization_bindings']['tvm1']['analysis']['replayed_fiscal_history']['opening_fiscal_components']['deferred_fair_value']=='-200.00000000'
 assert base['realization_bindings']['tvm1']['analysis']['recognition_inventory']['closing']['deferred_fair_value_realized']=='-120'
 tax=next(row for row in decision['trace'] if row['stage']=='FINANCIAL_GROSS_DEBITS');assert tax['ibs_nominal']=='0.71' and tax['cbs_nominal']=='6.39'
 replay=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/'negative-history-replay.json',replay)
 report=E/'negative-history-monthly-report.json'
 args=['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),(S/'negative-history-month.json').relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
 if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/'negative-history-candidate.xml').relative_to(ROOT).as_posix()]
 projected=run(args);stored=read(report)
 for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[key]==stored[key]
 binding=stored['projection']['securities_realization_binding'];assert binding['status']=='RESOLVED' and len(binding['parents'])==1 and not binding['production_ready']
 assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199'} and not stored['production_ready']
 for event,path in stored['event_outputs'].items():
  raw=(ROOT/path).read_bytes();validation=stored['event_validations'][event]
  assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
  assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest() and validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
  if event=='D1101':
   xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]};accounts={r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)}
   for account in read(S/'negative-history-month.json')['book']['accounts']:
    for field in ['vSaldoInic','vMovDebt','vMovCred','vAjusteDebt','vAjusteCred','vSaldoFinal','vApur']:
     node=accounts[account['cCta']].find('d:'+field,ns);assert (node.text if node is not None else '0.00')==account.get(field,'0.00')
   assert accounts['FV000'].find('d:vMovCred',ns).text=='80.00' and accounts['FV000'].find('d:vAjusteCred',ns).text=='80.00' and accounts['FV000'].find('d:vApur',ns).text=='80.00'
   assert accounts['fvloss000'].find('d:vMovDebt',ns).text=='80.00' and accounts['fvloss000'].find('d:vAjusteDebt',ns).text=='80.00' and accounts['fvloss000'].find('d:vApur',ns).text=='0.00'
 save(E/'corpus.json',{'cases':1,'parents':1,'prior_deferred':'-200.00','released_deferred_loss':'80.00','closing_deferred':'-120.00','net_base':'710.00','xml_bodies':5,'replay_matches':True,'production_ready':False,'scope28_completed':False});print('Negative historical deferral, current realization, five XSD bodies and exact replay passed.');sys.exit(0)
if name=='prior-sign-guards':
 cases=[]
 for case in ['missing-sign-proof','changed-sign','raw-signed-metadata']:
  scenario=read(S/'prior-negative-financial.json');entry=scenario['facts']['rtc.financial_ledger'][0]['entries'][1]
  if case=='missing-sign-proof':entry.pop('fair_value_sign_evidence')
  elif case=='changed-sign':entry['fair_value_nature']='C'
  else:entry['deferred_signed_amount']='-999.00'
  path=E/(case+'-prior-guard-financial.json');save(path,scenario)
  result=run(['fiscal:rtc:run',path.relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-2026.10.07-28-draft7.json'],1 if case=='raw-signed-metadata' else 0)
  reason='RTC_FINANCIAL_BASE_'+('RESERVED_DERIVED_ENTRY_METADATA' if case=='raw-signed-metadata' else 'INDEPENDENT_FAIR_VALUE_SIGN_UNPROVEN')
  assert reason in result['reason_codes'],result;save(E/(case+'-prior-guard-result.json'),result);cases.append({'case':case,'reason':reason,'rejected':True})
 save(E/'prior-sign-guards.json',{'guards':cases});print(json.dumps(cases));sys.exit(0)
if name=='loss-guard':
 scenario=read(S/'negative-history-financial.json');ledger=scenario['facts']['rtc.financial_ledger'][0]
 ledger['entries'][4]['securities_realization']['deduction_evidence']=[]
 unsigned=copy.deepcopy(ledger)
 for entry in unsigned['entries']:
  if 'securities_realization' in entry:entry['securities_realization'].pop('qualification_evidence',None)
 for entry in ledger['entries']:
  if 'securities_realization' in entry:
   proof=entry['securities_realization']['qualification_evidence'];proof['value']['ledger_input_hash']=digest(unsigned);proof.pop('source_hash');proof['source_hash']=digest(proof)
 path=E/'missing-title-loss-proof-financial.json';save(path,scenario)
 result=run(['fiscal:rtc:run',path.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}'])
 reason='RTC_TVM_CURRENT_LOSS_AUTHORIZATION_UNPROVEN';assert reason in result['reason_codes'],result
 save(E/'missing-title-loss-proof-result.json',result);save(E/'loss-guard.json',{'reason':reason,'rejected':True,'prior_deferral_does_not_qualify_current_loss':True});print(reason);sys.exit(0)
if name=='guards':
 results=[]
 for case,reason in [('coverage','PARENT_BOOK_COVERAGE_INCOMPLETE'),('mapping','COMPONENT_PGCC_CODE_OR_NATURE_CONFLICT'),('cash-gross','JOURNAL_GROSS_MOVEMENTS_OR_COMPONENT_LINKS_CONFLICT')]:
  month=read(S/'negative-history-month.json')
  if case=='coverage':month['securities_realization_book'].pop()
  elif case=='mapping':
   row=month['securities_realization_book'][0];row['component_accounts'][next(iter(row['component_accounts']))]='fvnew000'
  else:
   next(row for row in month['book']['accounts'] if row['cCta']=='cash000')['vMovDebt']='3899.00'
   for row in month['securities_realization_book']:
    proof=row['independent_evidence'];proof['value']['book_content_hash']=digest(month['book']);proof.pop('source_hash');proof['source_hash']=digest(proof)
  path=E/(case+'-guard-month.json');save(path,month)
  result=run(['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),path.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}'],1)
  assert result['reason']=='RTC_DERE_TVM_BOOK_'+reason,result
  save(E/(case+'-guard-result.json'),result);results.append({'case':case,'reason':result['reason'],'rejected':True})
 save(E/'guards.json',{'guards':results});print(json.dumps(results));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json'],1)
 assert not result['ready'] and result['official_source_hashes_valid']==48
 save(E/'production-assessment.json',result);print('Production blocked; 48 official sources verified.');sys.exit(0)
assert name=='report'
quality={n:read(E/(n+'.json')) for n in commands};assert all(row['exit_code']==0 for row in quality.values())
baseline=read(E/'historical-before.json');changed=[path for path,hash in baseline.items() if not (ROOT/path).is_file() or hashlib.sha256((ROOT/path).read_bytes()).hexdigest()!=hash];assert not changed,changed
bundle=read(F/B)
for source in bundle['official_sources']:assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256']
corpus=read(E/'corpus.json');guards=read(E/'guards.json');loss_guard=read(E/'loss-guard.json');prior_guards=read(E/'prior-sign-guards.json');gate=read(E/'production-assessment.json');assert corpus['parents']==1 and corpus['xml_bodies']==5 and len(guards['guards'])==3 and len(prior_guards['guards'])==3 and loss_guard['rejected'] and not gate['ready']
report={'status':'DRAFT_NEGATIVE_FAIR_VALUE_HISTORY_REALIZATION_PROGRESS_ONLY','development_bundle':V,'scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27','quality':quality,'corpus':corpus,'guards':guards,'loss_guard':loss_guard,'prior_sign_guards':prior_guards,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':48,'production_ready':False,'pending':['Public D2101 realization','Negative assessed/public historical returns and wider signed chains','Funds','Multiple titles with historical chains and shared result accounts','Remaining releases and rounding','Integral scope28 acceptance']}
save(E/'progress-report.json',report);print(json.dumps(report))
