"""Reproduce September integration opt-in and preserve the independently prepared native fixtures."""
import copy,json,hashlib,subprocess
from pathlib import Path
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft38';PV='2026.10.08-28-draft37'
E=ROOT/'storage/app/phase4b-realization-third-sale-integrated-draft-20261008-28';S=F/('scenarios/tvm-third-sale-integrated-'+V)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
  if p.stat().st_size>1000000:
   before=hashlib.sha256(p.read_bytes()).hexdigest();r=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert r.returncode==0 and hashlib.sha256(p.read_bytes()).hexdigest()==before,p
b=read(F/('bundles/consumption-phase4b-'+PV+'.json'));c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/('bundles/consumption-phase4b-'+V+'.json');known=read(target)['knowledge_available_at'];c['version']=V
q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known;q['sale_recognition_policy']['published_at']=known;a=q['sale_recognition_policy']['analysis'];a['published_at']=known;a['history_policy']['published_at']=known;a['third_sale_book_rounding_policy']='PROVED_THIRD_PERIOD_AVERAGE_RELEASE_WITH_COVERED_AND_UNCOVERED_ROUNDING_8_2'
c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q));c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c);ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,ctrl);b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
for p in (F/('scenarios/tvm-third-sale-rounding-'+PV)).glob('*-current.json'):save(S/p.name,read(p))
print(json.dumps({'fixtures':12,'reproduced':True,'production_ready':False}))
