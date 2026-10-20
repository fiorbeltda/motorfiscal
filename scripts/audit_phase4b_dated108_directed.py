"""Directed108 integration audit; does not substitute expanded CLI corpus or full regression acceptance."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re

ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
E=Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019')
OLD=Path(r'D:\ProjetoSaasExports\phase4b-identity104-full-suite-20261019')
A=ROOT/'storage/app/phase4b-identity104-development-20261019/identity104-development-validation.json'
TARGET=E/'dated108-directed-validation.json'
assert not TARGET.exists()
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as f:return hashlib.file_digest(f,'sha256').hexdigest()
def canonical(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
assert sha(A)=='8f3d57986e7fea2f87ca317914bc1ecac90e7cfd719607839e88fa5128262bd1'
archive=read(E/'before-integration-archive.json');pre=read(OLD/'preflight.json')
allowed={'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php'}
changed=[]
for path,digest in pre['input_hashes'].items():
    p=Path(path)
    if sha(p)!=digest:
        rel=p.relative_to(ROOT).as_posix();changed.append(rel)
        assert rel in allowed and sha(E/'before-integration'/rel)==digest
assert set(changed)==allowed
runtime={**read(A)['runtime_hashes']}
for path in allowed:runtime[path]=sha(ROOT/path)
for name in ['PartialExportIssMaterialDate105.php','PartialExportIssDatedResults106.php','PartialExportDatedDpsIdentity107.php',
    'DatedDocumentFactProjection108.php']:
    path='app/Modules/Fiscal/ConsumptionReform/'+name;runtime[path]=sha(ROOT/path)
assert len(runtime)==83
for label in ['directed-v3','phpstan-v2','pint-v3','production-assess108']:
    gate=read(E/(label+'.json'))
    assert gate['exit_code']==0 and gate['runtime_hashes']==runtime,label
    for ch in ['stdout','stderr']:assert sha(E/(label+'.'+ch))==gate[ch+'_sha256']
log=(E/'directed-v3.stdout').read_text(encoding='utf-8')
count=re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)',log)
assert count and int(count[1])==832
assert '[OK] No errors' in (E/'phpstan-v2.stdout').read_text(encoding='utf-8')
assert re.search(r'PASS.*570 files',(E/'pint-v3.stdout').read_text(encoding='utf-8'))
assert read(E/'directed-v1.json')['exit_code']==1 and read(E/'pint-v1.json')['exit_code']==1
bundle_path=F/'bundles/consumption-phase4b-2026.10.19-108-draft3.json';bundle=read(bundle_path)
previous=read(F/'bundles/consumption-phase4b-2026.10.19-104-draft1.json')
assert bundle['status']=='SANDBOX_CANDIDATE' and len(bundle['official_sources'])==76
assert bundle['official_sources'][:71]==previous['official_sources']
for source in bundle['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
for role in ['rtc_rate_catalog','rtc_transition_catalog','municipal_parameters','service_catalog','rules']:
    assert bundle['resource_paths'][role]==previous['resource_paths'][role]
codes=read(F/bundle['resource_paths']['rtc_code_catalog']);before=read(F/previous['resource_paths']['rtc_code_catalog'])
for key in before:
    if key not in ['version','sources']:assert codes[key]==before[key],key
assert set(codes)-set(before)=={'local_preparation_records','partial_export_dated_documents_policy'}
for role,key in [('fact_definitions','key'),('questions','fact_key')]:
    new=read(F/bundle['resource_paths'][role]);old=read(F/previous['resource_paths'][role])
    assert new[:len(old)]==old and len(new)==len(old)+6
    assert 'provider.iss_regime_proof_records' in [r[key] for r in new]
    assert canonical(new)==bundle['content_hashes'][role]
assert canonical(codes)==bundle['content_hashes']['rtc_code_catalog']
schema=read(F/bundle['resource_paths']['scenario_schema'])
assert canonical(schema)==bundle['content_hashes']['scenario_schema']
original_schema=read(F/previous['resource_paths']['scenario_schema'])
assert {k:v for k,v in schema.items() if k not in ['$id','properties']}=={k:v for k,v in original_schema.items() if k not in ['$id','properties']}
assert {k:v for k,v in schema['properties']['facts'].items() if k!='properties'}==original_schema['properties']['facts']
assert schema['properties']['facts']['properties']=={'provider.iss_regime_proof_records':{
    'type':'array','minItems':1,'maxItems':1,'items':{'type':'object'}}}
metadata=codes['local_preparation_records']['dated108_metadata_review']
assert sha(F/metadata['path'])==metadata['sha256']==codes['partial_export_dated_documents_policy']['dated_results_policy']['material_policy']['sources']['metadata_review']
for directory,name,fixture in [
    ('phase4b-iss-date105-local-preparation-20261019','PartialExportIssMaterialDate105.php','dated105-cases.json'),
    ('phase4b-iss-date106-result-composition-20261019','PartialExportIssDatedResults106.php','dated106-cases.json')]:
    prepared=Path(r'D:\ProjetoSaasExports')/directory/name
    assert (ROOT/'app/Modules/Fiscal/ConsumptionReform'/name).read_bytes()==prepared.read_bytes().replace(
        b'namespace Phase4bPrepared;',b'namespace App\\Modules\\Fiscal\\ConsumptionReform;')
raw107=(Path(r'D:\ProjetoSaasExports\phase4b-identity107-dated-preparation-20261019')/'PartialExportDatedDpsIdentity107.php').read_bytes()
assert (E/'identity107-installed-before-pint.php').read_bytes()==raw107.replace(b'namespace Phase4bPrepared;',b'namespace App\\Modules\\Fiscal\\ConsumptionReform;')
manifest=read(ROOT/'tests/Fixtures/dated108-scenario-expectations.json')
assert len(manifest['cases'])==22 and manifest['full_upstream_corpus_complete'] is False
for case in manifest['cases']:
    assert sha(ROOT/case['path'])==case['scenario_sha256']
    scenario=read(ROOT/case['path']);facts=scenario['facts']
    assert scenario['rule_bundle_id']==bundle['bundle_id'] and scenario['catalog_versions']==bundle['catalog_versions']
    assert datetime.fromisoformat(scenario['knowledge_at'])>=datetime.fromisoformat(bundle['knowledge_available_at'])
    assert facts['rtc.operation_date']==case['rtc_operation_date']
    assert len(facts['provider.iss_regime_proof_records'])==1 and 'provider.iss_regime_proof' not in facts
assessment=read(E/'production-assess108.stdout');old_assess=read(OLD/'final-production-assess104.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid']==assessment['official_sources_checked']==76
assert len(assessment['blockers'])==124
assert set(assessment['blockers'])==set(old_assess['blockers'])|{'DATED_ISS_DPS_LOCAL_EVIDENCE_ONLY_NOT_LEGAL_OR_DOCUMENT_APPROVAL'}
report={'status':'DATED108_DIRECTED_INTEGRATION_VERIFIED_EXPANDED_CORPUS_AND_REGRESSION_PENDING',
    'created_at':datetime.now(timezone.utc).isoformat(),'auditor_sha256':sha(Path(__file__)),
    'directed_tests':int(count[1]),'directed_assertions':int(count[2]),'new_permanent_tests':599,
    'initial_engine_scenarios':22,'initial_engine_evaluations_in_tests':44,'cli_replays_completed':0,
    'phpstan_general_passed':True,'pint_general_files':570,'bundle':bundle_path.relative_to(F).as_posix(),
    'bundle_sha256':sha(bundle_path),'runtime_hashes':runtime,'predecessor104_unchanged_input_files':11131,
    'authorized_archived104_changes':changed,'predecessor104_acceptance_sha256':sha(A),
    'official_sources_valid':76,'production_blockers':124,
    'transport':'SINGLETON_LIST_WITH_ONE_REGIME_PROOF;NO_CORE_FACT_TYPE_CHANGE',
    'projection':'EXCLUDE_IDENTITY_HASH_CARRIER_AND_ONLY_FIVE_KNOWN_DERIVED_PRESENTATION_FACTS;BIND_ALL_PROVIDED_FACTS',
    'first_directed_failure_preserved':{'tests_failed':20,'tests_passed':801,'reason':'Integrated derived presentation facts changed qualification hashes;stable explicit projection added;fiscal expectations unchanged'},
    'installed107_change':'Namespace-only followed by Pint removing redundant same-namespace import/style;prepared107 preserved',
    'full_upstream_corpus_complete':False,'full_suite_complete':False,'development_acceptance_published':False,
    'historical_legal_applicability_approved':False,'document_obligation_complete':False,
    'xml_generated':False,'phase4b_complete':False,'production_ready':False}
with TARGET.open('x',encoding='utf-8') as f:json.dump(report,f,ensure_ascii=False,indent=2)
print(json.dumps({k:report[k] for k in ['status','directed_tests','directed_assertions','new_permanent_tests',
    'predecessor104_unchanged_input_files','official_sources_valid','production_blockers']}),flush=True)
