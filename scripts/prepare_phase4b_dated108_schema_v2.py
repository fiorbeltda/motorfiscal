"""New explicit regime-proof FactValue envelope schema; preserve108draft1 and prior schema unchanged."""
from pathlib import Path
from datetime import datetime, timezone
import copy
import hashlib
import json

ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
E=Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019')
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def canonical(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as f:json.dump(v,f,ensure_ascii=False,indent=2)
schema=read(F/'schemas/scenario-phase4a-public-v2.schema.json')
envelope=copy.deepcopy(schema['properties']['facts']['additionalProperties']['oneOf'][1])
# This one proof is an ARRAY fact represented by a JSON object; loader receives the full proof inside value.
# Other facts retain all existing schemas; this does not permit arbitrary top-level object facts.
envelope['properties']['value']={'type':'object'}
schema['properties']['facts']['properties']={'provider.iss_regime_proof':envelope}
schema['$id']='https://fiscal-engine.local/schemas/scenario-dated108-v1.schema.json'
path=F/'schemas/scenario-dated108-v1.schema.json';save(path,schema)
bundle=read(F/'bundles/consumption-phase4b-2026.10.19-108-draft1.json')
bundle.update(bundle_id='CONSUMPTION-PHASE4B-2026.10.19-108-draft2',version='2026.10.19-108-draft2',
    predecessor_bundle_id=bundle['bundle_id'],knowledge_available_at=datetime.now(timezone.utc).isoformat())
bundle['resource_paths']['scenario_schema']=path.relative_to(F).as_posix();bundle['content_hashes']['scenario_schema']=canonical(schema)
manifest=F/'bundles/consumption-phase4b-2026.10.19-108-draft2.json';save(manifest,bundle)
seed=read(E/'scenario-seed.json');seed['source_bundle']=manifest.relative_to(F).as_posix()
save(E/'scenario-seed-v2.json',seed)
save(E/'scenario-schema-v1-failure-review.json',{'created_at':datetime.now(timezone.utc).isoformat(),
    'first_builder_exit_code':255,'first_powershell_exit_code':1,
    'failure':'Scenario schema rejected object provider.iss_regime_proof;direct object also collides with FactValue.value detection',
    'failed_builder_archived_sha256':sha(E/'build108-scenarios-before-schema-fix.php'),
    'original_schema_sha256':sha(F/'schemas/scenario-phase4a-public-v2.schema.json'),
    'original_bundle108draft1_sha256':sha(F/'bundles/consumption-phase4b-2026.10.19-108-draft1.json'),
    'new_schema':path.relative_to(ROOT).as_posix(),'new_bundle':manifest.relative_to(ROOT).as_posix(),
    'new_schema_sha256':sha(path),'new_bundle_sha256':sha(manifest),
    'record_semantics_or_fiscal_qualifier_changed':False,'production_ready':False})
print('PASS:new explicit proof envelope schema and108draft2;historical schemas/draft1 preserved',flush=True)
