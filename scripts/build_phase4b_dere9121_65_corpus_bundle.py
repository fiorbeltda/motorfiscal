"""Publish a reproducible own65 corpus bundle only after accepted63 quality and source hashes."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,json
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-dere9121-65-version-corpus-20261013'
V='2026.10.13-28-draft65'

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

accepted=read(ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013/period-development-validation.json')
assert accepted['full_suite_after63_complete'] is True
for item in accepted['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
E.mkdir(exist_ok=True)
bundle=read(F/'bundles/consumption-phase4b-2026.10.13-28-draft63.json')
catalog=read(F/bundle['resource_paths']['rtc_code_catalog'])
target=F/('bundles/consumption-phase4b-'+V+'.json')
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
policy=catalog['dere_public_offer_negative_base_local_settlement']
assert policy['require_public_period_history'] and policy['allow_public_event_versions']
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
manifest=read(ROOT/'storage/app/phase4b-dere9121-56-corpus-preparation-20261013/source-corpus-manifest.json')
for case in manifest['cases']:
    for key in ['source_month','source_financial_input','source_monthly_report','source_d2101']:
        assert sha(ROOT/case[key]['path'])==case[key]['sha256']
save(E/'source-corpus-manifest.json',{'status':'TWENTY_PRESERVED_PUBLIC_CASES_SELECTED_FOR_ACTIVE_VERSION_CORPUS',
    'predecessor63_acceptance_sha256':sha(ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013/period-development-validation.json'),
    'source56_manifest_sha256':sha(ROOT/'storage/app/phase4b-dere9121-56-corpus-preparation-20261013/source-corpus-manifest.json'),
    'cases':manifest['cases'],'isolated_synthetic_cases':True,'active_sequences':['01','03'],'production_ready':False})
save(E/'bundle-preparation.json',{'version':V,'policy':policy,'old63_policy_preserved':True,
    'runtime_changed':False,'version_corpus_captured':False,'production_ready':False})
print('PASS:own65 publication and twenty source-bound cases prepared; runtime and accepted63 preserved')
