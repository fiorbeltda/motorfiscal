"""Exclusive112 resources and lazy permanent fixtures; no historical resource or source rewrite."""
from pathlib import Path
from datetime import datetime,timezone
import copy
import hashlib
import json
import re
import shutil

R=Path(__file__).resolve().parents[1];F=R/'resources/fiscal'
BASE=Path(r'D:\ProjetoSaasExports');E=BASE/'phase4b-price112-integration-20261019'
P110=BASE/'phase4b-price110-local-preparation-20261019'
P111=BASE/'phase4b-iss-base111-primary-research-20261019'
V='2026.10.19-112-draft1'
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
def canonical(v):return hashlib.sha256(json.dumps(v,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8')as f:json.dump(v,f,indent=2,ensure_ascii=False)
archive=read(E/'before-integration-archive.json')
assert archive['accepted108_input_files_verified']==13395
assert sha(P111/'price111-local-component-validation.json')=='a544b1224b2d5fcacf4b34d7b8e26ae2509c7249a09ad26180cf79c4127f7740'
verified=read(P111/'price111-local-component-validation.json')
assert sha(P111/'native-v5.stdout')==verified['native_stdout_sha256']
native=read(P111/'native-v5.stdout')
prep110=read(P110/'price110-local-component-validation.json')
assert sha(P110/'price110-cases-v2.json')==prep110['fixture_sha256']
original110=read(P110/'price110-cases-v2.json')['cases']
captured110={r['name']:r['result']for r in native['upstream_controls']}
captured111={r['name']:r['result']for r in native['results']}
installed={}
for name,source in [('PartialExportContractPrices110.php',P110),('PartialExportIssPriceComposition111.php',P111)]:
    target=R/'app/Modules/Fiscal/ConsumptionReform'/name
    expected=(source/name).read_text().replace('namespace Phase4bPrepared;','namespace App\\Modules\\Fiscal\\ConsumptionReform;')
    assert target.read_text()==expected,name
    installed[target.relative_to(R).as_posix()]=sha(target)
policy={'contract':'PARTIAL-EXPORT-DOCUMENT-PRICE-TRANSPORT-2026.10.19-112',
        'available_at':'2026-10-19T18:25:35.572132+00:00','composition_policy':native['case_inputs'][0]['policy'],
        'price_proof_transport':'EXACTLY_ONE_CAPTURED_CONTRACT_PRICE_PROOF',
        'composition_proof_transport':'EXACTLY_ONE_CAPTURED_COMPOSITION_PROOF',
        'monetary_records_remain_qualification_facts':True,'legal_applicability_approved':False,'production_ready':False}
bundle=read(F/'bundles/consumption-phase4b-2026.10.19-108-draft3.json')
codes=read(F/bundle['resource_paths']['rtc_code_catalog']);facts=read(F/bundle['resource_paths']['fact_definitions'])
questions=read(F/bundle['resource_paths']['questions']);controls=read(F/bundle['production_controls']['path'])
schema=read(F/bundle['resource_paths']['scenario_schema'])
codes['version']=V;codes['partial_export_document_prices_policy']=policy
sources=[]
review=read(P111/'source-contract-v2/iss-base111-source-scope-review.json')
for row in review['primary_captures']:
    source=Path(row['raw_path']);assert sha(source)==row['sha256']
    target=F/'sources/official'/('price112-'+source.name);assert not target.exists()
    shutil.copy2(source,target);assert sha(target)==row['sha256']
    sid='PRICE112_'+source.stem.upper().replace('-','_')
    bundle['official_sources'].append({'id':sid,'url':row['url'],'file':target.name,'sha256':row['sha256'],
        'version':'OBSERVED_2026.10.19','observed_at':row['observed_at'],'published_on':None,'effective_from':None,
        'proven_effective_scope':'Scoped actual-price composition evidence only;no taxpayer/rate/FX/base or issuance approval.'})
    codes['sources']['price112_'+source.stem]={'source_id':sid,'file':target.name,'sha256':row['sha256'],
        'version':'OBSERVED_2026.10.19','retrieved_on':'2026-10-19'}
    sources.append({'path':target.relative_to(R).as_posix(),'sha256':sha(target)})
for name,source in [('price112-base111-source-review.json',P111/'source-contract-v2/iss-base111-source-scope-review.json'),
                    ('price112-local111-component-review.json',P111/'price111-local-component-validation.json')]:
    target=F/'sources/research'/name;assert not target.exists();shutil.copy2(source,target)
    codes.setdefault('local_preparation_records',{})[name.removesuffix('.json')]={
        'path':target.relative_to(F).as_posix(),'sha256':sha(target),'kind':'LOCAL_VERIFICATION_NOT_OFFICIAL_NORM_OR_LEGAL_APPROVAL'}
    installed[target.relative_to(R).as_posix()]=sha(target)
added=[('rtc.assess_export_document_prices','BOOLEAN','Conferir preços contratuais e composição por documento,sem aprovar baseISS,câmbio,imposto ou XML?'),
       ('rtc.export_contract_price_records','ARRAY','Informe duas linhas110 de preço contratual por papel/documento,com moeda e valores próprios.'),
       ('rtc.export_contract_price_proof_records','ARRAY','Informe lista de uma prova110 capturada de preços,com contexto,origem,período e ciclo de vida.'),
       ('rtc.export_price_composition_records','ARRAY','Informe duas composições111,com principal,acréscimos,descontos e base bruta/líquida explicitamente reconciliada.'),
       ('rtc.export_price_composition_proof_records','ARRAY','Informe lista de uma prova111 independente dos ajustes,ligada ao110 fresco e às datas/linhas/partes atuais.')]
rank=max(q['rank']for q in questions)
for i,(key,kind,prompt)in enumerate(added,1):
    assert not any(f['key']==key for f in facts)
    facts.append({'key':key,'type':kind,'material':True});questions.append({'fact_key':key,'answer_type':kind,'rank':rank+i,'prompt':prompt})
for key in ['rtc.export_contract_price_proof_records','rtc.export_price_composition_proof_records']:
    schema['properties']['facts']['properties'][key]={'type':'array','minItems':1,'maxItems':1,'items':{'type':'object'}}
schema['$id']='https://fiscal-engine.local/schemas/scenario-price112-v1.schema.json'
controls['version']=V
for c in controls['controls']:
    if c['id']in['4B.4.BASE_AND_SPECIAL_REGIMES','4B.7.DOCUMENT']:
        c['gap']+='112:preço110 e composição111 locais;FX/base/imposto,obrigação eXML continuam pendentes,não aprovados por este contrato.'
for role,value,relative in [
 ('rtc_code_catalog',codes,f'catalogs/rtc-codes-{V}.json'),('fact_definitions',facts,f'facts/consumption-phase4b-{V}.json'),
 ('questions',questions,f'questions/consumption-integrated-{V}.json'),('scenario_schema',schema,'schemas/scenario-price112-v1.schema.json')]:
    target=F/relative;save(target,value);bundle['resource_paths'][role]=relative;bundle['content_hashes'][role]=canonical(value)
    installed[target.relative_to(R).as_posix()]=sha(target)
target=F/f'production/phase4b-controls-{V}.json';save(target,controls)
bundle['production_controls']={'path':target.relative_to(F).as_posix(),'sha256':canonical(controls)}
installed[target.relative_to(R).as_posix()]=sha(target)
bundle.update(predecessor_bundle_id=bundle['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,
              knowledge_available_at=datetime.now(timezone.utc).isoformat())
bundle['catalog_versions']['rtc_codes']=V
target=F/f'bundles/consumption-phase4b-{V}.json';save(target,bundle);installed[target.relative_to(R).as_posix()]=sha(target)
fixture_index={}
for kind,cases,expected in [('110',original110,captured110),('111',native['case_inputs'],captured111)]:
    directory=R/'tests/Fixtures'/('price'+kind+'-native112');directory.mkdir(exist_ok=False)
    index=[]
    for i,case in enumerate(cases):
        name=f'{i:03d}-'+re.sub('[^a-z0-9-]','-',case['name'].lower())+'.json'
        target=directory/name;save(target,{'input':case,'expected_result':expected[case['name']]})
        index.append({'name':case['name'],'path':target.relative_to(R).as_posix(),'sha256':sha(target)})
    target=R/'tests/Fixtures'/('price'+kind+'-native112-index.json');save(target,{'cases':index,'source_is_synthetic':True})
    fixture_index[kind]={'path':target.relative_to(R).as_posix(),'sha256':sha(target),'cases':len(index)}
save(E/'resource-installation.json',{'created_at':datetime.now(timezone.utc).isoformat(),'files':installed,
     'official_sources':sources,'official_source_count':len(bundle['official_sources']),
     'new_fact_keys':[r[0]for r in added],'fixtures':fixture_index,
     'policy':policy,'namespace_only_from_verified110_111':True,'full_suite_complete':False,'production_ready':False})
print(json.dumps({'status':'112_RESOURCES_AND_LAZY_FIXTURES_INSTALLED','sources':len(bundle['official_sources']),
                  'facts_added':len(added),'native_fixtures':sum(v['cases']for v in fixture_index.values())}),flush=True)
