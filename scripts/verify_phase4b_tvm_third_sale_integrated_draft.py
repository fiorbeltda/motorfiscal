"""Verify the September integration and the complete synthetic three-period rounding corpus."""
import gc,hashlib,json,re,subprocess,sys,ctypes,shutil,xml.etree.ElementTree as ET
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft38';E=ROOT/'storage/app/phase4b-realization-third-sale-integrated-draft-20261008-28';S=F/('scenarios/tvm-third-sale-integrated-'+V);B='bundles/consumption-phase4b-'+V+'.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
def compress(p):
 if p.stat().st_size>1000000:
  before=sha(p)
  if ctypes.windll.kernel32.GetFileAttributesW(str(p))&0x800:
   if shutil.disk_usage(ROOT).free<=p.stat().st_size+15000000:
    assert sha(p)==before;return
   uncompress=subprocess.run(['compact.exe','/U','/F','/I','/Q',str(p)],capture_output=True);assert uncompress.returncode==0
  result=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert result.returncode==0 and sha(p)==before,p
def run(args,code=0):
 command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args]
 if args[0]=='fiscal:rtc:run':
  capture=[a.split('=',1)[1] for a in args if a.startswith('--snapshot=')]
  command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M','scripts/run_phase4b_third_sale_compact.php',args[1],*capture]
 p=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stdout[:2500]+p.stderr;return json.loads(p.stdout)
name=sys.argv[1]
docker=['docker','compose','exec','-T','app'];commands={
 'reproduce-bundle':['python','scripts/build_phase4b_tvm_third_sale_integrated_draft.py'],
 'tests':docker+['php','-d','memory_limit=512M','vendor/bin/pest','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],
 'pint':docker+['vendor/bin/pint','--test'],
 'regression36':['python','scripts/verify_phase4b_tvm_three_period_august_draft.py','corpus'],
 'regression35':['python','scripts/verify_phase4b_tvm_three_period_rounding_draft.py','corpus'],
 'regression34':['docker','compose','exec','-T','-e','TVM_CREATED_ROUNDING_CLOSING_CAPTURE=1','app','php','-d','memory_limit=512M','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesCreatedRoundingClosingTest.php','--compact'],
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
if name in commands:
 log=E/(name+'.log');record=E/(name+'.json');assert not log.exists() and not record.exists(),'Refusing to overwrite evidence.'
 p=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8')
 with log.open('x',encoding='utf-8') as out:out.write(p.stdout+p.stderr)
 result={'command':commands[name],'exit_code':p.returncode,'log_sha256':sha(log)};match=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr))
 if match:result.update(tests=int(match[1]),assertions=int(match[2]))
 save(record,result);print(json.dumps(result));sys.exit(p.returncode)
