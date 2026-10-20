"""Verify supplied opposite-sign gross journals without inventing tax or postings."""
import hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft32';PV='2026.10.08-28-draft31'
E=ROOT/'storage/app/phase4b-realization-public-explicit-closing-draft-20261008-28';S=F/('scenarios/tvm-public-explicit-closing-'+V);B='bundles/consumption-phase4b-'+V+'.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:
  with p.open('x',encoding='utf-8') as stream:stream.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['php','-d','memory_limit=512M','vendor/bin/pest','--compact'],
 'reproduce-bundle':['python','scripts/build_phase4b_tvm_public_explicit_closing_draft.py','prepare'],
 'reproduce-inputs':['python','scripts/build_phase4b_tvm_public_explicit_closing_draft.py','current'],
 'explicit-closing-tests':['docker','compose','exec','-T','-e','TVM_EXPLICIT_CLOSING_CAPTURE=1','app','php','-d','memory_limit=512M','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesPublicExplicitClosingTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],'pint':docker+['vendor/bin/pint','--test'],
 'regression-draft30':['python','scripts/verify_phase4b_tvm_public_gross_rounding_draft.py','corpus'],
 'regression-draft29':['python','scripts/verify_phase4b_tvm_public_signed_release_draft.py','corpus'],
 'regression-draft27':['python','scripts/verify_phase4b_tvm_public_release_only_draft.py','corpus'],
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
 result={'command':commands[name],'exit_code':p.returncode,'log_sha256':sha(log)}
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
 cases=[];xml_count=0
 names=['prior-'+case for case in ['negative','positive','zero','exact']]+[sign+'-'+mode for sign in ['negative','positive','zero','exact'] for mode in ['partial','complete']]
 for case in names:
  prior=case.startswith('prior-');version=PV if prior else V;bundle='bundles/consumption-phase4b-'+version+'.json'
  f=read(S/(case+'-current.json'));snap=E/(case+'-snapshot.json');args=['fiscal:rtc:run',(S/(case+'-financial.json')).relative_to(ROOT).as_posix(),'--bundle-manifest='+bundle]
  if not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
  r=run(args);assert r['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];base=next(t['base'] for t in r['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE')
  assert base['net_base']==f['expected']['base'] and base['deductions_before_art188']=='300.00'
  tax=next(t for t in r['trace'] if t['stage']=='FINANCIAL_GROSS_DEBITS');assert tax['base']==base['net_base']
  for field,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[field])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  parent=base['realization_bindings']['tvm1'];q=next(e['d2101_binding'] for e in base['entries'] if e['id']=='yield1')
  assert parent['financial_entries']==[] and parent['deductions']=='0.00' and parent['revenue']=='0.00'
  assert q['book_apur_amount']=='0.00' and q['taxable_amount']==f['expected']['auxiliary_base'];compare_oracle(q['calculation'],f['independent_oracle'])
  inventory=parent['analysis']['recognition_inventory']
  for edge in ['opening','closing']:
   for field,want in f['recognition_inventory'][edge].items():assert Decimal(inventory[edge][field])==Decimal(want),(edge,field)
  if prior:
   oracle=f['gross_rounding_oracle'];delta=Decimal(oracle['signed_adjustment'])
   if delta:
    bridge=q['gross_rounding_reconciliation'];assert not bridge['posting_performed'] and not bridge['production_ready']
    for field,want in oracle.items():assert bridge[field]==want,field
   else:assert 'gross_rounding_reconciliation' not in q
  else:
   history=parent['analysis']['replayed_fiscal_history'];assert history['public_explicit_net_closing_replayed'] and not history['posting_performed']
   assert history['opening_fiscal_components']==f['expected']['opening_fiscal_components'] and history['opening_carrying_components']==f['expected']['opening_carrying_components']
   assert inventory['closing_fiscal_components']==f['recognition_inventory']['closing_fiscal_components']
   sale=parent['analysis']['realizations'][0];assert Decimal(sale['historical_carrying_rounding'])==Decimal(f['components']['sale1']['rounding_bridge'])
   fraction=Decimal('.5') if case.endswith('partial') else Decimal(1)
   for field,key in [('previously_assessed_return','assessed_produced_return'),('previously_unoffset_public_title_loss','unoffset_public_title_loss')]:assert Decimal(sale[field])==Decimal(history['opening_fiscal_components'][key])*fraction
   assert not history['historical_public_loss_current_deduction_created'] and not history['historical_public_loss_cross_title_offset_created']
  replay=run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(case+'-replay.json'),replay)
  month=S/(case+'-month.json');report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snap.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),'--bundle-manifest='+bundle]
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
  cases.append({'case':case,'base':base['net_base'],'auxiliary_base':q['taxable_amount'],'period':base['period_end'],'closing_economic_components':inventory['closing'],'replay_matches':True,'monthly_projected':True})
 save(E/'corpus.json',{'cases':cases,'financial_replays':12,'xml_bodies':xml_count,'prior_monthly_projections':4,'current_monthly_projections':8,'scope28_completed':False,'production_ready':False});print(json.dumps({'financial_replays':12,'xml_bodies':xml_count}));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess','--bundle-manifest='+B,'--controls=resources/fiscal/production/phase4b-controls-'+V+'.json'],1)
 assert not result['ready'] and result['official_source_hashes_valid']==49 and 'error' not in result;save(E/'production-assessment.json',result);print(json.dumps({'ready':False,'official_sources':49}));sys.exit(0)
assert name=='report'
quality={key:read(E/(key+'.json')) for key in commands};assert all(v['exit_code']==0 for v in quality.values())
for key,value in quality.items():assert value['command']==commands[key] and sha(E/(key+'.log'))==value['log_sha256']
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
corpus=read(E/'corpus.json');gate=read(E/'production-assessment.json');assert len(corpus['cases'])==12 and corpus['xml_bodies']==72 and not gate['ready']
research=read(E/'research.json');assert sha(ROOT/research['manual_file'])==research['manual_sha256'] and not research['normative_homologation_claimed']
assert sha(ROOT/research['legal_source'])==research['legal_sha256']==sha(ROOT/research['live_capture'])==research['live_sha256']
for path,expected in read(E/'initial-operation-date-preservation.json').items():assert sha(ROOT/path)==expected
publication=read(E/'publication-confirmation.json');assert sha(ROOT/publication['capture'])==publication['sha256'] and publication['version']=='1.3.0'
for source in read(F/B)['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
save(E/'progress-report.json',{'status':'DRAFT_PUBLIC_EXPLICIT_NET_CLOSING_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27','prior_development_bundle':PV,'development_bundle':V,'quality':quality,'corpus':corpus,'research':research,'publication_confirmation':publication,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':49,'production_ready':False,'limits':['Independent supplied accounting and closing proofs; engine never performs postings','Prior first acquisition, one sale, zero PIS and art195 excess; explicit net closing proof required; broader chains and newly generated subsequent rounding remain unproved','Synthetic proofs and unsigned XML do not establish reception transmission payment or homologation'],'pending':['Broader mixed-sign chains and residuals generated at subsequent sales','Broader acquisition and multi-release combinations','Produced return at sale without proved appropriation','Integral scope28 and phase4b acceptance']});print(json.dumps({'historical_artifacts':len(baseline),'production_ready':False}))
