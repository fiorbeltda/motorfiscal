"""Verify separate public yield and fair-value fiscal origins, supplied journals and immutable evidence."""
import copy,hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.07-28-draft13'
E=ROOT/'storage/app/phase4b-realization-public-fair-draft-20261007-28';S=F/f'scenarios/tvm-public-fair-{V}';B=f'bundles/consumption-phase4b-{V}.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['vendor/bin/pest','--compact'],
 'public-fair-tests':['docker','compose','exec','-T','-e','TVM_PUBLIC_FAIR_CAPTURE=1','app','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesPublicFairTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],'pint':docker+['vendor/bin/pint','--test'],
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
 for case in ['gain','loss','full-gain','opposing-fair','exclusive-fund']:
  source=S/(case+'-financial.json');snapshot=E/(case+'-snapshot.json');fixture=read(S/(case+'.json'))
  args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
  result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'],result['reason_codes']
  stages={r['stage']:r for r in result['trace']};base=stages['FINANCIAL_OPERATION_AND_BASE']['base'];tax=stages['FINANCIAL_GROSS_DEBITS'];assert base['net_base']==fixture['expected']['base'] and tax['base']==base['net_base']
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[key])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  parent=base['realization_bindings']['tvm1'];q=next(e for e in base['entries'] if e['id']=='yield1')['d2101_binding']
  assert parent['public_yield_binding']==q and q['sale_recognition_binding']['companion_parent_id']=='tvm1' and q['sale_recognition_binding']['fair_value_tax_origin']=='D1101'
  assert q['xml_title']['vVarMensal']=='100.00' and q['taxable_amount']==fixture['expected']['auxiliary_base'] and q['book_apur_amount']=='0.00'
  assert parent['revenue']==fixture['expected']['fair_revenue'] and parent['deductions']==fixture['expected']['fair_deductions']
  assert all(e['realization_binding']['tax_origin']=='D1101' and e['realization_component']['component_kind']=='NEW_FAIR_VALUE_REALIZED' for e in parent['financial_entries'])
  replay=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(case+'-replay.json'),replay)
  month=S/(case+'-month.json');report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
  projected=run(args);stored=read(report)
  for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[key]==stored[key]
  assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199','D2101'} and not stored['production_ready'] and not stored['projection']['period_closed']
  assert stored['projection']['debt_yield_projection']['total_vApur']==q['taxable_amount']
  assert stored['projection']['securities_realization_binding']['scope']=='PUBLIC_D2101_CURVE_AND_D1101_REALIZATION_BOOK_JOURNALS_SEGREGATED'
  for event,file in stored['event_outputs'].items():
   raw=(ROOT/file).read_bytes();validation=stored['event_validations'][event];assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
   assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest() and validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
   xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]}
   if event=='D2101':
    rows=xml.findall('.//d:detTitulo',ns);assert len(rows)==1
    for field,value in q['xml_title'].items():
     if field in ['idTitulo','cCta']:assert rows[0].get(field)==value
     else:assert rows[0].find('d:'+field,ns).text==value
   if event=='D1101':
    accounts={r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)}
    assert accounts['curve000'].find('d:vApur',ns).text=='0.00'
    for account in read(month)['book']['accounts']:
     for field in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:assert accounts[account['cCta']].find('d:'+field,ns).text==account[field]
   xml_count+=1
  cases.append({'case':case,'net_base':base['net_base'],'auxiliary_base':q['taxable_amount'],'fair_revenue':parent['revenue'],'fair_deductions':parent['deductions'],'replay_matches':True,'monthly_projected':True})
 save(E/'corpus.json',{'cases':cases,'xml_bodies':xml_count,'scope28_completed':False,'production_ready':False});print(json.dumps(cases));sys.exit(0)
