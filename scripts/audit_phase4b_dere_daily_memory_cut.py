"""Accept daily local arithmetic while retaining unresolved method and activation boundaries."""
import hashlib,json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';E=ROOT/'storage/app/phase4b-daily-memory-20261006-26'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def log(n):return re.sub(r'\x1b\[[0-9;]*m','',(E/n).read_text(encoding='utf-8'))
assert re.search(r'1044 passed\s*\(7937 assertions\)',log('tests.log'))
assert '[OK] No errors' in log('phpstan.log')
assert re.search(r'PASS\s*\.+\s*372 files',log('pint.log'))
assert all(v==0 for v in read(E/'quality.json').values())
assert 'Verified13 partial RTC/3 general integration cases/8 replays/3 XML candidates' in log('general-regressions.log')
assert 'Candidate25 PASS:24 monthly/15 engine cases;72 XML bodies;12 financial replays' in log('predecessor-yield-regression.log')
b=read(F/'bundles/consumption-phase4b-2026.10.06-26.json')
for key,path in b['resource_paths'].items():
 p=F/path;v=sorted([read(x) for x in p.rglob('*.json')],key=lambda r:r['rule_id']) if p.is_dir() else read(p)
 assert digest(v)==b['content_hashes'][key],key
assert digest(read(F/b['production_controls']['path']))==b['production_controls']['sha256']
for s in b['official_sources']:assert sha(F/'sources/official'/s['file'])==s['sha256']
assert len(b['official_sources'])==47
c=read(F/b['resource_paths']['rtc_code_catalog']);p=c['dere_debt_daily_memory_calculation']
assert p['contract']=='DERE-D2101-DAILY-MEMORY-LOCAL-2026.10.06-1' and p['rounding_contract']=='DERE_HALF_EVEN_8_2'
assert p['rounding_source_hash']=='639643d2eee1282bd7a8f104c492f74342bd51293845011aae2fb0a64b742d04'
assert p['source_hash']=='b7756c7b0cec5cff46d77e1b1ea5d657a4e2476fa6033f8192561c17dd70ab2d'
assert len(p['series']['rows'])==21 and p['series']['series_code']==11 and p['series']['unit']=='PERCENT_PER_BUSINESS_DAY'
assert p['series_hash']==digest({k:v for k,v in p['series'].items() if k!='source_hash'})==p['series']['source_hash']
assert sha(F/'sources/official'/p['official_data_file'])==p['official_data_sha256']
old=read(F/'bundles/consumption-phase4b-2026.10.06-25.json');oldc=read(F/old['resource_paths']['rtc_code_catalog'])
for key in ['financial_operation','dere_trial_balance_projection','dere_monthly_closing_projection','dere_debt_constant_cost_calculation']:assert c[key]==oldc[key],key
corpus=read(E/'corpus.json');assert corpus['valid'] and len(corpus['cases'])==44 and corpus['snapshots_replayed']==16 and corpus['command_guards']==6
assert sum(r['status']=='RESOLVED' for r in corpus['cases'])==16
drafts=list((E/'draft-initial-fixtures').glob('*.json'))
assert len(drafts)==44 and {p.stem for p in drafts}=={r['case'] for r in corpus['cases']}
for row in corpus['cases']:
 r=read(E/(row['case']+'-result.json'));assert digest(r['result'])==r['result_hash']==row['result_hash'] and r['production_ready'] is False
 if r['result']['status']=='RESOLVED':
  q=r['result'];assert q['memory_days']==len(q['daily_memory'])==30 and q['retention_years']==5
  assert q['normative_method_homologated'] is False and q['financial_base_integration_performed'] is False and q['xml_projection_performed'] is False
  assert q['sale_gain_tax_treatment_performed'] is False and q['negative_base_created'] is False
examples={name:read(E/(name+'-result.json'))['result']['monthly'] for name in ['constant','first-purchase-before-rate','first-purchase-after-rate','weighted-additional-purchase','partial-sale-gain','complete-sale','partial-amortization','coupon']}
assert examples['first-purchase-before-rate']['vSelic']=='66.49' and examples['first-purchase-after-rate']['vSelic']=='61.29'
assert examples['weighted-additional-purchase']['vSelic']=='148.06' and examples['weighted-additional-purchase']['vApur']=='138.06'
assert read(E/'weighted-additional-purchase-result.json')['result']['closing_unit_cost']=='10.66666667'
assert read(E/'partial-sale-gain-result.json')['result']['realized_sale_gain']=='200.00'
assert examples['complete-sale']['vSelic']=='41.40' and examples['complete-sale']['vSaldoContFin']=='0.00'
regressions={}
for family,total in [('consumption-phase4b',15),('consumption-integrated',3)]:
 r=read(ROOT/f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-26.json');assert r['valid'] and r['passed']==r['total']==total;regressions[family]=total
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
g=read(E/'production-assessment.json');assert not g['ready'] and g['official_source_hashes_valid']==47
for name in ['fork_phase4b_dere_daily_memory_bundle.py','verify_phase4b_dere_daily_memory_cut.py','verify_phase4b_dere_daily_memory_regressions.py']:compile((ROOT/'scripts'/name).read_text(encoding='utf-8'),name,'exec')
report={'bundle_id':b['bundle_id'],'local_scope_validated':True,'phase4b_complete':False,'scope':'DAILY_ACQUISITION_COST_ACCOUNTING_CURVE_AND_EXPLICIT_LOCAL_SELIC_MEMORY',
 'tests':{'passed':1044,'assertions':7937},'phpstan':'PASS','pint':{'status':'PASS','files':372},'corpus':corpus,'examples':examples,
 'method_homologated':False,'retention_years':5,
 'limitations':['SYNTHETIC_MEMORY_POSITION_METHOD_AND_CURVE_PROOFS','EXPLICIT_LOCAL_BEFORE_OR_AFTER_RATE_CONVENTION_NOT_OFFICIAL_HOMOLOGATION','LOCAL_PROPORTIONAL_BENCHMARK_RELEASE_AND_COUPON_CONVENTION','SEPTEMBER2026_CAPTURED_SGS11_ONLY','SALE_GAIN_RECORDED_BUT_TAX_TREATMENT_PENDING','VARIABLE_POSITION_NOT_YET_IN_FINANCIAL_BASE_OR_MONTHLY_XML','NO_FULL_PHASE4B_ACCEPTANCE'],
 'general_regressions':regressions,'predecessor25':{'monthly_cases':24,'engine_cases':15,'xml_bodies':72,'financial_replays':12},
 'historical_artifacts':{'checked':len(baseline),'changed':changed},'preserved_initial_decimal_fixture_drafts':44,
 'gate':{'ready':False,'sources_valid':47,'blockers':len(g['blockers'])},
 'actions':{'signature_created':False,'transmission_performed':False,'period_closed':False,'posting_performed':False,'variable_position_tax_activated':False},
 'logs_sha256':{n:sha(E/n) for n in ['tests.log','phpstan.log','pint.log','general-regressions.log','predecessor-yield-regression.log']}}
out=E/'verification-report.json'
if out.exists():assert read(out)==report
else:out.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(f'Candidate26 acceptance PASS:1044 tests/7937 assertions;44 cases/16 replays/6 guards;47 sources;{len(baseline)} historical artifacts preserved.')
