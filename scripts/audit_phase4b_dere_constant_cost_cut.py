"""Accept only reproducible constant-cost arithmetic; retain all fiscal activation gaps."""
import hashlib,json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';E=ROOT/'storage/app/phase4b-constant-cost-20261006-24'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def log(n):return re.sub(r'\x1b\[[0-9;]*m','',(E/n).read_text(encoding='utf-8'))
assert re.search(r'959 passed\s*\(3108 assertions\)',log('tests.log'))
assert '[OK] No errors' in log('phpstan.log')
assert re.search(r'PASS\s*\.+\s*366 files',log('pint.log'))
assert all(v==0 for v in read(E/'quality.json').values())
assert 'Verified13 partial RTC/3 general integration cases/8 replays/3 XML candidates' in log('general-regressions.log')
assert 'Candidate23 PASS:37 cases,42 XML bodies' in log('predecessor-regression.log')
b=read(F/'bundles/consumption-phase4b-2026.10.06-24.json')
for key,path in b['resource_paths'].items():
 p=F/path;v=sorted([read(x) for x in p.rglob('*.json')],key=lambda r:r['rule_id']) if p.is_dir() else read(p);assert digest(v)==b['content_hashes'][key],key
assert digest(read(F/b['production_controls']['path']))==b['production_controls']['sha256']
for s in b['official_sources']:assert hashlib.sha256((F/'sources/official'/s['file']).read_bytes()).hexdigest()==s['sha256']
assert len(b['official_sources'])==47
p=read(F/b['resource_paths']['rtc_code_catalog'])['dere_debt_constant_cost_calculation'];series=p['series']
assert series['series_code']==11 and series['unit']=='PERCENT_PER_BUSINESS_DAY' and len(series['rows'])==21
assert p['series_hash']==digest({k:v for k,v in series.items() if k!='source_hash'})==series['source_hash']
assert p['official_data_sha256']==hashlib.sha256((F/'sources/official'/p['official_data_file']).read_bytes()).hexdigest()
corpus=read(E/'corpus.json');assert corpus['valid'] and len(corpus['cases'])==22 and corpus['snapshots_replayed']==7 and corpus['command_guards']==3
assert read(E/'capped-yield-result.json')['result']['vSelic']=='108.17'
assert read(E/'capped-yield-result.json')['result']['vApur']=='98.17'
for row in corpus['cases']:
 r=read(E/(row['case']+'-result.json'));assert r['result']['production_ready'] is False
 if r['result']['status']=='RESOLVED':
  assert not r['result']['negative_base_created'] and not r['result']['tax_totalization_performed']
  assert digest(r['result'])==r['result_hash']
regressions={}
for cut in ['24','23']:
 regressions[cut]={}
 for family,total in [('consumption-phase4b',15),('consumption-integrated',3)]:
  r=read(ROOT/f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-{cut}.json');assert r['valid'] and r['passed']==r['total']==total;regressions[cut][family]=total
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed
g=read(E/'production-assessment.json');assert not g['ready'] and g['official_source_hashes_valid']==47
for n in ['fork_phase4b_dere_constant_cost_bundle.py','verify_phase4b_dere_constant_cost_cut.py']:compile((ROOT/'scripts'/n).read_text(encoding='utf-8'),n,'exec')
report={'bundle_id':b['bundle_id'],'local_scope_validated':True,'phase4b_complete':False,
 'tests':{'passed':959,'assertions':3108},'phpstan':'PASS','pint':{'status':'PASS','files':366},'corpus':corpus,
 'example':{'cost_basis':'10000.00','period':'2026-09','vSelic':'108.17','vVarMensal':'150.00','vPisCofins':'10.00','vApur':'98.17'},
 'scope':'CONSTANT_ACQUISITION_COST_FULL_MONTH_PER_TITLE_ARITHMETIC',
 'limitations':['SYNTHETIC_POSITION_PROOFS','NO_CHANGING_QUANTITY_OR_COST','NO_PUBLIC_OFFER_OR_FUND_CLASSIFICATION_IN_CALCULATOR','NO_MONTHLY_YIELD_XML_ACTIVATION','NO_FINANCIAL_BASE_OR_FINAL_DECISION_INTEGRATION','LOCAL_HALF_UP_FINAL_CENTS_ROUNDING_NOT_HOMOLOGATED'],
 'official_data':{'rows':21,'unit':'PERCENT_PER_BUSINESS_DAY','series':11,'file':p['official_data_file'],'sha256':p['official_data_sha256'],'observed_at':series['observed_at']},
 'integrated_regressions':regressions,'historical_artifacts':{'checked':len(baseline),'changed':changed},
 'gate':{'ready':False,'sources_valid':47,'blockers':len(g['blockers'])},
 'actions':{'signature_created':False,'transmission_performed':False,'period_closed':False,'tax_totalization_performed':False},
 'logs_sha256':{n:hashlib.sha256((E/n).read_bytes()).hexdigest() for n in ['tests.log','phpstan.log','pint.log','general-regressions.log','predecessor-regression.log']}}
out=E/'verification-report.json'
if out.exists():assert read(out)==report
else:out.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(f'Candidate24 acceptance PASS:959 tests/3108 assertions;22 cases/7 replays;47 sources;{len(baseline)} historical artifacts preserved.')
