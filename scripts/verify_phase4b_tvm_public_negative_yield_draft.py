"""Verify prior unoffset public loss, current independent yield, exact XML and unchanged history."""
import hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft18';PV='2026.10.08-28-draft17'
E=ROOT/'storage/app/phase4b-realization-public-negative-yield-draft-20261008-28';S=F/f'scenarios/tvm-public-negative-yield-{V}';B=f'bundles/consumption-phase4b-{V}.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['vendor/bin/pest','--compact'],
 'public-negative-yield-tests':['docker','compose','exec','-T','-e','TVM_PUBLIC_NEGATIVE_YIELD_CAPTURE=1','app','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesPublicNegativeYieldTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],'pint':docker+['vendor/bin/pint','--test'],
 'intraday-replays':['python','scripts/verify_phase4b_tvm_intraday_yield_draft.py','corpus'],
 'public-deferral-replays':['python','scripts/verify_phase4b_tvm_public_deferral_draft.py','corpus'],
 'public-fair-replays':['python','scripts/verify_phase4b_tvm_public_fair_draft.py','corpus'],
 'public-history-replays':['python','scripts/verify_phase4b_tvm_public_history_draft.py','corpus'],
 'public-recognition-replays':['python','scripts/verify_phase4b_tvm_public_recognition_draft.py','corpus'],
 'private-fair-replays':['python','scripts/verify_phase4b_tvm_fair_value_draft.py','corpus'],
 'negative-history-replays':['python','scripts/verify_phase4b_tvm_negative_history_draft.py','corpus'],
 'rounding-replays':['python','scripts/verify_phase4b_tvm_rounding_draft.py','corpus'],
 'regression27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],
 'regression26':['python','scripts/verify_phase4b_dere_daily_memory_cut.py'],
 'regression25':['python','scripts/verify_phase4b_dere_yield_cut.py']}
name=sys.argv[1]
if name in commands:
 log=E/(name+'.log');record=E/(name+'.json');assert not log.exists() and not record.exists(),'Refusing to overwrite evidence.'
 p=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8');log.write_text(p.stdout+p.stderr,encoding='utf-8')
 summary={'command':commands[name],'exit_code':p.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()}
 m=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr))
 if m:summary.update(tests=int(m[1]),assertions=int(m[2]))
 save(record,summary);print(json.dumps(summary));sys.exit(p.returncode)
def run(args,code=0):
 p=subprocess.run(docker+['php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stderr+p.stdout[:2000]
 return json.loads(p.stdout)
if name=='corpus':
 cases=[];xml_count=0
 for route in ['direct','fund']:
  snap=E/('prior-'+route+'-snapshot.json');r=read(snap)['result'];base=next(t['base'] for t in r['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE')
  assert base['net_base']=='0.00' and base['deductions_before_art188']=='0.00' and base['entries'][0]['d2101_binding']['unoffset_title_loss']=='150.00'
  replay=run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/('prior-'+route+'-replay.json'),replay)
 for case in ['partial','complete','exclusive-fund']:
  f=read(S/(case+'-current.json'));source=S/(case+'-financial.json');snap=E/(case+'-snapshot.json');args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
  r=run(args);assert r['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];stages={t['stage']:t for t in r['trace']};base=stages['FINANCIAL_OPERATION_AND_BASE']['base'];tax=stages['FINANCIAL_GROSS_DEBITS']
  assert base['net_base']==f['expected']['base'] and base['deductions_before_art188']=='300.00' and tax['base']==base['net_base']
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[key])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  parent=base['realization_bindings']['tvm1'];history=parent['analysis']['replayed_fiscal_history'];q=next(e['d2101_binding'] for e in base['entries'] if e['id']=='yield1')
  assert parent['financial_entries']==[] and parent['deductions']=='0.00' and parent['revenue']=='0.00'
  assert history['opening_fiscal_components']['unoffset_public_title_loss']=='-150.00000000' and all(history['opening_fiscal_components'][k]=='0.00000000' for k in ['assessed_produced_return','excluded_art195_return','removed_pis_cofins','deferred_fair_value'])
  assert not history['historical_public_loss_current_deduction_created'] and not history['historical_public_loss_cross_title_offset_created']
  assert parent['analysis']['recognition_inventory']['closing_fiscal_components']==f['recognition_inventory']['closing_fiscal_components']
  assert q['xml_title']['vVarMensal']=='50.00' and q['book_apur_amount']=='0.00' and q['taxable_amount']==f['expected']['auxiliary_base']
  replay=run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(case+'-replay.json'),replay)
  month=S/(case+'-month.json');report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snap.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
  projected=run(args);stored=read(report)
  for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[key]==stored[key]
  assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199','D2101'} and not stored['production_ready'] and not stored['projection']['period_closed']
  assert stored['projection']['debt_yield_projection']['total_vApur']==q['taxable_amount']
  for event,file in stored['event_outputs'].items():
   raw=(ROOT/file).read_bytes();validation=stored['event_validations'][event];assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
   assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest() and validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
   xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]}
   if event=='D2101':
    row=xml.findall('.//d:detTitulo',ns);assert len(row)==1
    for field,value in q['xml_title'].items():assert (row[0].get(field) if field in ['idTitulo','cCta'] else row[0].find('d:'+field,ns).text)==value
   if event=='D1101':
    accounts={r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)};assert accounts['curve000'].find('d:vApur',ns).text=='0.00'
    for account in read(month)['book']['accounts']:
     for field in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:assert accounts[account['cCta']].find('d:'+field,ns).text==account[field]
   xml_count+=1
  cases.append({'case':case,'base':base['net_base'],'auxiliary_base':q['taxable_amount'],'current_deduction_from_prior_public_loss':'0.00','retained_prior_loss':f['recognition_inventory']['closing_fiscal_components']['unoffset_public_title_loss'],'replay_matches':True,'monthly_projected':True})
 save(E/'corpus.json',{'cases':cases,'financial_replays':5,'xml_bodies':xml_count,'scope28_completed':False,'production_ready':False});print(json.dumps(cases));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json'],1);assert not result['ready'] and result['official_source_hashes_valid']==48 and 'error' not in result
 save(E/'production-assessment.json',result);print(json.dumps({'ready':False,'official_sources':48}));sys.exit(0)
assert name=='report'
quality={n:read(E/(n+'.json')) for n in commands};assert all(v['exit_code']==0 for v in quality.values())
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
corpus=read(E/'corpus.json');gate=read(E/'production-assessment.json');assert len(corpus['cases'])==3 and corpus['financial_replays']==5 and corpus['xml_bodies']==18 and not gate['ready']
for source in read(F/B)['official_sources']:assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256']
report={'status':'DRAFT_REPLAYED_PRIOR_PUBLIC_NEGATIVE_YIELD_NO_CURRENT_DEDUCTION_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'prior_development_bundle':PV,'development_bundle':V,'quality':quality,'corpus':corpus,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':48,'production_ready':False,
 'limits':['Prior negative produced yield is an unoffset title memory balance, not an automatically deductible current expense','Document pending decisions and synthetic PGCC/proofs do not establish reception or homologation'],
 'pending':['Bare new produced return at sale without proved appropriation','Combined positive and negative historical sources, intervening operations, multiple titles/funds/rounding','Integral scope28 acceptance and remaining phase4b requirements']}
save(E/'progress-report.json',report);print(json.dumps(report))
