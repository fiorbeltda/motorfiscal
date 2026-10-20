"""Verify public prior fiscal recognition through current disposal, documents and immutable replays."""
import copy,hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.07-28-draft12';PV='2026.10.07-28-draft11'
E=ROOT/'storage/app/phase4b-realization-public-history-draft-20261007-28';S=F/f'scenarios/tvm-public-history-{V}';B=f'bundles/consumption-phase4b-{V}.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['vendor/bin/pest','--compact'],'history-tests':docker+['vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesPublicHistoryTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],'pint':docker+['vendor/bin/pint','--test'],
 'public-recognition-replays':['python','scripts/verify_phase4b_tvm_public_recognition_draft.py','corpus'],
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
 p=subprocess.run(docker+['php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stderr+p.stdout[:1600]
 return json.loads(p.stdout)
if name=='corpus':
 cases=[];replays=0;xml_count=0
 for route in ['direct','fund']:
  snapshot=E/('prior-'+route+'-snapshot.json');r=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]);assert r['replay_matches'];save(E/('prior-'+route+'-replay.json'),r);replays+=1
 for case in ['partial','complete','exclusive-fund','two-sales']:
  source=S/(case+'-financial.json');snapshot=E/(case+'-snapshot.json');oracle=read(S/(case+'-oracle.json'))
  result=run(['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
  stages={r['stage']:r for r in result['trace']};base=stages['FINANCIAL_OPERATION_AND_BASE']['base'];tax=stages['FINANCIAL_GROSS_DEBITS'];assert base['net_base']==oracle['expected_base'] and tax['base']==base['net_base']
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[key])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  q=next(e for e in base['entries'] if e['id']=='yield1')['d2101_binding'];bridge=q['sale_recognition_binding'];analysis=bridge['analysis'];history=analysis['replayed_fiscal_history']
  assert history['opening_fiscal_components']==oracle['prior_available'] and history['fiscal_snapshots_replayed']==1 and history['closing_memory_replayed'] and analysis['historical_fiscal_snapshot_replay_performed']
  assert q['xml_title']['vVarMensal']=='50.00' and bridge['additional_sale_taxable_amount']=='0.00' and q['book_apur_amount']=='0.00'
  assert sum((Decimal(s['previously_assessed_return'])+Decimal(s['previously_excluded_art195_return'])+Decimal(s['previously_removed_pis_cofins']) for s in analysis['realizations']),Decimal(0))==Decimal(bridge['prior_return_not_reincluded'])
  replay=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(case+'-replay.json'),replay);replays+=1
  month=S/(case+'-month.json');report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
  projected=run(args);stored=read(report)
  for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[key]==stored[key]
  assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199','D2101'} and not stored['production_ready'] and not stored['projection']['period_closed']
  assert stored['projection']['debt_yield_projection']['total_vApur']==oracle['independent_oracle']['monthly']['vApur']
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
    accounts={r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)};assert accounts['interest000'].find('d:vApur',ns).text=='1000.00'
    for account in read(month)['book']['accounts']:
     for field in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:assert accounts[account['cCta']].find('d:'+field,ns).text==account[field]
   xml_count+=1
  cases.append({'case':case,'net_base':base['net_base'],'auxiliary_base':q['taxable_amount'],'prior_return_not_reincluded':bridge['prior_return_not_reincluded'],'current_return_not_reincluded':bridge['current_curve_return_not_reincluded'],'prior_fiscal_split':history['opening_fiscal_components'],'replay_matches':True,'monthly_projected':True})
 save(E/'corpus.json',{'cases':cases,'financial_replays':replays,'xml_bodies':xml_count,'scope28_completed':False,'production_ready':False});print(json.dumps(cases));sys.exit(0)
if name=='guards':
 cases=[];original=read(S/'partial-financial.json')
 for case,reason in [('missing','RTC_TVM_REALIZATION_REPLAYED_FISCAL_HISTORY_MISSING'),('result','RTC_TVM_HISTORY_FISCAL_SNAPSHOT_REPLAY_MISMATCH'),
  ('knowledge','RTC_TVM_HISTORY_CAPTURED_KNOWLEDGE_OR_REPLAY_INVALID'),('duplicate','RTC_TVM_HISTORY_DUPLICATE_FISCAL_SOURCE'),
  ('sale-reference','RTC_TVM_REALIZATION_SALE_FISCAL_HISTORY_REFERENCE_CONFLICT'),('pis','RTC_TVM_REALIZATION_PRIOR_ASSESSED_AND_EXCLUDED_RETURN_CONFLICT'),('closing-fiscal','RTC_TVM_REALIZATION_CLOSING_FISCAL_COMPONENT_CONFLICT')]:
  scenario=copy.deepcopy(original);input=scenario['facts']['rtc.financial_ledger'][0]['entries'][4]['public_offer_debt']['sale_recognition']['analysis_input']
  if case=='missing':input.pop('recognition_history')
  elif case=='result':input['recognition_history']['fiscal_sources'][0]['snapshot']['result']['decision']={'forged':True}
  elif case=='knowledge':input['recognition_history']['fiscal_sources'][0]['snapshot']['knowledge']['documents']['rtc_code_catalog']['version']='forged'
  elif case=='duplicate':input['recognition_history']['fiscal_sources'].append(copy.deepcopy(input['recognition_history']['fiscal_sources'][0]))
  elif case=='sale-reference':input['realizations'][0]['history']['snapshot_id']='SYNTHETIC.UNBOUND'
  elif case=='pis':input['realizations'][0]['history'].pop('previously_removed_pis_cofins')
  else:
   from dere_daily_memory_oracle import digest
   input['recognition_inventory']['closing_fiscal_components']['removed_pis_cofins']='0.00000000'
   ip=input['inventory_evidence'];ip['value']['inventory']=input['recognition_inventory'];ip.pop('source_hash');ip['source_hash']=digest(ip)
   cp=input['complete_evidence'];cp['value']['recognition_inventory']=input['recognition_inventory'];cp['value']['inventory_evidence_hash']=ip['source_hash'];cp.pop('source_hash');cp['source_hash']=digest(cp)
  source=E/(case+'-guard-financial.json');save(source,scenario);result=run(['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']);assert result['decision']==[] and result['reason_codes']==[reason],result['reason_codes']
  save(E/(case+'-guard-result.json'),result);cases.append({'case':case,'reason':reason,'rejected':True})
 save(E/'guards.json',{'guards':cases});print(json.dumps(cases));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json'],1);assert not result['ready'] and result['official_source_hashes_valid']==48 and 'error' not in result
 save(E/'production-assessment.json',result);print(json.dumps({'ready':False,'official_sources':48}));sys.exit(0)
assert name=='report'
quality={n:read(E/(n+'.json')) for n in commands};assert all(v['exit_code']==0 for v in quality.values())
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
corpus=read(E/'corpus.json');guards=read(E/'guards.json');gate=read(E/'production-assessment.json');assert len(corpus['cases'])==4 and corpus['financial_replays']==6 and corpus['xml_bodies']==24 and len(guards['guards'])==7 and not gate['ready']
for source in read(F/B)['official_sources']:assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256']
report={'status':'DRAFT_PUBLIC_PRIOR_FISCAL_HISTORY_DISPOSAL_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'prior_development_bundle':PV,'development_bundle':V,'quality':quality,'corpus':corpus,'guards':guards,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':48,'production_ready':False,
 'pending':['New public sale results and fair-value accounting/fiscal D1101/D2101 bridge','Remaining negative historical public results, combined titles, funds, rounding and integral scope28 acceptance']}
save(E/'progress-report.json',report);print(json.dumps(report))
