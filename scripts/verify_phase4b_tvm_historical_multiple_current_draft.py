"""Validate two historical public realizations, shared book and D2101 without cross-title offsets."""
import json,hashlib,subprocess,sys,re,gc,xml.etree.ElementTree as ET
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft40';E=ROOT/'storage/app/phase4b-realization-historical-multiple-current-draft-20261008-28';S=F/('scenarios/tvm-historical-multiple-current-'+V);B='bundles/consumption-phase4b-'+V+'.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
def run(args,code=0):
 r=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert r.returncode==code,r.stdout[:3000]+r.stderr;return json.loads(r.stdout)
def compact(p):
 if p.stat().st_size>1000000:
  h=sha(p);r=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert r.returncode==0 and sha(p)==h
name=sys.argv[1];docker=['docker','compose','exec','-T','app'];commands={'tests':docker+['php','-d','memory_limit=512M','vendor/bin/pest','--compact'],'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],'pint':docker+['vendor/bin/pint','--test'],'reproduce':['python','scripts/build_phase4b_tvm_historical_multiple_current_draft.py'],'regression38':['python','scripts/verify_phase4b_tvm_third_sale_integrated_draft.py','corpus'],'regression29':['python','scripts/verify_phase4b_tvm_public_signed_release_draft.py','corpus'],'regression21':['python','scripts/verify_phase4b_tvm_public_mixed_history_draft.py','corpus'],'candidate27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],'candidate26':['python','scripts/verify_phase4b_dere_daily_memory_cut.py'],'candidate25':['python','scripts/verify_phase4b_dere_yield_cut.py']}
if name in commands:
 log=E/(name+'.log');assert not log.exists();r=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8');log.write_text(r.stdout+r.stderr,encoding='utf-8');v={'command':commands[name],'exit_code':r.returncode,'log_sha256':sha(log)};m=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',r.stdout+r.stderr))
 if m:v.update(tests=int(m[1]),assertions=int(m[2]))
 save(E/(name+'.json'),v);print(json.dumps(v));sys.exit(r.returncode)
