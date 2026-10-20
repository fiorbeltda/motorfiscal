"""Verify recognized-yield public disposal through the base, D1101/D2101 and immutable replay."""
import copy, hashlib, json, re, subprocess, sys, xml.etree.ElementTree as ET
from decimal import Decimal, ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]; F=ROOT/'resources/fiscal'; V='2026.10.07-28-draft10'
E=ROOT/'storage/app/phase4b-realization-public-recognition-draft-20261007-28'; S=F/f'scenarios/tvm-public-recognition-{V}'; B=f'bundles/consumption-phase4b-{V}.json'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists(): assert read(p)==v,p
 else: p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['vendor/bin/pest','--compact'],
 'public-tests':['docker','compose','exec','-T','-e','TVM_PUBLIC_RECOGNITION_CAPTURE=1','app','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesPublicRecognitionTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'], 'pint':docker+['vendor/bin/pint','--test'],
 'regression27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],
 'regression26':['python','scripts/verify_phase4b_dere_daily_memory_cut.py'],
 'regression25':['python','scripts/verify_phase4b_dere_yield_cut.py'],
 'rounding-replays':['python','scripts/verify_phase4b_tvm_rounding_draft.py','corpus'],
 'negative-history-replays':['python','scripts/verify_phase4b_tvm_negative_history_draft.py','corpus']}
commands.update({'final-tests':commands['tests'],'final-public-tests':commands['public-tests'],
 'final-phpstan':commands['phpstan'],'final-pint':commands['pint']})
name=sys.argv[1]
if name in commands:
 log=E/(name+'.log'); record=E/(name+'.json'); assert not log.exists() and not record.exists(),'Refusing to overwrite evidence.'
 p=subprocess.run(commands[name],cwd=ROOT,capture_output=True,encoding='utf-8'); log.write_text(p.stdout+p.stderr,encoding='utf-8')
 summary={'command':commands[name],'exit_code':p.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()}
 m=re.search(r'(\d+) passed.*?\((\d+) assertions\)',re.sub(r'\x1b\[[0-9;]*m','',p.stdout+p.stderr))
 if m: summary.update(tests=int(m[1]),assertions=int(m[2]))
 save(record,summary); print(json.dumps(summary)); sys.exit(p.returncode)
def run(args,code=0):
 p=subprocess.run(docker+['php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8'); assert p.returncode==code,p.stdout+p.stderr
 return json.loads(p.stdout)
if name=='corpus':
 cases=[]; xml_count=0
 for case in ['partial','complete','exclusive-fund','two-sales']:
  source=S/(case+'-financial.json'); snapshot=E/(case+'-snapshot.json'); fixture=read(S/(case+'.json'))
  args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not snapshot.exists(): args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
  result=run(args); assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
  stages={r['stage']:r for r in result['trace']}; base=stages['FINANCIAL_OPERATION_AND_BASE']['base']; tax=stages['FINANCIAL_GROSS_DEBITS']
  assert base['net_base']==fixture['expected']['base'] and tax['base']==base['net_base']
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:
   assert Decimal(tax[key])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  entry=next(e for e in base['entries'] if e['id']=='yield1'); q=entry['d2101_binding']; bridge=q['sale_recognition_binding']
  assert q['book_apur_amount']=='0.00' and q['xml_title']['vVarMensal']=='100.00'
  assert bridge['additional_sale_taxable_amount']=='0.00' and bridge['current_curve_return_not_reincluded']==fixture['expected']['released_curve']
  replay=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]); assert replay['replay_matches']; save(E/(case+'-replay.json'),replay)
  month=S/(case+'-month.json'); report=E/(case+'-monthly-report.json')
  args=['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not report.exists(): args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
  projected=run(args); stored=read(report)
  for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']: assert projected[key]==stored[key]
  assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199','D2101'} and not stored['production_ready'] and not stored['projection']['period_closed']
  assert stored['projection']['debt_yield_projection']['total_vApur']==fixture['expected']['auxiliary_base']
  for event,file in stored['event_outputs'].items():
   raw=(ROOT/file).read_bytes(); validation=stored['event_validations'][event]
   assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
   assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest() and validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
   xml=ET.fromstring(raw); ns={'d':xml.tag.split('}')[0][1:]}
   if event=='D2101':
    rows=xml.findall('.//d:detTitulo',ns); assert len(rows)==1
    for field,value in q['xml_title'].items():
     if field in ['cCta','idTitulo']: assert rows[0].get(field)==value
     else: assert rows[0].find('d:'+field,ns).text==value
   if event=='D1101':
    accounts={r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)}
    assert accounts['interest000'].find('d:vApur',ns).text=='1000.00'
    for account in read(month)['book']['accounts']:
     for field in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:
      assert accounts[account['cCta']].find('d:'+field,ns).text==account[field]
   xml_count+=1
  cases.append({'case':case,'net_base':base['net_base'],'auxiliary_base':q['taxable_amount'],'released_curve':bridge['current_curve_return_not_reincluded'],'replay_matches':True,'monthly_projected':True})
 save(E/'corpus.json',{'cases':cases,'xml_bodies':xml_count,'scope28_completed':False,'production_ready':False}); print(json.dumps(cases)); sys.exit(0)