if name=='corpus':
 cases=[];xml_count=0
 for case in sorted(p.name.removesuffix('-current.json') for p in S.glob('*-current.json')):
  stem=case;fixture=read(S/(stem+'-current.json'));snap=E/(stem+'-snapshot.json');args=['fiscal:rtc:run',(S/(stem+'-financial.json')).relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
  if not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
  result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];assert result['native_snapshot_sha256']==sha(snap);compress(snap);replay=run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()]);assert replay['replay_matches'] and result['decision_hash']==replay['original_hash'];save(E/(stem+'-replay.json'),replay)
  base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');entry=next(v for v in base['entries'] if v['id']=='yield1');q=entry['d2101_binding'];assert base['net_base']==fixture['expected']['base'] and q['taxable_amount']==fixture['expected']['auxiliary_base'];expected=fixture['independent_oracle'];actual=q['calculation']
  assert base['period_end']=='2026-09-30' and base['deductions_before_art188']=='300.00' and q['book_apur_amount']=='0.00'
  parent=base['realization_bindings']['tvm1'];assert parent['financial_entries']==[] and parent['deductions']=='0.00'
  analysis=parent['analysis'];sale=analysis['realizations'][0]
  assert analysis['replayed_fiscal_history']['public_created_rounding_closing_replayed']
  assert analysis['recognition_inventory']['closing_fiscal_components']==fixture['recognition_inventory']['closing_fiscal_components']
  for field,want in fixture['third_period_sale_rounding_oracle'].items():assert sale['book_release_rounding'][field]==want,(case,field)
  split=analysis['recognition_inventory']['rounding_inventory'];parts=sale['book_release_rounding']
  assert Decimal(sale['historical_carrying_rounding'])==Decimal(parts['fiscal_covered_rounding_consumed'])
  for origin in ['fiscal_covered_rounding','uncovered_created_rounding']:
   pool=split[origin];assert Decimal(pool['opening'])-Decimal(pool['consumed'])==Decimal(pool['closing'])
   assert Decimal(pool['consumed'])==Decimal(parts[origin+'_consumed'])
  assert sum(Decimal(split[k]['closing']) for k in ['fiscal_covered_rounding','uncovered_created_rounding'])+Decimal(split['closing_created'])==Decimal(split['closing_total'])
  assert Decimal(split['closing_created'])==-Decimal(parts['created_rounding_adjustment'])
  for edge in ['opening','closing']:
   for field,want in fixture['recognition_inventory'][edge].items():assert Decimal(analysis['recognition_inventory'][edge][field])==Decimal(want),(case,edge,field)
  tax=next(t for t in result['trace'] if t['stage']=='FINANCIAL_GROSS_DEBITS');assert tax['base']==base['net_base']
  for field,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[field])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  for field,want in expected.items():
   if field!='daily_memory':assert actual[field]==want,(case,field)
   else:
    assert len(actual[field])==len(want)
    for row,oracle in zip(actual[field],want):
     for key,value in oracle.items():assert row[key]==value,(case,row['date'],key)
  report=E/(stem+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snap.relative_to(ROOT).as_posix(),(S/(stem+'-month.json')).relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
  if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(stem+'-candidate.xml')).relative_to(ROOT).as_posix()]
  projected=run(args);stored=read(report);compress(report)
  for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[key]==stored[key],key
  assert stored['projection']['status']=='PROJECTED' and not stored['production_ready'];assert stored['projection']['debt_yield_projection']['total_vApur']==q['taxable_amount'];assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199','D2101'}
  for event,path in stored['event_outputs'].items():
   raw=(ROOT/path).read_bytes();validation=stored['event_validations'][event];assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest();assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1;xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]}
   assert validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
   if event=='D2101':
    titles=xml.findall('.//d:detTitulo',ns);assert len(titles)==1
    for field,value in q['xml_title'].items():assert (titles[0].get(field) if field in ['idTitulo','cCta'] else titles[0].find('d:'+field,ns).text)==value
   if event=='D1101':
    accounts={row.find('d:cCta',ns).text:row for row in xml.findall('.//d:infoConta',ns)}
    for account in read(S/(stem+'-month.json'))['book']['accounts']:
     for field in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:assert accounts[account['cCta']].find('d:'+field,ns).text==account[field]
   xml_count+=1
  cases.append({'case':case,'snapshot_sha256':sha(snap),'base':base['net_base'],'auxiliary_base':q['taxable_amount'],'closing_quantity':actual['closing_quantity'],'closing_book':actual['monthly']['vSaldoContFin'],'replay_matches':True,'monthly_projected':True})
  del result,replay,base,entry,q,parent,analysis,sale,expected,actual,fixture,projected,stored
  gc.collect()
 save(E/'september-corpus.json',{'cases':cases,'financial_replays':12,'xml_bodies':xml_count,'full_three_month_chain_accepted':False,'scope28_completed':False,'production_ready':False});print(json.dumps({'financial_replays':12,'xml_bodies':xml_count,'full_three_month_chain_accepted':False}));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess','--bundle-manifest='+B,'--controls=resources/fiscal/production/phase4b-controls-'+V+'.json'],1);assert not result['ready'] and result['official_source_hashes_valid']==49;save(E/'production-assessment.json',result);print(json.dumps({'official_sources':49,'production_ready':False}));sys.exit(0)
