"""Publish explicit researched-origin selection after accepted69 and installed70 guard, preserving old bundles."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,json
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
P=ROOT/'storage/app/phase4b-import-fx69-temporal-preparation-20261014'
V='2026.10.14-70-draft1'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
    if p.exists():assert read(p)==v,p
    else:
        with p.open('x',encoding='utf-8') as f:json.dump(v,f,ensure_ascii=False,indent=2);f.write('\n')

assert read(P/'temporal-integration-development-validation.json')['full_suite_complete']
assert (E/'material-origin-installation.json').exists()
bundle=read(F/'bundles/consumption-phase4b-2026.10.14-68-draft1.json')
catalog=read(F/bundle['resource_paths']['rtc_code_catalog'])
target=F/('bundles/consumption-phase4b-'+V+'.json')
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
policy=catalog['ordinary_import_fx']
primary=ROOT/'storage/app/phase4b-fx67-preparation-20261013/bcb-ptax-api.pdf'
origin={'status':'QUALIFIED_LOCAL_SOURCE_SELECTION','currency':'USD','strategy':'BCB_USD_BRL_PTAX',
    'quote_source_sha256':policy['quote_sources']['USD']['sha256'],'primary_evidence_sha256':hashlib.sha256(primary.read_bytes()).hexdigest(),
    'known_from':known,'scope':'BCB_USD_BRL_PUBLISHED_QUOTE_SOURCE_LOCAL_SELECTION_NOT_TRANSACTION_AUTHENTICATION'}
origin['source_hash']=digest(origin)
policy.update(published_at=known,require_material_quote_origin=True,qualified_quote_origins={'USD':origin})
policy.pop('source_hash');policy['source_hash']=digest(policy)
catalog['version']=V
path='catalogs/rtc-codes-'+V+'.json';save(F/path,catalog)
bundle['resource_paths']['rtc_code_catalog']=path;bundle['content_hashes']['rtc_code_catalog']=digest(catalog)
bundle['catalog_versions']['rtc_codes']=V
controls=read(F/bundle['production_controls']['path']);controls['version']=V
controls_path='production/phase4b-controls-'+V+'.json';save(F/controls_path,controls)
bundle['production_controls']={'path':controls_path,'sha256':digest(controls)}
bundle.update(predecessor_bundle_id=bundle['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
save(target,bundle)
save(E/'origin-bundle-preparation.json',{'version':V,'policy':policy,'qualified_material_origins':['USD'],
    'non_usd_numerical_sources_preserved_but_origin_unqualified':True,'cross_currency_qualified':False,
    'own_integrated_corpus_captured':False,'production_ready':False})
print('PASS:own70 origin policy published; non-USD numerical sources preserved with explicit qualification guard')
