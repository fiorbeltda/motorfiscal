"""Verify actual prior/current replays, fiscal origins, XML, blocked production and unchanged history."""
import hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft15';PV='2026.10.08-28-draft14'
E=ROOT/'storage/app/phase4b-realization-public-deferral-draft-20261008-28';S=F/f'scenarios/tvm-public-deferral-{V}';B=f'bundles/consumption-phase4b-{V}.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['vendor/bin/pest','--compact'],
 'public-deferral-tests':['docker','compose','exec','-T','-e','TVM_PUBLIC_DEFERRAL_CAPTURE=1','app','vendor/bin/pest','tests/Fiscal/Unit/FinancialPublicDeferralHistoryTest.php','tests/Fiscal/Unit/DereSecuritiesPublicDeferralTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],'pint':docker+['vendor/bin/pint','--test'],
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
 for case in ['gain','loss','fund-gain','fund-loss']:
  replay=run(['fiscal:rtc:replay',(E/(case+'-prior-snapshot.json')).relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(case+'-prior-replay.json'),replay)
  history=read(E/(case+'-history-result.json'));assert history['opening_fiscal_components']==read(S/(case+'-history.json'))['expected'] and history['fiscal_snapshots_replayed']==1
 for case in ['gain','loss','fund-gain','fund-loss','full-gain','full-loss']:
  fixture=read(S/(case+'-current.json'));source=S/(case+'-financial.json');snapshot=E/(case+'-snapshot.json')
  args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
  result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'],result['reason_codes']
  stages={r['stage']:r for r in result['trace']};base=stages['FINANCIAL_OPERATION_AND_BASE']['base'];tax=stages['FINANCIAL_GROSS_DEBITS'];assert base['net_base']==fixture['expected']['base'] and tax['base']==base['net_base']
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[key])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  parent=base['realization_bindings']['tvm1'];q=next(e['d2101_binding'] for e in base['entries'] if e['id']=='yield1')
  assert parent['public_yield_binding']==q and q['xml_title']['vVarMensal']=='50.00' and q['book_apur_amount']=='0.00' and q['taxable_amount']==fixture['expected']['auxiliary_base']
  assert parent['revenue']==fixture['expected']['fair_revenue'] and parent['deductions']==fixture['expected']['fair_deductions']
  assert len(parent['financial_entries'])==1 and parent['financial_entries'][0]['realization_component']['component_kind']=='DEFERRED_FAIR_VALUE_REALIZED'
  assert parent['financial_entries'][0]['realization_component']['expected_codTrib']==('411011701' if 'loss' in case else '311011702')
  replay=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(case+'-replay.json'),replay)
  month=S/(case+'-month.json');report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
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
    for field,value in q['xml_title'].items():
     assert (row[0].get(field) if field in ['idTitulo','cCta'] else row[0].find('d:'+field,ns).text)==value
   if event=='D1101':
    accounts={r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)};assert accounts['curve000'].find('d:vApur',ns).text=='0.00'
    for account in read(month)['book']['accounts']:
     for field in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:assert accounts[account['cCta']].find('d:'+field,ns).text==account[field]
   xml_count+=1
  cases.append({'case':case,'base':base['net_base'],'auxiliary_base':q['taxable_amount'],'fair_revenue':parent['revenue'],'fair_deductions':parent['deductions'],'replay_matches':True,'monthly_projected':True})
 save(E/'corpus.json',{'cases':cases,'financial_replays':10,'xml_bodies':xml_count,'scope28_completed':False,'production_ready':False});print(json.dumps(cases));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json'],1);assert not result['ready'] and result['official_source_hashes_valid']==48 and 'error' not in result
 save(E/'production-assessment.json',result);print(json.dumps({'ready':False,'official_sources':48}));sys.exit(0)
assert name=='report'
quality={n:read(E/(n+'.json')) for n in commands};assert all(v['exit_code']==0 for v in quality.values())
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
corpus=read(E/'corpus.json');gate=read(E/'production-assessment.json');assert len(corpus['cases'])==6 and corpus['financial_replays']==10 and corpus['xml_bodies']==36 and not gate['ready']
for source in read(F/B)['official_sources']:assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256']
report={'status':'DRAFT_PUBLIC_PRIOR_SIGNED_FAIR_VALUE_REALIZATION_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'prior_development_bundle':PV,'development_bundle':V,'quality':quality,'corpus':corpus,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':48,'production_ready':False,
 'completed_development_subscopes':['Actual first-acquisition public yield and independently signed fair-value historical replay','Current partial/full fair-value realization with separate D1101/D2101 fiscal origins'],
 'limits':['Prior document-pending decisions and full carrying memory do not prove a historical D2101 receipt or tax payment','Eligibility, PGCC mappings, journals and independent proofs in the corpus remain synthetic','Unsigned XML candidates and local conventions do not constitute official homologation'],
 'pending':['New produced public yield at disposal needs a qualified D2101 bridge','Historical negative public produced yield and combinations with intervening prior operations','Combined titles, daily rounding, real evidence and integral scope28 acceptance']}
save(E/'progress-report.json',report);print(json.dumps(report))