assert name=='report'
quality={key:read(E/(key+'.json')) for key in commands};assert all(r['exit_code']==0 for r in quality.values())
for key,r in quality.items():assert r['command']==commands[key] and sha(E/(key+'.log'))==r['log_sha256']
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
compactstyle=read(E/'compact-pint.json');assert compactstyle['exit_code']==0 and sha(E/'compact-pint.log')==compactstyle['log_sha256']
finalstyle=read(E/'final-pint.json');assert finalstyle['exit_code']==0 and sha(E/'final-pint.log')==finalstyle['log_sha256']
secondfailure=read(E/'second-capture-failure.json');assert sha(ROOT/secondfailure['preserved_path'])==secondfailure['sha256'] and not secondfailure['accepted'] and not secondfailure['json_complete']
chunkstyle=read(E/'chunked-capture-pint.json');assert chunkstyle['exit_code']==0 and sha(E/'chunked-capture-pint.log')==chunkstyle['log_sha256']
for item in read(E/'initial-evidence-preservation.json'):assert sha(ROOT/item['path'])==item['sha256'] and not item['accepted_validation']
for item in read(E/'final-implementation-hashes.json'):assert sha(ROOT/item['path'])==item['sha256']
corpus=read(E/'september-corpus.json');assert len(corpus['cases'])==12 and corpus['financial_replays']==12 and corpus['xml_bodies']==72
gate=read(E/'production-assessment.json');assert not gate['ready'] and gate['official_source_hashes_valid']==49
for source in read(F/B)['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
for name in ['compression-preservation.json','current-compression-preservation.json','extended-compression-preservation.json','additional-compression-preservation.json','non-json-compression-preservation.json','late-current-compression-preservation.json']:
 for item in read(E/name):assert sha(ROOT/item['path'])==item['sha256_before']==item['sha256_after']
for item in read(E/'capture-preservation.json'):
 assert item['exit_code']==0 and sha(E/(item['case']+'-capture.log'))==item['log_sha256']
 for f in item['files']:assert sha(ROOT/f['path'])==f['sha256']
guards=read(E/'history-guards.json');assert guards['exit_code']==0 and len(guards['cases'])==9 and sha(E/'history-guards.log')==guards['log_sha256']
origins=read(E/'origin-guards.json');assert origins['exit_code']==0 and len(origins['cases'])==2 and origins['positive_control_resolved'] and sha(E/'origin-guards.log')==origins['log_sha256']
directed=read(E/'september-tests.json');assert directed['exit_code']==0 and sha(E/'september-tests.log')==directed['log_sha256']
research=read(E/'research.json');assert sha(ROOT/research['reference_research'])==research['reference_sha256'] and sha(ROOT/research['current_lc214_path'])==research['current_lc214_sha256'] and not research['normative_homologation_claimed']
from dere_daily_memory_oracle import digest
augE=ROOT/'storage/app/phase4b-realization-three-period-august-draft-20261008-28';julE=ROOT/'storage/app/phase4b-realization-three-period-rounding-draft-20261008-28'
aug=read(augE/'august-corpus.json');jul=read(julE/'july-corpus.json');assert aug['financial_replays']==10 and aug['xml_bodies']==60 and jul['financial_replays']==4 and jul['xml_bodies']==24
chains=[]
for item in corpus['cases']:
 case=item['case'];f=read(S/(case+'-current.json'));sourcecase=case.rsplit('-',1)[0];augpath=augE/(sourcecase+'-snapshot.json');augsource=read(augpath)
 assert digest(f['recognition_history']['fiscal_sources'][0]['snapshot'])==digest(augsource)
 augfixture=read(F/('scenarios/tvm-three-period-august-2026.10.08-28-draft36/'+sourcecase+'-current.json'));sign=sourcecase.split('-')[0];julpath=julE/('july-'+sign+'-snapshot.json');julsource=read(julpath)
 assert digest(augfixture['recognition_history']['fiscal_sources'][0]['snapshot'])==digest(julsource)
 for prior,current,period in [(julsource,augfixture,'2026-07'),(augsource,f,'2026-08')]:
  base=next(t['base'] for t in prior['result']['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');entry=next(v for v in base['entries'] if v['id']=='yield1');memory=entry['d2101_binding']['calculation']
  assert base['period_end'].startswith(period)
  for k,w in {'quantity':memory['closing_quantity'],'acquisition_cost':memory['closing_acquisition_cost'],'book_balance':memory['monthly']['vSaldoContFin']}.items():assert Decimal(current['position']['opening'][k])==Decimal(w),(case,period,k)
 chains.append({'case':case,'july_snapshot':julpath.relative_to(ROOT).as_posix(),'july_sha256':sha(julpath),'august_snapshot':augpath.relative_to(ROOT).as_posix(),'august_sha256':sha(augpath),'september_snapshot':(E/(case+'-snapshot.json')).relative_to(ROOT).as_posix(),'september_sha256':sha(E/(case+'-snapshot.json')),'native_opening_continuity':True,'source_snapshots_match':True})
report={'status':'THREE_PERIOD_SYNTHETIC_ROUNDING_CHAIN_VALIDATED','development_bundle':V,'latest_locally_accepted_candidate':'2026.10.06-27','quality':quality,'final_pint':finalstyle,'september':corpus,'chains':chains,'distinct_financial_replays':26,'distinct_xml_bodies':156,'history_guards':guards,'origin_guards':origins,'directed_tests':directed,'research':research,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':49,'three_period_rounding_corpus_accepted_locally':True,'fixture_preparation_markers_preserved':True,'runtime_qualification_proven_by_captured_decisions':True,'integral_scope28_completed':False,'phase4b_completed':False,'production_ready':False,'limitations':['One September SELL per title; zero new sale gain, unappropriated return and fair value in this corpus','Covered and uncovered rounding sources remain distinct; no inferred fiscal posting','Integral scope28 fund and multiple-title realization acceptance remains pending']}
save(E/'three-period-progress-report.json',report)
print(json.dumps({'financial_replays':26,'xml_bodies':156,'historical_artifacts_checked':len(baseline),'integral_scope28_completed':False,'production_ready':False}))
