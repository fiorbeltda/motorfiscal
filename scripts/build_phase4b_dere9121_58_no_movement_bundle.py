"""Own58 policy publication for explicitly enabled no-movement public return composition."""
from pathlib import Path
from datetime import datetime,timezone
import json
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'
V='2026.10.13-28-draft58'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def save(path,value):
    if path.exists():
        assert read(path)==value,path
    else:
        with path.open('x',encoding='utf-8') as stream:
            json.dump(value,stream,ensure_ascii=False,indent=2)
            stream.write('\n')

bundle=read(F/'bundles/consumption-phase4b-2026.10.13-28-draft54.json')
catalog=read(F/bundle['resource_paths']['rtc_code_catalog'])
target=F/('bundles/consumption-phase4b-'+V+'.json')
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
policy=catalog['dere_public_offer_negative_base_local_settlement']
policy.update(published_at=known,allow_native_no_movement=True)
policy.pop('source_hash')
policy['source_hash']=digest(policy)
catalog['version']=V
catalog_path='catalogs/rtc-codes-'+V+'.json'
save(F/catalog_path,catalog)
controls=read(F/bundle['production_controls']['path'])
controls['version']=V
controls_path='production/phase4b-controls-'+V+'.json'
save(F/controls_path,controls)
bundle.update(predecessor_bundle_id=bundle['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
bundle['resource_paths']['rtc_code_catalog']=catalog_path
bundle['content_hashes']['rtc_code_catalog']=digest(catalog)
bundle['catalog_versions']['rtc_codes']=V
bundle['production_controls']={'path':controls_path,'sha256':digest(controls)}
save(target,bundle)
financial=read(ROOT/'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013/financial-input.json')
financial.update(scenario_id='SYNTHETIC-D9121-58-NO-MOVEMENT',knowledge_at=known,rule_bundle_id=bundle['bundle_id'])
financial['catalog_versions']=bundle['catalog_versions']
save(E/'financial-input.json',financial)
save(E/'bundle-preparation.json',{'version':V,'policy':policy,'old54_policy_preserved':True,
    'no_movement_native_monthly_validated':False,'production_ready':False})
print('PASS: own58 bundle/catalog/controls with explicit no-movement enablement; predecessor54 unchanged')
