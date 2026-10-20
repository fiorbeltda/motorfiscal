"""Accept integrated art195 constant-cost yield only after independent verification."""
import hashlib,json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';E=ROOT/'storage/app/phase4b-yield-20261006-25'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def log(n):return re.sub(r'\x1b\[[0-9;]*m','',(E/n).read_text(encoding='utf-8'))
assert re.search(r'998 passed\s*\(3315 assertions\)',log('tests.log'))
assert '[OK] No errors' in log('phpstan.log')
assert re.search(r'PASS\s*\.+\s*369 files',log('pint.log'))
assert all(v==0 for v in read(E/'quality.json').values())
assert 'Verified13 partial RTC/3 general integration cases/8 replays/3 XML candidates' in log('general-regressions.log')
assert 'Candidate24 PASS:22 cases/7 replays/3 command guards' in log('predecessor-calculation-regression.log')
assert 'Candidate23 PASS:37 cases,42 XML bodies' in log('predecessor-document-regression.log')
b=read(F/'bundles/consumption-phase4b-2026.10.06-25.json')
for key,path in b['resource_paths'].items():
 p=F/path;v=sorted([read(x) for x in p.rglob('*.json')],key=lambda r:r['rule_id']) if p.is_dir() else read(p)
 assert digest(v)==b['content_hashes'][key],key
assert digest(read(F/b['production_controls']['path']))==b['production_controls']['sha256']
for s in b['official_sources']:assert sha(F/'sources/official'/s['file'])==s['sha256']
assert len(b['official_sources'])==47
c=read(F/b['resource_paths']['rtc_code_catalog']);calc=c['dere_debt_constant_cost_calculation']
assert calc['rounding_contract']=='DERE_HALF_EVEN_8_2'
assert calc['rounding_source_hash']=='639643d2eee1282bd7a8f104c492f74342bd51293845011aae2fb0a64b742d04'
assert c['financial_operation']['base']['public_offer_debt']==c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']
assert c['financial_operation']['base']['public_offer_debt']['calculation']==calc
assert c['dere_trial_balance_projection']['d2101_origin_allowed'] is True
assert len(calc['series']['rows'])==21
assert sha(F/'sources/official'/calc['official_data_file'])==calc['official_data_sha256']
corpus=read(E/'corpus.json')
assert corpus['valid'] and len(corpus['monthly_cases'])==24 and len(corpus['engine_cases'])==15
assert corpus['xml_bodies']==72 and corpus['financial_replays']==12
for case in corpus['monthly_cases']:
 if not (E/(case['case']+'-monthly-report-final.json')).exists():continue
 r=read(E/(case['case']+'-monthly-report-final.json'));assert r['production_ready'] is False
 assert r['projection']['period_closed'] is False
 for event,path in r['event_outputs'].items():
  validation=r['event_validations'][event]
  assert sha(ROOT/path)==validation['xml_sha256']
  assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
  assert validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
 r=r['projection']['debt_yield_projection']
 assert r['production_ready'] is False and r['tax_totalization_performed'] is False
 for q in r['qualified_entries'].values():
  assert q['tax_origin']=='D2101' and q['book_apur_amount']=='0.00'
  assert digest(q['calculation'])==q['calculation_hash']
sample=read(E/'capped-yield-monthly-report-final.json')['projection']['debt_yield_projection']
assert sample['total_vApur']=='98.17'
q=next(iter(sample['qualified_entries'].values()))
assert q['xml_title']['vSelic']=='108.17' and q['xml_title']['vSaldoContFin']=='10150.00'
recovery=read(E/'original-export-recovery.json');assert len(recovery)==6
for row in recovery.values():assert sha(ROOT/row['preserved_file'])==row['sha256']
regressions={}
for family,total in [('consumption-phase4b',15),('consumption-integrated',3)]:
 r=read(ROOT/f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-25.json')
 assert r['valid'] and r['passed']==r['total']==total;regressions[family]=total
baseline=read(E/'historical-before.json')
changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
g=read(E/'production-assessment.json');assert not g['ready'] and g['official_source_hashes_valid']==47
report={'bundle_id':b['bundle_id'],'local_scope_validated':True,'phase4b_complete':False,
 'scope':'ART195_CONSTANT_COST_YIELD_IN_FINANCIAL_BASE_AND_D2101_WITH_UNIQUE_TAX_ORIGIN',
 'tests':{'passed':998,'assertions':3315},'phpstan':'PASS','pint':{'status':'PASS','files':369},'corpus':corpus,
 'example':{'generic_net_base':'700.00','cost_basis':'10000.00','vSelic':'108.17','vVarMensal':'150.00','vPisCofins':'10.00','D2101_vApur':'98.17','financial_net_base':'798.17','ibs_nominal':'0.80','cbs_nominal':'7.18','D1101_result_vApur':'1000.00','D1101_public_yield_vApur':'0.00'},
 'rounding':{'contract':'DERE_HALF_EVEN_8_2','source_sha256':calc['rounding_source_hash'],'legacy24_preserved':True},
 'limitations':['SYNTHETIC_POSITION_ISSUER_OFFER_AND_BOOK_PROOFS','CONSTANT_COST_FULL_MONTH_BRL_ONLY','NO_CHANGING_QUANTITY_OR_COST','NO_SALE_GAIN_MEMORY','NO_OFFICIAL_PGCC_RECEIPT_OR_D9121_RETURN','NO_FULL_PHASE4B_FAMILY_ACCEPTANCE','RN_MS1156_LABEL_DIVERGENCE_REMAINS_UNRESOLVED'],
 'general_regressions':regressions,'predecessor_regressions':{'24':'22 cases/7 replays/3 guards','23':'37 cases/42 XML/1 replay'},
 'historical_artifacts':{'checked':len(baseline),'changed':changed},'recovered_original_export_bodies':6,
 'gate':{'ready':False,'sources_valid':47,'blockers':len(g['blockers'])},
 'actions':{'signature_created':False,'transmission_performed':False,'period_closed':False,'tax_totalization_performed':False},
 'logs_sha256':{n:sha(E/n) for n in ['tests.log','phpstan.log','pint.log','general-regressions.log','predecessor-calculation-regression.log','predecessor-document-regression.log']}}
out=E/'verification-report.json'
if out.exists():assert read(out)==report
else:out.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(f"Candidate25 acceptance PASS:998 tests/3315 assertions;39 cases/72 XML/12 replays;47 sources;{len(baseline)} historical artifacts preserved.")
