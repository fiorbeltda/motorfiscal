"""Verify explicit fair-value realization journals and stock; integral scope28 remains open."""
import copy,hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.06-28-draft5'
E=ROOT/'storage/app/phase4b-realization-other-releases-draft-20261006-28';S=F/f'scenarios/tvm-other-releases-{V}';B=f'bundles/consumption-phase4b-{V}.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['vendor/bin/pest','--compact'],
 'other-release-tests':['docker','compose','exec','-T','-e','TVM_OTHER_CAPTURE=1','app','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesOtherReleasesTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],
 'pint':docker+['vendor/bin/pint','--test'],
 'regression27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],
 'fair-replays':['python','scripts/verify_phase4b_tvm_fair_value_draft.py','corpus'],
 'book-replays':['python','scripts/verify_phase4b_tvm_book_draft.py','corpus'],
 'base-replays':['python','scripts/verify_phase4b_tvm_base_draft.py','corpus'],
 'history-replays':['python','scripts/verify_phase4b_tvm_history_draft.py','replays']}
name=sys.argv[1]
if name in commands:
 log=E/(name+'.log');record=E/(name+'.json');assert not log.exists() and not record.exists(),'Refusing to overwrite evidence.'
 p=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8');log.write_text(p.stdout+p.stderr,encoding='utf-8')
 summary={'command':commands[name],'exit_code':p.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()}
 m=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr))
 if m:summary.update(tests=int(m[1]),assertions=int(m[2]))
 save(record,summary);print(json.dumps(summary));sys.exit(p.returncode)
def run(args,code=0):
 p=subprocess.run(docker+['php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stdout+p.stderr
 return json.loads(p.stdout)
if name=='corpus':
 cases=[];xml_count=0
 for case,base in [('amortization-then-sale','990.00'),('prior-coupon-then-sale','1030.00')]:
  source=S/(case+'-financial.json');snapshot=E/(case+'-snapshot.json');args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
  result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
  stages={r['stage']:r for r in result['trace']};financial=stages['FINANCIAL_OPERATION_AND_BASE']['base'];tax=stages['FINANCIAL_GROSS_DEBITS']
  assert financial['status']=='RESOLVED' and financial['net_base']==base and tax['status']=='RESOLVED' and tax['base']==base
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[key])==(Decimal(base)*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  parent=financial['realization_bindings']['tvm1']
  if case=='amortization-then-sale':
   assert Decimal(parent['analysis']['realizations'][0]['prior_return_not_reincluded'])==Decimal('30')
   component=next(c for c in parent['components'] if c['component_kind']=='DEFERRED_FAIR_VALUE_REALIZED');assert component['expected_codTrib']=='311011702'
  if case=='amortization-then-sale':
   release=parent['other_release_realizations'][0]
   assert release['operation_id']=='amort1' and Decimal(release['components']['deferred_fair_value_realized'])==Decimal('40')
   assert Decimal(release['prior_return_not_reincluded'])==Decimal('20')
   assert Decimal(parent['analysis']['recognition_inventory']['closing']['deferred_fair_value_realized'])==Decimal('100')
  else:
   assert 'other_release_realizations' not in parent
   assert Decimal(parent['analysis']['realizations'][0]['prior_return_not_reincluded'])==Decimal('20')
  replay=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(case+'-replay.json'),replay)
  month=S/(case+'-month.json');report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
  projected=run(args);stored=read(report)
  for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[key]==stored[key]
  assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199'} and not stored['production_ready'] and not stored['projection']['period_closed']
  binding=stored['projection']['securities_realization_binding'];assert binding['status']=='RESOLVED' and not binding['production_ready']
  for event,file in stored['event_outputs'].items():
   raw=(ROOT/file).read_bytes();validation=stored['event_validations'][event]
   assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
   assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest() and validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
   xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]}
   if event=='D1101':
    accounts={r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)}
    for account in read(month)['book']['accounts']:
     for field in ['vSaldoInic','vMovDebt','vMovCred','vAjusteDebt','vAjusteCred','vSaldoFinal','vApur']:
      node=accounts[account['cCta']].find('d:'+field,ns);assert (node.text if node is not None else '0.00')==account.get(field,'0.00')
    if case=='amortization-then-sale':
     assert accounts['FV000'].find('d:vMovDebt',ns).text=='100.00' and accounts['FV000'].find('d:vApur',ns).text=='100.00'
     assert accounts['fvnew000'].find('d:vMovCred',ns).text=='100.00' and accounts['fvnew000'].find('d:vAjusteCred',ns).text=='100.00' and accounts['fvnew000'].find('d:vApur',ns).text=='0.00'
   xml_count+=1
  cases.append({'case':case,'net_base':base,'journal_count':binding['parents']['tvm1']['journal_count'],'replay_matches':True,'monthly_projected':True})
 save(E/'corpus.json',{'cases':cases,'xml_bodies':xml_count,'scope28_completed':False,'production_ready':False});print(json.dumps(cases));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json'],1)
 assert not result['ready'] and result['official_source_hashes_valid']==48 and 'error' not in result
 save(E/'production-assessment.json',result);print(json.dumps({'ready':False,'official_sources':48}));sys.exit(0)