if name=='guards':
 cases=[]; original=read(S/'partial-financial.json')
 for case,reason in [('missing','RTC_FINANCIAL_ART195_DAILY_SALE_GAIN_TAX_TREATMENT_PENDING'),
  ('proof','RTC_FINANCIAL_ART195_INDEPENDENT_SALE_RECOGNITION_UNPROVEN'),('scope','RTC_FINANCIAL_ART195_SALE_RECOGNITION_SCOPE_CONFLICT'),
  ('component-proof','RTC_TVM_REALIZATION_INDEPENDENT_COMPONENTS_UNPROVEN'),('coverage','RTC_TVM_REALIZATION_SALE_COVERAGE_INCOMPLETE'),
  ('separate-security-entry','RTC_FINANCIAL_ART195_SALE_RECOGNITION_SEPARATE_SECURITY_ENTRY_PENDING')]:
  scenario=copy.deepcopy(original); detail=scenario['facts']['rtc.financial_ledger'][0]['entries'][4]['public_offer_debt']; bridge=detail['sale_recognition']
  if case=='missing': detail.pop('sale_recognition')
  elif case=='proof': bridge.pop('independent_evidence')
  elif case=='scope': bridge['analysis_input']['title']['cCta']='other000'
  elif case=='component-proof': bridge['analysis_input']['realizations'][0].pop('component_evidence')
  elif case=='coverage': bridge['analysis_input']['realizations']=[]
  else:
   ledger=scenario['facts']['rtc.financial_ledger'][0]; other=copy.deepcopy(ledger['entries'][0]); other.update(id='manual-yield',document_id='manual-yield-document',security_identity={k:detail['title'][k] for k in ['idTitulo','cCta','CNPJDevedor','currency']}); ledger['entries'].append(other)
  source=E/(case+'-guard-financial.json'); save(source,scenario)
  result=run(['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}'])
  assert result['decision']==[] and result['reason_codes']==[reason],result
  save(E/(case+'-guard-result.json'),result); cases.append({'case':case,'reason':reason,'rejected':True})
 save(E/'guards-final.json',{'guards':cases}); print(json.dumps(cases)); sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json'],1)
 assert not result['ready'] and result['official_source_hashes_valid']==48 and 'error' not in result
 save(E/'production-assessment.json',result); print(json.dumps({'ready':False,'official_sources':48})); sys.exit(0)
assert name=='report'
quality={n:read(E/(n+'.json')) for n in commands}; assert all(v['exit_code']==0 for v in quality.values())
baseline=read(E/'historical-before.json'); changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h]; assert not changed,changed
corpus=read(E/'corpus.json'); gate=read(E/'production-assessment.json'); guards=read(E/'guards-final.json')
assert len(corpus['cases'])==4 and corpus['xml_bodies']==24 and not gate['ready'] and len(guards['guards'])==6
for source in read(F/B)['official_sources']: assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256']
report={'status':'DRAFT_PUBLIC_ALREADY_RECOGNIZED_YIELD_DISPOSAL_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'development_bundle':V,'quality':quality,'corpus':corpus,'guards':guards,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,
 'official_sources_verified':48,'production_ready':False,'pending':['Public new sale result and fair-value D1101/D2101 accounting bridges',
 'Actual prior public fiscal replay disposal corpus and wider combined title/fund/loss/rounding cases','Integral scope28 acceptance']}
save(E/'progress-report.json',report); print(json.dumps(report))
