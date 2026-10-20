"""Audit representative consolidation47 decisions, exact replay and local unsigned XML."""
import json, hashlib, subprocess, gc, sys, xml.etree.ElementTree as ET
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
from dere_daily_memory_oracle import digest

ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.09-28-draft47'
E=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28'
S=F/('scenarios/tvm-realization-consolidated-'+V);B='bundles/consumption-phase4b-'+V+'.json'
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
matrix=read(ROOT/'storage/app/phase4b-realization-consolidation-preparation-20261009-28/requirements-evidence-matrix.json')
expansion=read(E/'expansion-cases.json')
for case,metadata in expansion['cases'].items():
 fixture=read(ROOT/metadata['fixture']);matrix['cases'][case]={'observations':{'base':fixture['expected']['base'],'sales':[]},'source_fixture':metadata['fixture'],'metadata':metadata}
if sys.argv[1]=='corpus':
 cases=[];count=0
 for case,row in matrix['cases'].items():
  financial=S/(case+'-financial.json');monthpath=S/(case+'-month.json');compact(financial)
  snap=E/(case+'-snapshot.json');args=['fiscal:rtc:run',financial.relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
  if not snap.exists():args+=['--snapshot='+snap.relative_to(ROOT).as_posix()]
  result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];compact(snap)
  replay=run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()]);assert replay['replay_matches'] and replay['original_hash']==result['decision_hash'];save(E/(case+'-replay.json'),replay)
  base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');assert base['net_base']==row['observations']['base']
  tax=next(stage for stage in result['trace'] if stage['stage']=='FINANCIAL_GROSS_DEBITS');assert tax['base']==base['net_base']
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[key])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN),(case,key)
  sales=[sale for binding in base['realization_bindings'].values() for sale in binding['analysis']['realizations']]
  if 'source_fixture' in row:
   fixture=read(ROOT/row['source_fixture']);assert sha(ROOT/row['source_fixture'])==row['metadata']['fixture_sha256'];assert len(sales)==1
   expected_components=fixture['components'].get(sales[0]['operation_id'],fixture['components']);assert isinstance(expected_components,dict) and len(expected_components)==6
   for part,value in expected_components.items():assert Decimal(sales[0]['components'][part])==Decimal(value),(case,part)
   inventory=base['realization_bindings']['tvm1']['analysis']['recognition_inventory']
   for edge in ['opening','closing','closing_fiscal_components']:
    for field,value in fixture['recognition_inventory'][edge].items():assert Decimal(inventory[edge][field])==Decimal(value),(case,edge,field)
   entry=next(entry for entry in base['entries'] if entry['id']=='yield1');binding=entry['d2101_binding'];assert binding['taxable_amount']==fixture['expected']['auxiliary_base']
   for field,value in fixture['independent_oracle'].items():
    if field=='daily_memory':
     assert len(binding['calculation'][field])==len(value)
     for actual,expected in zip(binding['calculation'][field],value):
      for key,amount in expected.items():assert actual[key]==amount,(case,field,key)
    else:assert binding['calculation'][field]==value,(case,field)
  else:assert len(sales)==len(row['observations']['sales'])
  for sale,want in zip(sales,row['observations']['sales']):
   for key,value in want.items():
    if key=='components':
     for part,amount in value.items():assert Decimal(sale[key][part])==Decimal(amount),(case,part)
    else:assert Decimal(sale[key])==Decimal(value),(case,key)
  report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snap.relative_to(ROOT).as_posix(),monthpath.relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
  if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
  projected=run(args);stored=read(report);assert stored['projection']['status']=='PROJECTED' and not stored['production_ready']
  for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[key]==stored[key]
  month=read(monthpath);accounts={a['cCta']:a for a in month['book']['accounts']}
  for event,path in stored['event_outputs'].items():
   raw=(ROOT/path).read_bytes();validation=stored['event_validations'][event]
   assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest() and validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
   assert validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
   xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]}
   if event=='D1101':
    rows={r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)}
    for code,a in accounts.items():
     for key in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:assert rows[code].find('d:'+key,ns).text==a[key],(case,code,key)
   if event=='D2101':
    titles={(t.attrib['idTitulo'],t.attrib['cCta']):t for t in xml.findall('.//d:detTitulo',ns)}
    bindings=[entry['d2101_binding']['xml_title'] for entry in base['entries'] if 'd2101_binding' in entry and entry['d2101_binding'].get('status')=='RESOLVED']
    assert len(titles)==len(bindings)
    for binding in bindings:
     title=titles[(binding['idTitulo'],binding['cCta'])]
     for key,value in binding.items():assert (title.get(key) if key in ['idTitulo','cCta'] else title.find('d:'+key,ns).text)==value
   count+=1
  cases.append({'case':case,'knowledge_version':V,'snapshot_sha256':sha(snap),'financial_replay':True,'base':base['net_base'],'event_count':len(stored['event_outputs'])})
  del result,base,sales,projected,stored;gc.collect();print(case,flush=True)
 save(E/'expanded-corpus.json',{'cases':cases,'financial_replays':len(cases),'xml_bodies':count,'representative_cases_validated':True,'unified_candidate_accepted':False,'scope28_completed':False,'phase4b_completed':False,'production_ready':False})
