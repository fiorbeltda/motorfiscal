"""Reproduce successor41 with the July official source explicitly registered."""
import copy,json
from pathlib import Path
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.09-28-draft41';OLD='2026.10.08-28-draft40'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
b=read(F/('bundles/consumption-phase4b-'+OLD+'.json'));c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/('bundles/consumption-phase4b-'+V+'.json');known=read(target)['knowledge_available_at'];c['version']=V;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known;q['sale_recognition_policy']['published_at']=known;a=q['sale_recognition_policy']['analysis'];a['published_at']=known;a['history_policy']['published_at']=known;c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q));c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
prior=read(F/'bundles/consumption-phase4b-2026.10.08-28-draft39.json');ids={x['id'] for x in b['official_sources']};b['official_sources'] += [x for x in prior['official_sources'] if x['id'] not in ids];assert len(b['official_sources'])==49
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c);ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,ctrl);b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
for p in (F/('scenarios/tvm-historical-multiple-current-'+OLD)).glob('*-current.json'):save(F/('scenarios/tvm-historical-multiple-current-'+V)/p.name,read(p))
print(json.dumps({'reproduced':True,'official_sources':49,'production_ready':False}))
