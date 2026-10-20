"""Prepare own66 no-movement version publication only after accepted65 corpus."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,json
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-dere9121-66-no-movement-versions-20261013'
V='2026.10.13-28-draft66'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def save(p,value):
    if p.exists():
        assert read(p)==value,p
    else:
        with p.open('x',encoding='utf-8') as f:
            json.dump(value,f,ensure_ascii=False,indent=2)
            f.write('\n')

accepted=read(ROOT/'storage/app/phase4b-dere9121-65-version-corpus-20261013/version-corpus-development-validation.json')
assert accepted['local_corpus65_complete'] is True
runtime=read(ROOT/accepted['accepted_runtime']['path'])
assert sha(ROOT/accepted['accepted_runtime']['path'])==accepted['accepted_runtime']['sha256']
for item in runtime['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
E.mkdir(exist_ok=True)
bundle=read(F/'bundles/consumption-phase4b-2026.10.13-28-draft65.json')
catalog=read(F/bundle['resource_paths']['rtc_code_catalog'])
target=F/('bundles/consumption-phase4b-'+V+'.json')
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
policy=catalog['dere_public_offer_negative_base_local_settlement']
assert policy['allow_native_no_movement'] and policy['allow_public_event_versions'] and policy['require_public_period_history']
policy['published_at']=known
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
financial=read(ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013/financial-input.json')
financial.update(scenario_id='SYNTHETIC-D9121-66-NO-MOVEMENT-VERSIONS',knowledge_at=known,rule_bundle_id=bundle['bundle_id'])
financial['catalog_versions']=bundle['catalog_versions']
save(E/'financial-input.json',financial)
save(E/'bundle-preparation.json',{'version':V,'policy':policy,'predecessor65_preserved':True,
    'native_versions_captured':False,'runtime_changed':False,'production_ready':False})
print('PASS:own66 no-movement version bundle prepared after accepted65; runtime and old policies preserved')
