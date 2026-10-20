"""Verify amortization coupon acquisition and subsequent realization and immutable historical evidence."""
import hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
ROOT=Path(__file__).resolve().parents[1]; F=ROOT/'resources/fiscal'; V='2026.10.08-28-draft27'
E=ROOT/'storage/app/phase4b-realization-public-release-only-draft-20261008-28';S=F/('scenarios/tvm-public-release-only-'+V);B='bundles/consumption-phase4b-'+V+'.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:
  with p.open('x',encoding='utf-8') as stream:stream.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['php','-d','memory_limit=512M','vendor/bin/pest','--compact'],
 'release-only-tests':['docker','compose','exec','-T','-e','TVM_RELEASE_ONLY_CAPTURE=1','app','php','-d','memory_limit=512M','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesPublicReleaseOnlyTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],'pint':docker+['vendor/bin/pint','--test'],
 'regression-draft25':['python','scripts/verify_phase4b_tvm_public_other_movements_draft.py','corpus'],
 'regression23':['python','scripts/verify_phase4b_tvm_public_intermediate_sale_draft.py','corpus'],
 'regression21':['python','scripts/verify_phase4b_tvm_public_mixed_history_draft.py','corpus'],
 'regression18':['python','scripts/verify_phase4b_tvm_public_negative_yield_draft.py','corpus'],
 'regression27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],
 'regression26':['python','scripts/verify_phase4b_dere_daily_memory_cut.py'],
 'regression25':['python','scripts/verify_phase4b_dere_yield_cut.py']}
name=sys.argv[1]
if name in commands:
 log=E/(name+'.log');record=E/(name+'.json');assert not log.exists() and not record.exists(),'Refusing to overwrite evidence.'
 p=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8')
 with log.open('x',encoding='utf-8') as stream:stream.write(p.stdout+p.stderr)
 result={'command':commands[name],'exit_code':p.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()}
 match=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr))
 if match:result.update(tests=int(match[1]),assertions=int(match[2]))
 save(record,result);print(json.dumps(result));sys.exit(p.returncode)
