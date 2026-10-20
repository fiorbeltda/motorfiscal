"""Verify complete July source decisions; the three-month chain remains open."""
import hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft35';E=ROOT/'storage/app/phase4b-realization-three-period-rounding-draft-20261008-28';S=F/('scenarios/tvm-three-period-rounding-'+V);B='bundles/consumption-phase4b-'+V+'.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
def compress(p):
 if p.stat().st_size>1000000:
  before=sha(p);result=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert result.returncode==0 and sha(p)==before,p
def run(args,code=0):
 p=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stdout[:2500]+p.stderr;return json.loads(p.stdout)
name=sys.argv[1]
docker=['docker','compose','exec','-T','app'];commands={
 'tests':docker+['php','-d','memory_limit=512M','vendor/bin/pest','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],
 'pint':docker+['vendor/bin/pint','--test'],
 'regression34':['docker','compose','exec','-T','-e','TVM_CREATED_ROUNDING_CLOSING_CAPTURE=1','app','php','-d','memory_limit=512M','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesCreatedRoundingClosingTest.php','--compact'],
 'regression33':['python','scripts/verify_phase4b_tvm_historical_sale_rounding_draft.py','corpus'],
 'regression32':['python','scripts/verify_phase4b_tvm_public_explicit_closing_draft.py','corpus'],
 'regression30':['python','scripts/verify_phase4b_tvm_public_gross_rounding_draft.py','corpus'],
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
 for case in ['negative','positive','zero','exact']:
  stem='july-'+case;fixture=read(S/(stem+'-current.json'));snap=E/(stem+'-snapshot.json');args=['fiscal:rtc:run',(S/(stem+'-financial.json')).relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
  if not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
  result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];compress(snap);replay=run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(stem+'-replay.json'),replay)
  base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');entry=next(v for v in base['entries'] if v['id']=='yield1');q=entry['d2101_binding'];assert base['net_base']==fixture['expected']['base'] and q['taxable_amount']==fixture['expected']['auxiliary_base'];expected=fixture['independent_oracle'];actual=q['calculation']
  assert base['period_end']=='2026-07-31' and base['deductions_before_art188']=='300.00' and q['book_apur_amount']=='0.00'
  parent=base['realization_bindings']['tvm1'];assert parent['financial_entries']==[] and parent['deductions']=='0.00'
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
 save(E/'july-corpus.json',{'cases':cases,'financial_replays':4,'xml_bodies':xml_count,'full_three_month_chain_accepted':False,'scope28_completed':False,'production_ready':False});print(json.dumps({'financial_replays':4,'xml_bodies':xml_count,'full_three_month_chain_accepted':False}));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess','--bundle-manifest='+B,'--controls=resources/fiscal/production/phase4b-controls-'+V+'.json'],1);assert not result['ready'] and result['official_source_hashes_valid']==49;save(E/'production-assessment.json',result);print(json.dumps({'official_sources':49,'production_ready':False}));sys.exit(0)
assert name=='report';quality={key:read(E/(key+'.json')) for key in commands};assert all(record['exit_code']==0 for record in quality.values())
for key,record in quality.items():assert record['command']==commands[key] and sha(E/(key+'.log'))==record['log_sha256']
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
corpus=read(E/'july-corpus.json');gate=read(E/'production-assessment.json');assert len(corpus['cases'])==4 and corpus['xml_bodies']==24 and not gate['ready']
directed=read(E/'july-tests.json');assert directed['exit_code']==0 and sha(E/'july-tests.log')==directed['log_sha256']
research=read(E/'research.json');assert sha(ROOT/research['legal_capture'])==research['legal_sha256'] and sha(ROOT/research['july_official_data_file'])==research['july_official_data_sha256'] and research['official_july_rows']==23 and not research['normative_homologation_claimed']
for item in read(E/'compression-preservation.json'):assert sha(ROOT/item['path'])==item['sha256_before']==item['sha256_after']
for source in read(F/B)['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
save(E/'july-progress-report.json',{'status':'JULY_SOURCE_VALIDATION_PROGRESS_ONLY','development_bundle':V,'latest_locally_accepted_candidate':'2026.10.06-27','quality':quality,'corpus':corpus,'research':research,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':49,'full_three_month_chain_accepted':False,'scope28_completed':False,'production_ready':False,'pending':['August subsequent sale with newly created rounding','September third sale consuming covered and uncovered previous rounding','Full three-period acceptance','Integral scope28 and phase4b acceptance']});print(json.dumps({'historical_artifacts_checked':len(baseline),'full_three_month_chain_accepted':False,'production_ready':False}))