if name=='release-guards':
 from dere_daily_memory_oracle import digest
 cases=[]
 for case in ['prior-source','confirmation','operation']:
  scenario=read(S/'amortization-then-sale-financial.json');ledger=scenario['facts']['rtc.financial_ledger'][0]
  detail=ledger['entries'][4]['securities_realization'];inp=detail['analysis_input'];release=inp['recognition_inventory']['other_releases'][0]
  proof=release['realization_evidence']
  if case=='prior-source':proof['value']['prior_source_set_hash']='a'*64
  elif case=='confirmation':proof['value']['realization_confirmed']=False
  else:proof['value']['operation']['id']='sale1'
  proof.pop('source_hash');proof['source_hash']=digest(proof)
  ip=inp['inventory_evidence'];ip['value']['inventory']=copy.deepcopy(inp['recognition_inventory']);ip.pop('source_hash');ip['source_hash']=digest(ip)
  cp=inp['complete_evidence'];cp['value']['recognition_inventory']=copy.deepcopy(inp['recognition_inventory']);cp['value']['inventory_evidence_hash']=ip['source_hash'];cp.pop('source_hash');cp['source_hash']=digest(cp)
  unsigned=copy.deepcopy(ledger)
  for entry in unsigned['entries']:
   if 'securities_realization' in entry:entry['securities_realization'].pop('qualification_evidence',None)
  qp=detail['qualification_evidence'];qp['value']['analysis_input_hash']=digest(inp);qp['value']['ledger_input_hash']=digest(unsigned);qp.pop('source_hash');qp['source_hash']=digest(qp)
  source=E/(case+'-release-guard-financial.json');save(source,scenario)
  result=run(['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}'])
  reason='RTC_TVM_CURRENT_INDEPENDENT_OTHER_FAIR_VALUE_REALIZATION_UNPROVEN'
  assert reason in result['reason_codes'],result
  save(E/(case+'-release-guard-result.json'),result);cases.append({'case':case,'reason':reason,'rejected':True})
 save(E/'release-guards.json',{'guards':cases});print(json.dumps(cases));sys.exit(0)
if name=='guards':
 cases=[];base=read(S/'amortization-then-sale-month.json')
 for case,reason in [('missing-bridge','FAIR_VALUE_SUBLEDGER_AND_METHOD_UNPROVEN'),('changed-closing','FAIR_VALUE_SUBACCOUNT_CLOSING_CONFLICT'),
  ('changed-release','FAIR_VALUE_RELEASE_COMPONENT_CONFLICT'),('missing-counter-adjustment','FAIR_VALUE_RECLASSIFICATION_TAX_ADJUSTMENT_CONFLICT'),
  ('missing-reclassification-journal','BOOK_JOURNAL_COMPONENT_OR_BALANCE_CONFLICT')]:
  month=copy.deepcopy(base);detail=month['securities_realization_book'][0]
  if case=='missing-bridge':detail.pop('fair_value_bridge')
  elif case=='changed-closing':detail['fair_value_bridge']['subaccounts'][0]['closing_deferred_value']='121'
  elif case=='changed-release':detail['fair_value_bridge']['subaccounts'][0]['releases'][0]['prior_deferred_value']='81'
  elif case=='missing-counter-adjustment':next(r for r in month['book']['accounts'] if r['cCta']=='fvnew000')['vAjusteCred']='0.00'
  else:detail['journals']=[r for r in detail['journals'] if not r['event_id'].startswith('FAIR_VALUE_RECLASS:')]
  path=E/('guard-'+case+'-month.json');save(path,month)
  result=run(['fiscal:dere:project-monthly-closing',(E/'amortization-then-sale-snapshot.json').relative_to(ROOT).as_posix(),path.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}'],1)
  assert result['reason']=='RTC_DERE_TVM_BOOK_'+reason and not result['production_ready'],result
  save(E/('guard-'+case+'-result.json'),result);cases.append({'case':case,'reason':result['reason'],'rejected':True})
 save(E/'guards.json',{'guards':cases});print(json.dumps(cases));sys.exit(0)
assert name=='report'
quality={n:read(E/(n+'.json')) for n in commands};assert all(v['exit_code']==0 for v in quality.values())
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
corpus=read(E/'corpus.json');gate=read(E/'production-assessment.json');guards=read(E/'guards.json');release_guards=read(E/'release-guards.json');assert len(corpus['cases'])==2 and corpus['xml_bodies']==10 and not gate['ready'] and len(guards['guards'])==5 and len(release_guards['guards'])==3
b=read(F/B)
for source in b['official_sources']:assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256']
report={'status':'DRAFT_TVM_AMORTIZATION_AND_PRIOR_COUPON_CONTINUITY_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'development_bundle':V,'quality':quality,'corpus':corpus,'guards':guards,'release_guards':release_guards,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,
 'official_sources_verified':48,'production_ready':False,'pending':['Public-offer D2101 realization integration',
 'Remaining historical negative/fund/multiple-title/remaining other-releases/rounding cases and integral scope28 acceptance']}
save(E/'progress-report.json',report);print(json.dumps(report))