if name=='corpus':
 cases=[];xml_count=0
 for pos in ['partial','complete']:
  for neg in ['partial','complete']:
   for mode in ['separate','shared']:
    case=pos+'-'+neg+'-'+mode;snap=E/(case+'-snapshot.json');args=['fiscal:rtc:run',(S/(case+'-financial.json')).relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
    if not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
    result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];compact(snap);replay=run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()]);assert replay['replay_matches'] and replay['original_hash']==result['decision_hash'];save(E/(case+'-replay.json'),replay)
    base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');entries={v['id']:v for v in base['entries']};assert base['net_base']==('745.00' if pos=='partial' else '741.85') and base['deductions_before_art188']=='300.00' and len(base['realization_bindings'])==2
    for sign,part,entryid,parent in [('positive',pos,'yield1','tvm1'),('negative',neg,'yield2','tvm2')]:
     fixture=read(S/(sign+'-'+part+'-current.json'));q=entries[entryid]['d2101_binding'];actual=q['calculation'];assert q['taxable_amount']==fixture['expected']['auxiliary_base'] and q['book_apur_amount']=='0.00'
     for field,want in fixture['independent_oracle'].items():
      if field!='daily_memory':assert actual[field]==want,(case,sign,field)
      else:
       assert len(actual[field])==len(want)
       for row,expected in zip(actual[field],want):
        for k,v in expected.items():assert row[k]==v,(case,sign,row['date'],k)
     binding=base['realization_bindings'][parent];assert binding['financial_entries']==[] and binding['deductions']=='0.00';assert binding['analysis']['recognition_inventory']['closing_fiscal_components']==fixture['recognition_inventory']['closing_fiscal_components']
     for edge in ['opening','closing']:
      for field,want in fixture['recognition_inventory'][edge].items():assert Decimal(binding['analysis']['recognition_inventory'][edge][field])==Decimal(want)
    assert entries['yield2']['d2101_binding']['unoffset_title_loss']=='50.00'
    tax=next(t for t in result['trace'] if t['stage']=='FINANCIAL_GROSS_DEBITS');assert tax['base']==base['net_base']
    for k,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[k])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
    month=read(S/(case+'-month.json'));accounts={a['cCta']:a for a in month['book']['accounts']};proceeds=Decimal('10150' if pos=='complete' else '4060')+Decimal('9800' if neg=='complete' else '3920');assert Decimal(accounts['cash000']['vMovDebt'])==proceeds and accounts['cash000']['vMovCred']=='0.00' and Decimal(accounts['cash000']['vSaldoFinal'])==Decimal(10000)+proceeds
    if mode=='shared':assert accounts['curve000']['vMovCred']=='50.00' and accounts['curve000']['vMovDebt']=='50.00' and set(accounts['curve000']['financial_entry_ids'])=={'yield1','yield2'}
    report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snap.relative_to(ROOT).as_posix(),(S/(case+'-month.json')).relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
    if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
    projected=run(args);stored=read(report);assert stored['projection']['status']=='PROJECTED' and not stored['production_ready'];assert stored['projection']['debt_yield_projection']['title_count']==2 and stored['projection']['debt_yield_projection']['total_vApur']==('45.00' if pos=='partial' else '41.85')
    for k in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[k]==stored[k]
    assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199','D2101'}
    for event,path in stored['event_outputs'].items():
     raw=(ROOT/path).read_bytes();validation=stored['event_validations'][event];assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest() and validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1;assert validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd';xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]}
     if event=='D2101':
      titles={t.attrib['idTitulo']:t for t in xml.findall('.//d:detTitulo',ns)};assert len(titles)==2
      for id in ['yield1','yield2']:
       for k,v in entries[id]['d2101_binding']['xml_title'].items():
        row=titles[entries[id]['d2101_binding']['xml_title']['idTitulo']];assert (row.get(k) if k in ['idTitulo','cCta'] else row.find('d:'+k,ns).text)==v
     if event=='D1101':
      rows={r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)}
      for code,a in accounts.items():
       for k in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:assert rows[code].find('d:'+k,ns).text==a[k]
     xml_count+=1
    cases.append({'case':case,'snapshot_sha256':sha(snap),'financial_replay':True,'base':base['net_base'],'cash_closing':accounts['cash000']['vSaldoFinal'],'two_titles_projected':True});del result,base,entries,projected,stored;gc.collect();print(case,flush=True)
 save(E/'multiple-corpus.json',{'cases':cases,'financial_replays':8,'xml_bodies':xml_count,'current_multiple_title_closing_accepted':True,'scope28_completed':False,'production_ready':False});sys.exit(0)
if name=='gate':
 v=run(['fiscal:rtc:production-assess','--bundle-manifest='+B,'--controls=resources/fiscal/production/phase4b-controls-'+V+'.json'],1);assert not v['ready'] and v['official_source_hashes_valid']==49;save(E/'production-assessment.json',v);sys.exit(0)
assert name=='report';quality={k:read(E/(k+'.json')) for k in commands}
for k,v in quality.items():assert v['exit_code']==0 and v['command']==commands[k] and sha(E/(k+'.log'))==v['log_sha256']
corpus=read(E/'multiple-corpus.json');assert corpus['financial_replays']==8 and corpus['xml_bodies']==48 and len(corpus['cases'])==8
directed=read(E/'directed-tests.json');assert directed['exit_code']==0 and sha(E/'directed-tests.log')==directed['log_sha256']
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
research=read(E/'research.json');assert not research['normative_homologation_claimed']
for pathkey,hashkey in [('manual_path','manual_sha256'),('rfb_article_path','rfb_article_sha256'),('source_preparation_audit','source_preparation_audit_sha256')]:assert sha(ROOT/research[pathkey])==research[hashkey]
for item in read(E/'implementation-hashes.json'):assert sha(ROOT/item['path'])==item['sha256']
gate=read(E/'production-assessment.json');assert not gate['ready']
for source in read(F/B)['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
save(E/'multiple-progress-report.json',{'status':'TWO_HISTORICAL_PUBLIC_TITLES_SHARED_AND_SEPARATE_RESULT_ACCOUNTS_VALIDATED','bundle':V,'quality':quality,'directed_tests':directed,'research':research,'corpus':corpus,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':49,'current_multiple_title_closing_accepted':True,'scope28_completed':False,'phase4b_completed':False,'production_ready':False});print(json.dumps({'historical_preserved':len(baseline),'financial_replays':8,'xml_bodies':48,'scope28_completed':False}))
