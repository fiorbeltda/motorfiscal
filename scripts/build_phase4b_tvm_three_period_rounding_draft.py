"""Prepare July first-acquisition sources for a full July/August/September rounding chain."""
import copy,hashlib,json,subprocess
from pathlib import Path
from datetime import datetime,timezone
from decimal import Decimal
from dere_daily_memory_oracle import digest,oracle,proof
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft35';E=ROOT/'storage/app/phase4b-realization-three-period-rounding-draft-20261008-28';S=F/('scenarios/tvm-three-period-rounding-'+V)
E.mkdir(parents=True,exist_ok=True);S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=sha(p)
 save(E/'historical-before.json',baseline)
b=read(F/'bundles/consumption-phase4b-2026.10.08-28-draft31.json');c=read(F/b['resource_paths']['rtc_code_catalog']);policy=read(F/'catalogs/tvm-public-chain-july-policy-draft-20261008.json');target=F/('bundles/consumption-phase4b-'+V+'.json');known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
assert sha(F/'sources/official'/policy['official_data_file'])==policy['official_data_sha256'];assert len(policy['series']['rows'])==23 and all(row['date'].startswith('2026-07') for row in policy['series']['rows'])
c['version']=V;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known;q['daily_memory_calculation']=copy.deepcopy(policy);a=q['sale_recognition_policy']['analysis'];a.update(published_at=known,daily_memory_calculation=copy.deepcopy(policy));a['history_policy']['published_at']=known;q['sale_recognition_policy']['published_at']=known;c['dere_debt_daily_memory_calculation']=copy.deepcopy(policy)
c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q));c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c);ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,ctrl);b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
for case in ['negative','positive','zero','exact']:
 f=read(F/('scenarios/tvm-public-explicit-closing-2026.10.08-28-draft32/prior-'+case+'-current.json'));p=f['position'];p.pop('memory_evidence');p['period']='2026-07';p['appropriations']={day['date'].replace('2026-08','2026-07'):day['appropriation'] for day in p['contract_curve'] if Decimal(day['appropriation'])}
 for op in p['operations']:op['date']=op['date'].replace('2026-08','2026-07')
 p['method_evidence']=proof({'contract':policy['contract'],'method':p['method']},'SYNTHETIC.THREE.PERIOD.JULY.METHOD','2026-07-01','2026-07-31');curve,monthly,independent=oracle(p,policy['series']);p.pop('appropriations');p.update(contract_curve=curve,declared_monthly=monthly);p['memory_evidence']=proof(p,'SYNTHETIC.THREE.PERIOD.JULY.MEMORY','2026-07-01','2026-07-31');f['independent_oracle']=independent;f['expected'].update(auxiliary_base=monthly['vApur'],base=format(Decimal(700)+Decimal(monthly['vApur']),'.2f'));save(S/('july-'+case+'-current.json'),f)
save(E/'preparation.json',{'status':'JULY_FIRST_ACQUISITION_PREPARATION_ONLY','bundle':V,'official_july_daily_rates':23,'source_cases':4,'next_periods':['2026-08','2026-09'],'planned_second_stage':'Consume old rounding and create separately tracked rounding on August partial sale','planned_third_stage':'Consume covered old rounding and economic created residue on September sale, proving any new rounding separately','captured_financial_sources':0,'monthly_tax_accepted':False,'full_three_month_chain_accepted':False,'scope28_completed':False,'production_ready':False})
print(json.dumps({'july_fixtures':4,'official_daily_rates':23,'full_three_month_chain_accepted':False}))