if name=='guards':
 cases=[];original=read(S/'loss-financial.json')
 for case,reason in [('parent-missing','RTC_FINANCIAL_ART195_FAIR_VALUE_COMPANION_SCOPE_UNPROVEN'),('yield-missing','RTC_TVM_CURRENT_PUBLIC_CURVE_COMPANION_SCOPE_UNPROVEN'),
  ('wrong-link','RTC_TVM_CURRENT_PUBLIC_CURVE_COMPANION_SCOPE_UNPROVEN'),('analysis','RTC_FINANCIAL_ART195_FAIR_VALUE_COMPANION_SCOPE_UNPROVEN'),('loss-authorization','RTC_TVM_CURRENT_LOSS_AUTHORIZATION_UNPROVEN')]:
  scenario=copy.deepcopy(original);ledger=scenario['facts']['rtc.financial_ledger'][0]
  if case=='parent-missing':ledger['entries'].pop(4)
  elif case=='yield-missing':ledger['entries'].pop()
  elif case=='wrong-link':ledger['entries'][4]['securities_realization']['public_yield_entry_id']='unknown'
  elif case=='analysis':ledger['entries'][5]['public_offer_debt']['sale_recognition']['analysis_input']['complete_evidence']['qualified']=False
  else:ledger['entries'][4]['securities_realization']['deduction_evidence']=[]
  from dere_daily_memory_oracle import digest
  unsigned=copy.deepcopy(ledger)
  for entry in unsigned['entries']:
   if 'securities_realization' in entry:entry['securities_realization'].pop('qualification_evidence',None)
  for entry in ledger['entries']:
   if 'securities_realization' in entry:
    p=entry['securities_realization']['qualification_evidence'];p['value']['ledger_input_hash']=digest(unsigned);p.pop('source_hash');p['source_hash']=digest(p)
  source=E/(case+'-guard-financial.json');save(source,scenario);result=run(['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']);assert result['decision']==[] and result['reason_codes']==[reason],result['reason_codes']
  save(E/(case+'-guard-result.json'),result);cases.append({'case':case,'reason':reason,'rejected':True})
 original=read(S/'gain-month.json')
 for case,reason in [('component-account','COMPONENT_PGCC_CODE_OR_NATURE_CONFLICT'),('journals','BOOK_JOURNAL_COMPONENT_OR_BALANCE_CONFLICT'),('proof','INDEPENDENT_BOOK_BRIDGE_UNPROVEN'),('subledger','FAIR_VALUE_SUBLEDGER_AND_METHOD_UNPROVEN')]:
  month=copy.deepcopy(original);row=month['securities_realization_book'][0]
  if case=='component-account':row['component_accounts'][next(iter(row['component_accounts']))]='curve000'
  elif case=='journals':row['journals'].pop()
  elif case=='proof':row.pop('independent_evidence')
  else:row.pop('fair_value_bridge')
  source=E/(case+'-guard-month.json');save(source,month);result=run(['fiscal:dere:project-monthly-closing',(E/'gain-snapshot.json').relative_to(ROOT).as_posix(),source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}'],1)
  assert result['reason']=='RTC_DERE_TVM_BOOK_'+reason,result['reason'];save(E/(case+'-guard-result.json'),result);cases.append({'case':case,'reason':result['reason'],'rejected':True})
 save(E/'guards.json',{'guards':cases});print(json.dumps(cases));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json'],1);assert not result['ready'] and result['official_source_hashes_valid']==48 and 'error' not in result
 save(E/'production-assessment.json',result);print(json.dumps({'ready':False,'official_sources':48}));sys.exit(0)
assert name=='report'
quality={n:read(E/(n+'.json')) for n in commands};assert all(v['exit_code']==0 for v in quality.values())
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
corpus=read(E/'corpus.json');guards=read(E/'guards.json');gate=read(E/'production-assessment.json');assert len(corpus['cases'])==5 and corpus['xml_bodies']==30 and len(guards['guards'])==9 and not gate['ready']
for source in read(F/B)['official_sources']:assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256']
report={'status':'DRAFT_PUBLIC_D2101_CURVE_D1101_REALIZED_FAIR_VALUE_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'development_bundle':V,'quality':quality,'corpus':corpus,'guards':guards,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':48,'production_ready':False,
 'pending':['Public new produced return at sale fiscal treatment','Prior public deferred fair-value histories and remaining negative historical results, combined titles/funds/rounding','Integral scope28 acceptance']}
save(E/'progress-report.json',report);print(json.dumps(report))
