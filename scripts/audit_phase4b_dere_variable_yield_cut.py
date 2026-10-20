"""Accept variable-position integration without extending synthetic proof or tax activation claims."""
import hashlib,json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';E=ROOT/'storage/app/phase4b-variable-yield-20261006-27'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def log(n):return re.sub(r'\x1b\[[0-9;]*m','',(E/n).read_text(encoding='utf-8'))
assert re.search(r'1082 passed\s*\(8294 assertions\)',log('tests-final.log'))
assert '[OK] No errors' in log('phpstan-final.log')
assert re.search(r'PASS\s*\.+\s*373 files',log('pint-final.log'))
assert all(v==0 for v in read(E/'quality-final.json').values())
assert 'Verified13 partial RTC/3 general integration cases/8 replays/3 XML candidates' in log('general-regressions.log')
assert 'Candidate26 PASS:44 cases/16 replays/6 command guards' in log('predecessor-memory-regression.log')
assert 'Candidate25 PASS:24 monthly/15 engine cases;72 XML bodies;12 financial replays' in log('predecessor-yield-regression.log')
b=read(F/'bundles/consumption-phase4b-2026.10.06-27.json')
for key,path in b['resource_paths'].items():
 p=F/path;v=sorted([read(x) for x in p.rglob('*.json')],key=lambda r:r['rule_id']) if p.is_dir() else read(p)
 assert digest(v)==b['content_hashes'][key],key
assert digest(read(F/b['production_controls']['path']))==b['production_controls']['sha256']
for s in b['official_sources']:assert sha(F/'sources/official'/s['file'])==s['sha256']
assert len(b['official_sources'])==47
c=read(F/b['resource_paths']['rtc_code_catalog']);p=c['dere_debt_daily_memory_calculation']
assert p['rounding_contract']=='DERE_HALF_EVEN_8_2' and len(p['series']['rows'])==21
assert p['rounding_source_hash']=='639643d2eee1282bd7a8f104c492f74342bd51293845011aae2fb0a64b742d04'
assert sha(F/'sources/official'/p['official_data_file'])==p['official_data_sha256']
qual=c['financial_operation']['base']['public_offer_debt'];monthly=c['dere_monthly_closing_projection']['debt_yield_projection']
assert qual['contract']=='LC214-ART195-DAILY-MEMORY-QUALIFIED-2026.10.06-1'
assert qual['daily_memory_calculation']==p and monthly['qualification']==qual
assert monthly['contract']=='DERE-D2101-DAILY-MEMORY-YIELD-LOCAL-2026.10.06-1'
capture=read(E/'legal-research-capture.json')
assert capture['status']=='CAPTURED' and capture['sha256']==qual['source_hash']==sha(E/'lc214-camara-observed-20261006.html')
corpus=read(E/'corpus-final.json')
assert corpus['valid'] and len(corpus['monthly_cases'])==27 and len(corpus['engine_cases'])==11
assert corpus['xml_bodies']==120 and corpus['financial_replays']==20
reports=0
for case in corpus['monthly_cases']:
 path=E/(case['case']+'-monthly-report-final.json')
 if not path.exists():continue
 reports+=1;r=read(path);assert r['production_ready'] is False and r['projection']['period_closed'] is False
 for event,filename in r['event_outputs'].items():
  validation=r['event_validations'][event]
  assert sha(ROOT/filename)==validation['xml_sha256']
  assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
  assert validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
 d=r['projection']['debt_yield_projection'];assert d['contract']==monthly['contract'] and d['production_ready'] is False
 for q in d['qualified_entries'].values():
  assert q['position_mode']=='DAILY_MEMORY' and q['book_apur_amount']=='0.00' and q['tax_origin']=='D2101'
  assert q['normative_method_homologated'] is False and len(q['calculation']['daily_memory'])==30
  assert digest(q['calculation'])==q['calculation_hash']
  assert q['taxable_amount']==q['calculation']['monthly']['vApur']
assert reports==20
sample=read(E/'weighted-additional-purchase-monthly-report-final.json')['projection']['debt_yield_projection']
q=next(iter(sample['qualified_entries'].values()))
assert sample['total_vApur']=='138.06' and q['xml_title']['vSelic']=='148.06' and q['xml_title']['vSaldoContFin']=='16150.00'
first=read(E/'first-purchase-before-rate-monthly-report-final.json')['projection']['debt_yield_projection']
assert next(iter(first['qualified_entries'].values()))['first_acquisition'] is True
assert first['total_vApur']=='56.49'
different=read(E/'different-book-and-acquisition-cost-monthly-report-final.json')['projection']['debt_yield_projection']
assert next(iter(different['qualified_entries'].values()))['xml_title']['vSaldoContIni']=='10100.00'
for name in ['partial-sale-gain','complete-sale','offsetting-sale-gains','zero-accounting-gain-with-acquisition-difference']:
 assert read(E/('engine-'+name+'-result.json'))['reason_codes']==['RTC_FINANCIAL_ART195_DAILY_SALE_GAIN_TAX_TREATMENT_PENDING']
regressions={}
for family,total in [('consumption-phase4b',15),('consumption-integrated',3)]:
 r=read(ROOT/f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-27.json');assert r['valid'] and r['passed']==r['total']==total;regressions[family]=total
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
g=read(E/'production-assessment.json');assert not g['ready'] and g['official_source_hashes_valid']==47
report={'bundle_id':b['bundle_id'],'local_scope_validated':True,'phase4b_complete':False,
 'scope':'ART195_VARIABLE_DAILY_MEMORY_IN_FINANCIAL_BASE_AND_D2101_WITH_GROSS_BOOK_RECONCILIATION',
 'tests':{'passed':1082,'assertions':8294},'phpstan':'PASS','pint':{'status':'PASS','files':373},'corpus':corpus,
 'example':{'cost_initial':'10000.00','purchase':'6000.00','closing_acquisition_cost':'16000.00000000','closing_unit_cost':'10.66666667','vSelic':'148.06','vVarMensal':'150.00','vPisCofins':'10.00','D2101_vApur':'138.06','financial_net_base':'838.06','ibs_nominal':'0.84','cbs_nominal':'7.54','D1101_result_vApur':'1000.00'},
 'limitations':['SYNTHETIC_ISSUER_OFFER_MEMORY_METHOD_AND_BOOK_PROOFS','LOCAL_INTRADAY_BENCHMARK_CONVENTIONS_NOT_HOMOLOGATED','NONZERO_ACCOUNTING_OR_ACQUISITION_SALE_DIFFERENCE_TAX_TREATMENT_PENDING','INTRAMONTH_GROSS_ROUNDING_RESIDUAL_REQUIRES_EXPLICIT_RECONCILIATION','SEPTEMBER2026_CAPTURED_SGS11_ONLY','NO_OFFICIAL_PGCC_RECEIPT_D9121_SIGNATURE_OR_TRANSMISSION','NO_FULL_PHASE4B_ACCEPTANCE'],
 'general_regressions':regressions,'predecessor26':{'cases':44,'replays':16,'command_guards':6},'predecessor25':{'monthly_cases':24,'engine_cases':15,'xml_bodies':72,'replays':12},
 'historical_artifacts':{'checked':len(baseline),'changed':changed},'preserved_initial_priors_drafts':len(list((E/'draft-initial-priors').glob('*.json'))),
 'gate':{'ready':False,'sources_valid':47,'blockers':len(g['blockers'])},
 'actions':{'signature_created':False,'transmission_performed':False,'period_closed':False,'posting_performed':False,'sale_gain_tax_activated':False},
 'logs_sha256':{n:sha(E/n) for n in ['tests-final.log','phpstan-final.log','pint-final.log','general-regressions.log','predecessor-memory-regression.log','predecessor-yield-regression.log']}}
out=E/'verification-report.json'
if out.exists():assert read(out)==report
else:out.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(f'Candidate27 acceptance PASS:1082 tests/8294 assertions;38 cases/120 XML/20 replays;47 sources;{len(baseline)} historical artifacts preserved.')
