"""Verify explicit fair-value realization journals and stock; integral scope28 remains open."""
import copy,hashlib,json,re,subprocess,sys,xml.etree.ElementTree as ET
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.07-28-draft9'
E=ROOT/'storage/app/phase4b-realization-rounding-draft-20261007-28';S=F/f'scenarios/tvm-rounding-{V}';B=f'bundles/consumption-phase4b-{V}.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
docker=['docker','compose','exec','-T','app']
commands={'tests':docker+['vendor/bin/pest','--compact'],
 'rounding-tests':['docker','compose','exec','-T','-e','TVM_ROUNDING_CAPTURE=1','app','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesDailyRoundingTest.php','--compact'],
 'phpstan':docker+['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],
 'pint':docker+['vendor/bin/pint','--test'],
 'regression27':['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],
 'negative-history-replays':['python','scripts/verify_phase4b_tvm_negative_history_draft.py','corpus'],
 'multiple-title-replays':['python','scripts/verify_phase4b_tvm_multiple_titles_draft.py','corpus'],
 'other-release-replays':['python','scripts/verify_phase4b_tvm_other_releases_draft.py','corpus'],
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
 for case,base in [('positive-fractions','900.01'),('negative-fractions','899.99'),('opposing-fractions','900.00')]:
  source=S/(case+'-financial.json');snapshot=E/(case+'-snapshot.json');args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
  result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
  stages={r['stage']:r for r in result['trace']};financial=stages['FINANCIAL_OPERATION_AND_BASE']['base'];tax=stages['FINANCIAL_GROSS_DEBITS']
  assert financial['status']=='RESOLVED' and financial['net_base']==base and tax['status']=='RESOLVED' and tax['base']==base
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[key])==(Decimal(base)*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  parent=financial['realization_bindings']['tvm1']
  replay=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/(case+'-replay.json'),replay)
  month=S/(case+'-month.json');report=E/(case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
  projected=run(args);stored=read(report)
  for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[key]==stored[key]
  assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199'} and not stored['production_ready'] and not stored['projection']['period_closed']
  binding=stored['projection']['securities_realization_binding'];assert binding['status']=='RESOLVED' and not binding['production_ready']
  allocation=binding['parents']['tvm1']['daily_journal_rounding'];fixture=read(S/(case+'.json'))
  assert allocation['allocations']==fixture['daily_journal_rounding']['allocations']
  assert allocation['gross_credit']==fixture['expected']['gross_curve_credit'] and allocation['gross_debit']==fixture['expected']['gross_curve_debit']
  assert not allocation['posting_performed'] and not allocation['normative_homologation_claimed']
  if case=='opposing-fractions':assert allocation['gross_credit']=='0.01' and allocation['gross_debit']=='0.01'
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
   xml_count+=1
  cases.append({'case':case,'net_base':base,'journal_count':binding['parents']['tvm1']['journal_count'],'replay_matches':True,'monthly_projected':True})
 save(E/'corpus.json',{'cases':cases,'xml_bodies':xml_count,'scope28_completed':False,'production_ready':False});print(json.dumps(cases));sys.exit(0)
if name=='gate':
 result=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json'],1)
 assert not result['ready'] and result['official_source_hashes_valid']==48 and 'error' not in result
 save(E/'production-assessment.json',result);print(json.dumps({'ready':False,'official_sources':48}));sys.exit(0)
if name=='guards':
 cases=[];base=read(S/'positive-fractions-month.json')
 for case,reason in [('missing','DAILY_JOURNAL_ROUNDING_BRIDGE_PENDING'),('residual','DAILY_JOURNAL_ROUNDING_ALLOCATION_CONFLICT'),('amount','DAILY_JOURNAL_ROUNDING_ALLOCATION_CONFLICT'),('coverage','DAILY_JOURNAL_ROUNDING_ALLOCATION_CONFLICT'),('proof','INDEPENDENT_DAILY_JOURNAL_ROUNDING_UNPROVEN'),('homologation','DAILY_JOURNAL_ROUNDING_POLICY_UNPROVEN')]:
  month=copy.deepcopy(base);row=month['securities_realization_book'][0];bridge=row['daily_journal_rounding']
  if case=='missing':row.pop('daily_journal_rounding')
  elif case=='residual':bridge['allocations'][1]['rounding_residual']='0.00000000'
  elif case=='amount':bridge['allocations'][0]['booked_amount']='0.01'
  elif case=='coverage':bridge['allocations'].pop()
  elif case=='proof':bridge.pop('independent_evidence')
  else:bridge['normative_homologation_claimed']=True
  source=E/(case+'-guard-month.json');save(source,month)
  result=run(['fiscal:dere:project-monthly-closing',(E/'positive-fractions-snapshot.json').relative_to(ROOT).as_posix(),source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}'],1)
  assert result['reason']=='RTC_DERE_TVM_BOOK_'+reason,result
  save(E/(case+'-guard-result.json'),result);cases.append({'case':case,'reason':result['reason'],'rejected':True})
 save(E/'guards.json',{'guards':cases});print(json.dumps(cases));sys.exit(0)
assert name=='report'
quality={n:read(E/(n+'.json')) for n in commands};assert all(v['exit_code']==0 for v in quality.values())
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
corpus=read(E/'corpus.json');gate=read(E/'production-assessment.json');guards=read(E/'guards.json');assert len(corpus['cases'])==3 and corpus['xml_bodies']==15 and not gate['ready'] and len(guards['guards'])==6
b=read(F/B)
for source in b['official_sources']:assert hashlib.sha256((F/'sources/official'/source['file']).read_bytes()).hexdigest()==source['sha256']
report={'status':'DRAFT_PRIVATE_TVM_DAILY_GROSS_ROUNDING_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
 'development_bundle':V,'quality':quality,'corpus':corpus,'guards':guards,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,
 'official_sources_verified':48,'production_ready':False,'pending':['Public-offer D2101 realization integration',
 'Remaining historical negative/fund/multiple-title/other-releases/remaining rounding cases and integral scope28 acceptance']}
save(E/'progress-report.json',report);print(json.dumps(report))