def run(args,code=0):
 p=subprocess.run(docker+['php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stdout[:2000]+p.stderr
 return json.loads(p.stdout)
def compare_oracle(actual,expected):
 for field,value in expected.items():
  if field!='daily_memory':assert actual[field]==value,field
  else:
   assert len(actual[field])==len(value)
   for observed,oracle in zip(actual[field],value):
    for key,want in oracle.items():assert observed[key]==want,(oracle['date'],key)
if name=='corpus':
 for movement in ['amortization','coupon']:
  prior=E/('prior-'+movement+'-snapshot.json');r=read(prior)['result'];base=next(t['base'] for t in r['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');parent=base['realization_bindings']['tvm1']
  assert base['net_base']=='745.00'
  q=next(e['d2101_binding'] for e in base['entries'] if e['id']=='yield1');cost={'amortization':'8000.00000000','coupon':'10000.00000000'}[movement]
  assert q['calculation']['closing_acquisition_cost']==cost and q['xml_title']['vJurosRec']==('30.00' if movement=='coupon' else '0.00')
  assert parent['analysis']['status']=='RECONCILED' and parent['analysis']['realizations']==[] and parent['analysis']['public_other_release_without_sale_reconciled']
  fixture=read(S/('prior-'+movement+'-current.json'))
  compare_oracle(q['calculation'],fixture['independent_oracle'])
  replay=run(['fiscal:rtc:replay',prior.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/('prior-'+movement+'-replay.json'),replay)
 cases=[];xml_count=0
 for case in [movement+'-'+mode for movement in ['amortization','coupon'] for mode in ['partial','complete']]:
  f=read(S/(case+'-current.json'));source=S/(case+'-financial.json');snap=E/(case+'-snapshot.json');args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
  if not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
  r=run(args);assert r['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];stages={t['stage']:t for t in r['trace']};base=stages['FINANCIAL_OPERATION_AND_BASE']['base'];tax=stages['FINANCIAL_GROSS_DEBITS']
  assert base['net_base']==f['expected']['base'] and base['deductions_before_art188']=='300.00' and tax['base']==base['net_base']
  for field,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[field])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  parent=base['realization_bindings']['tvm1'];history=parent['analysis']['replayed_fiscal_history'];q=next(e['d2101_binding'] for e in base['entries'] if e['id']=='yield1')
  assert history['public_realized_closing_replayed'] and history['opening_fiscal_components']==f['expected']['opening_fiscal_components']
  assert parent['financial_entries']==[] and parent['deductions']=='0.00' and parent['revenue']=='0.00'
  assert parent['analysis']['recognition_inventory']['closing_fiscal_components']==f['recognition_inventory']['closing_fiscal_components']
  assert q['xml_title']['vVarMensal']=='50.00' and q['book_apur_amount']=='0.00' and q['taxable_amount']==f['expected']['auxiliary_base']
  assert q['xml_title']['vJurosRec']=='0.00'
  compare_oracle(q['calculation'],f['independent_oracle'])
  sale=parent['analysis']['realizations'][0];fraction=Decimal('.5') if case.endswith('partial') else Decimal(1)
  assert Decimal(sale['previously_assessed_return'])==Decimal(f['expected']['opening_fiscal_components']['assessed_produced_return'])*fraction
  assert Decimal(sale['previously_removed_pis_cofins'])==Decimal(f['expected']['opening_fiscal_components']['removed_pis_cofins'])*fraction
  replay=run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(case+'-replay.json'),replay)
  month=S/(case+'-month.json');report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snap.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
  if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
  projected=run(args);stored=read(report)
  for field in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[field]==stored[field]
  assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199','D2101'} and not stored['production_ready'] and not stored['projection']['period_closed']
  assert stored['projection']['debt_yield_projection']['total_vApur']==q['taxable_amount']
  for event,file in stored['event_outputs'].items():
   raw=(ROOT/file).read_bytes();validation=stored['event_validations'][event];assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
   assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest() and validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
   xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]}
   if event=='D2101':
    rows=xml.findall('.//d:detTitulo',ns);assert len(rows)==1
    for field,value in q['xml_title'].items():assert (rows[0].get(field) if field in ['idTitulo','cCta'] else rows[0].find('d:'+field,ns).text)==value
   if event=='D1101':
    accounts={row.find('d:cCta',ns).text:row for row in xml.findall('.//d:infoConta',ns)};assert accounts['curve000'].find('d:vApur',ns).text=='0.00'
    for account in read(month)['book']['accounts']:
     for field in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:assert accounts[account['cCta']].find('d:'+field,ns).text==account[field]
   xml_count+=1
  cases.append({'case':case,'base':base['net_base'],'auxiliary_base':q['taxable_amount'],'opening_fiscal_components':history['opening_fiscal_components'],'closing_fiscal_components':f['recognition_inventory']['closing_fiscal_components'],'replay_matches':True,'monthly_projected':True})
 save(E/'corpus.json',{'cases':cases,'financial_replays':6,'xml_bodies':xml_count,'scope28_completed':False,'production_ready':False});print(json.dumps({'financial_replays':6,'xml_bodies':xml_count,'cases':cases}));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess','--bundle-manifest='+B,'--controls=resources/fiscal/production/phase4b-controls-'+V+'.json'],1)
 assert not result['ready'] and result['official_source_hashes_valid']==49 and 'error' not in result;save(E/'production-assessment.json',result);print(json.dumps({'ready':False,'official_sources':49}));sys.exit(0)
assert name=='report'
quality={key:read(E/(key+'.json')) for key in commands};assert all(v['exit_code']==0 for v in quality.values())
for key,value in quality.items():
 assert value['command']==commands[key] and hashlib.sha256((E/(key+'.log')).read_bytes()).hexdigest()==value['log_sha256']
research=read(E/'research.json');assert hashlib.sha256((ROOT/research['source']).read_bytes()).hexdigest()==research['sha256']
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
corpus=read(E/'corpus.json');gate=read(E/'production-assessment.json');assert len(corpus['cases'])==4 and corpus['financial_replays']==6 and corpus['xml_bodies']==24 and not gate['ready']
for source in read(F/B)['official_sources']:assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256']
report={'status':'DRAFT_PUBLIC_STANDALONE_AMORTIZATION_COUPON_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27','prior_development_bundle':'2026.10.08-28-draft26','development_bundle':V,'quality':quality,'corpus':corpus,'research':read(E/'research.json'),
 'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':49,'production_ready':False,
 'limits':['Actual prior fiscal closing plus retained own-month yield; daily own-month appropriations must have a consistent sign','Prior fiscal source closing is already retained; applying a second retained share is rejected','Nested historical capture and projection require local CLI memory_limit=512M; default128M snapshot serialization exceeded its limit','Two standalone prior August financial decisions are replayed but their signed monthly reception is not established','Synthetic proofs and unsigned XML do not establish payment reception transmission or homologation'],
 'pending':['Fund and mixed-sign combinations with historical movements','Gross components with opposite daily signs or rounding residues','Bare new produced return at sale without proved appropriation','Integral scope28 acceptance and remaining phase4b requirements']}
save(E/'progress-report.json',report);print(json.dumps({'status':report['status'],'historical_artifacts':len(baseline),'official_sources':49,'production_ready':False}))
