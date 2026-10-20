"""Install audited105-107 namespace-only and an own108 sandbox bundle; no historical resource rewrite."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
E=Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019')
R=Path(r'D:\ProjetoSaasExports\phase4b-iss-date105-primary-research-20261019')
P=Path(r'D:\ProjetoSaasExports\phase4b-iss-date105-local-preparation-20261019')
Q=Path(r'D:\ProjetoSaasExports\phase4b-iss-date106-result-composition-20261019')
S=Path(r'D:\ProjetoSaasExports\phase4b-identity107-dated-preparation-20261019')
V='2026.10.19-108-draft1'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as f:return hashlib.file_digest(f,'sha256').hexdigest()
def canonical(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as f:json.dump(v,f,ensure_ascii=False,indent=2)
assert (E/'before-integration-archive.json').exists()
paths=[F/f'{d}/{stem}-{V}.json' for d,stem in [('catalogs','rtc-codes'),('facts','consumption-phase4b'),
    ('questions','consumption-integrated'),('production','phase4b-controls'),('bundles','consumption-phase4b')]]
assert not any(p.exists() for p in paths)
installed={}
for base,name,expected in [(P,'PartialExportIssMaterialDate105.php','2a94bad8d281fda0b80131f83dd8f49ff409311000c2469663a69a3e4a13124e'),
    (Q,'PartialExportIssDatedResults106.php','38d731f9c12ee759a559735a9f23d69086e72a12f24da76d206a887f78b42585'),
    (S,'PartialExportDatedDpsIdentity107.php','07c927896509d0649dca30de03bb598599b61b6db46b6a2213fda53ea5f7b539')]:
    assert sha(base/name)==expected
    target=ROOT/'app/Modules/Fiscal/ConsumptionReform'/name
    raw=(base/name).read_bytes();assert raw.count(b'namespace Phase4bPrepared;')==1
    with target.open('xb') as f:f.write(raw.replace(b'namespace Phase4bPrepared;',b'namespace App\\Modules\\Fiscal\\ConsumptionReform;'))
    installed[target.relative_to(ROOT).as_posix()]=sha(target)
for base,source,name in [(P,'date105-cases.json','dated105-cases.json'),(Q,'dated106-cases-v2.json','dated106-cases.json'),
    (S,'identity107-cases-v2.json','dated107-cases.json')]:
    target=ROOT/'tests/Fixtures'/name;assert not target.exists();shutil.copy2(base/source,target)
    assert sha(target)==sha(base/source);installed[target.relative_to(ROOT).as_posix()]=sha(target)
bundle=read(F/'bundles/consumption-phase4b-2026.10.19-104-draft1.json')
codes=read(F/bundle['resource_paths']['rtc_code_catalog']);facts=read(F/bundle['resource_paths']['fact_definitions'])
questions=read(F/bundle['resource_paths']['questions']);controls=read(F/bundle['production_controls']['path'])
policy=read(S/'identity107-cases-v2.json')['cases'][0]['policy']
assert policy['contract']=='DPS-PARTIAL-EXPORT-DATED-IDENTITY-2026.10.19-107'
sources={}
for source in read(R/'primary-source-observation.json')['sources']:
    src=R/source['file'];assert sha(src)==source['sha256']
    target=F/'sources/official'/('dated108-'+src.name)
    assert not target.exists();shutil.copy2(src,target);assert sha(target)==source['sha256']
    sid='DATED108_'+src.stem.upper().replace('-','_')
    bundle['official_sources'].append({'id':sid,'url':source['url'],'file':target.name,'sha256':source['sha256'],
        'version':'OBSERVED_2026.10.19','observed_at':source['observed_at'],'published_on':None,'effective_from':None,
        'proven_effective_scope':'Local material/result/identity binding only;historical legal applicability not approved.'})
    codes['sources']['dated108_'+src.stem]={'source_id':sid,'file':target.name,'sha256':source['sha256'],
        'version':'OBSERVED_2026.10.19','retrieved_on':'2026-10-19'}
    sources[target.relative_to(ROOT).as_posix()]=sha(target)
metadata=R/'date105-norm-metadata-review.json'
assert sha(metadata)==policy['dated_results_policy']['material_policy']['sources']['metadata_review']
target=F/'sources/research/dated108-metadata-review.json';target.parent.mkdir(parents=True,exist_ok=True)
assert not target.exists();shutil.copy2(metadata,target)
codes['local_preparation_records']={'dated108_metadata_review':{'path':target.relative_to(F).as_posix(),'sha256':sha(target),
    'kind':'LOCAL_RESEARCH_RECEIPT_NOT_OFFICIAL_NORM_OR_EFFECTIVE_DATE_APPROVAL'}}
installed[target.relative_to(ROOT).as_posix()]=sha(target)
codes['version']=V;codes['partial_export_dated_documents_policy']=policy
added=[('rtc.assess_export_dated_documents','BOOLEAN','Conferir material,resultados ISS e identidadeDPS por data própria de cada documento,sem aprovação legal/emissão?'),
    ('rtc.export_iss_material_records','ARRAY','Informe provas de prestação ou etapa realizadas por papel/documento,com origem capturada e ciclo de vida.'),
    ('rtc.export_iss_dated_result_records','ARRAY','Informe contextos95,provas97 e vínculo do mesmo evento aos registros materiais por dataISS.'),
    ('rtc.export_dated_dps_identity_records','ARRAY','Informe dois mapeamentos107 com competência por dataISS e hashes do par/documento datado.'),
    ('provider.iss_regime','STRING','Qual é o regimeISS explicitamente demonstrado para esta prestação? Não inferir do regimeRTC ou federal.'),
    ('provider.iss_regime_proof','ARRAY','Informe a prova de regimeISS com origem capturada,partes,período e ciclo de vida;declaração não autentica governo.')]
rank=max(q['rank'] for q in questions)
for i,(key,kind,prompt) in enumerate(added,1):
    assert not any(f['key']==key for f in facts)
    facts.append({'key':key,'type':kind,'material':True})
    questions.append({'fact_key':key,'answer_type':kind,'rank':rank+i,'prompt':prompt})
controls['version']=V
next(c for c in controls['controls'] if c['id']=='4B.7.DOCUMENT')['gap']+='108:105-107 ligados localmente por data própria;aplicabilidade material/regimes completos,câmbio,base/imposto eXML ainda sem aprovação.'
for role,value,path in zip(['rtc_code_catalog','fact_definitions','questions'],[codes,facts,questions],paths[:3]):
    save(path,value);bundle['resource_paths'][role]=path.relative_to(F).as_posix();bundle['content_hashes'][role]=canonical(value)
save(paths[3],controls);bundle['production_controls']={'path':paths[3].relative_to(F).as_posix(),'sha256':canonical(controls)}
bundle.update(predecessor_bundle_id=bundle['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,
    knowledge_available_at=datetime.now(timezone.utc).isoformat())
bundle['catalog_versions']['rtc_codes']=V;save(paths[4],bundle)
installed.update({p.relative_to(ROOT).as_posix():sha(p) for p in paths})
save(E/'dated108-bundle-installation.json',{'created_at':datetime.now(timezone.utc).isoformat(),'files':installed,'sources':sources,
    'component_changes':'NAMESPACE_ONLY_FROM_AUDITED105_106_107','added_facts':[x[0] for x in added],
    'official_sources':len(bundle['official_sources']),'source_effective_date_proven':False,'full_suite_complete':False,'production_ready':False})
print('PASS:108 own sandbox bundle,six facts/questions,namespace-only105-107 and76 sources;not integration acceptance',flush=True)
