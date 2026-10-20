"""Use a singleton ARRAY transport for regime proof;never widen FactDefinition or rewrite105."""
from pathlib import Path
from datetime import datetime, timezone
import copy
import hashlib
import json

ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
E=Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019');V='2026.10.19-108-draft3'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def canonical(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as f:json.dump(v,f,ensure_ascii=False,indent=2)
old=read(F/'bundles/consumption-phase4b-2026.10.19-108-draft2.json')
bundle=copy.deepcopy(old)
for role in ['fact_definitions','questions','rtc_code_catalog']:
    content=read(F/old['resource_paths'][role])
    if role=='fact_definitions':
        row=next(r for r in content if r['key']=='provider.iss_regime_proof');row['key']='provider.iss_regime_proof_records'
    elif role=='questions':
        row=next(r for r in content if r['fact_key']=='provider.iss_regime_proof');row['fact_key']='provider.iss_regime_proof_records'
        row['prompt']='Informe uma lista com exatamente uma prova de regimeISS,origem capturada,partes,período e ciclo de vida;sem autenticação presumida.'
    else:content['version']=V
    oldpath=Path(old['resource_paths'][role]);path=F/oldpath.parent/(oldpath.stem.replace('108-draft1','108-draft3')+'.json')
    save(path,content);bundle['resource_paths'][role]=path.relative_to(F).as_posix();bundle['content_hashes'][role]=canonical(content)
schema=read(F/'schemas/scenario-phase4a-public-v2.schema.json')
schema['$id']='https://fiscal-engine.local/schemas/scenario-dated108-v2.schema.json'
schema['properties']['facts']['properties']={'provider.iss_regime_proof_records':{'type':'array','minItems':1,'maxItems':1,'items':{'type':'object'}}}
path=F/'schemas/scenario-dated108-v2.schema.json';save(path,schema)
bundle['resource_paths']['scenario_schema']=path.relative_to(F).as_posix();bundle['content_hashes']['scenario_schema']=canonical(schema)
bundle.update(bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,predecessor_bundle_id=old['bundle_id'],knowledge_available_at=datetime.now(timezone.utc).isoformat())
bundle['catalog_versions']['rtc_codes']=V
manifest=F/f'bundles/consumption-phase4b-{V}.json';save(manifest,bundle)
seed=read(E/'scenario-seed.json');seed['source_bundle']=manifest.relative_to(F).as_posix();save(E/'scenario-seed-v3.json',seed)
save(E/'scenario-array-transport-v2-failure-review.json',{'created_at':datetime.now(timezone.utc).isoformat(),
    'second_failure':'FactDefinition ARRAY accepts lists only;wrapped object proof was rejected',
    'second_powershell_exit_code':1,'second_builder_archived_sha256':sha(E/'build108-scenarios-before-array-transport-fix.php'),
    'solution':'Singleton provider.iss_regime_proof_records list normalized only in108;105/106/107 and core fact typing unchanged',
    'new_bundle':manifest.relative_to(ROOT).as_posix(),'new_bundle_sha256':sha(manifest),
    'historical_drafts_preserved':True,'qualifier_or_fiscal_rules_changed':False,'production_ready':False})
print('PASS:108draft3 typed singleton proof list;historical schemas/drafts and core fact types preserved',flush=True)