elif sys.argv[1]=='gate':
 value=run(['fiscal:rtc:production-assess','--bundle-manifest='+B,'--controls=resources/fiscal/production/phase4b-controls-'+V+'.json'],1);assert not value['ready'] and value['official_source_hashes_valid']==51;save(E/'production-assessment.json',value)
elif sys.argv[1]=='preservation':
 baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
 for source in read(F/B)['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
 save(E/'historical-preservation.json',{'artifacts_checked':len(baseline),'artifacts_changed':changed,'official_sources_verified':51,'scope28_completed':False,'production_ready':False})
elif sys.argv[1]=='report':
 quality={name:read(E/(name+'.json')) for name in ['directed-tests','historical-directed-tests','guards','full-suite','phpstan','pint','reproduce','candidate27','candidate26','candidate25','expanded-corpus-retry1','tax-audit']}
 for name,value in quality.items():assert value['exit_code']==0 and value['log_sha256']==sha(E/(name+'.log'))
 corpus=read(E/'expanded-corpus.json');assert corpus['financial_replays']==87 and corpus['xml_bodies']==520 and len(corpus['cases'])==87
 tax_audit=read(E/'separate-tax-audit.json');assert tax_audit['case_count']==87 and tax_audit['separate_ibs_cbs_verified']
 assert {row['case']:row['snapshot_sha256'] for row in tax_audit['cases']}=={row['case']:row['snapshot_sha256'] for row in corpus['cases']}
 baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
 for item in read(E/'implementation-hashes.json'):assert sha(ROOT/item['path'])==item['sha256']
 gate=read(E/'production-assessment.json');assert not gate['ready'] and gate['official_source_hashes_valid']==51
 for source in read(F/B)['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
 research=read(E/'research.json');assert not research['normative_homologation_claimed'] and not research['new_public_return_at_sale_document_bridge_proven']
 for path_key,hash_key in [('manual_path','manual_sha256'),('rfb_article_path','rfb_article_sha256'),('rendered_page','rendered_page_sha256')]:assert sha(ROOT/research[path_key])==research[hash_key]
 for metadata in expansion['cases'].values():
  for path_key,hash_key in [('fixture','fixture_sha256'),('original_financial_input','original_financial_input_sha256'),('original_month_input','original_month_input_sha256')]:assert sha(ROOT/metadata[path_key])==metadata[hash_key]
 save(E/'consolidation-progress-report.json',{'status':'EXPANDED_HISTORICAL_REALIZATIONS_ONE_KNOWLEDGE_VALIDATED','bundle':V,'quality':quality,'research':research,'separate_tax_audit':tax_audit,'corpus':corpus,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':51,'representative_cases_validated':True,'unified_candidate_accepted':False,'scope28_completed':False,'phase4b_completed':False,'production_ready':False})
 print(json.dumps({'representative_cases':87,'replays':87,'xml_bodies':520,'historical_preserved':len(baseline),'scope28_completed':False,'production_ready':False}))
else:raise ValueError(sys.argv[1])
