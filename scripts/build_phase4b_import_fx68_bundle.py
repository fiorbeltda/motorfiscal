"""Own dated FX bundle with primary quote bytes and explicit transaction facts/questions."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,json
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
P=ROOT/'storage/app/phase4b-fx67-preparation-20261013'
E=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'
V='2026.10.14-68-draft1'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
    if p.exists():assert read(p)==v,p
    else:
        with p.open('x',encoding='utf-8') as f:json.dump(v,f,ensure_ascii=False,indent=2);f.write('\n')

bundle=read(F/'bundles/consumption-phase4b-2026.10.13-28-draft66.json')
target=F/('bundles/consumption-phase4b-'+V+'.json')
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
catalog=read(F/bundle['resource_paths']['rtc_code_catalog'])
sources={}
for currency in ['USD','EUR','AUD']:
    name='bcb-'+currency.lower()+'-20260920-28.json'
    marker=read(P/(name+'.observation.json'))
    data=(P/name).read_text(encoding='utf-8')
    assert hashlib.sha256(data.encode()).hexdigest()==marker['sha256']
    sources[currency]={'currency':currency,'raw_json':data,'sha256':marker['sha256'],'url':marker['url'],'observed_at':marker['observed_at']}
policy={'contract':'RTC-ORDINARY-IMPORT-FX-LOCAL-2026.10.13-68','status':'SANDBOX_CANDIDATE',
    'published_at':known,'valid_from':'2026-01-01','valid_to':'2033-12-31','quote_sources':sources,
    'legal_sources':{item['source']:item['source_sha256'] for item in read(P/'fx-research-contract.json')['excerpts']},
    'scope':'SYNTHETIC_TRANSACTION_BOUND_ORDINARY_DIGITAL_IMPORT_FX_LOCAL_ONLY','production_ready':False}
policy['source_hash']=digest(policy)
catalog['ordinary_import_fx']=policy;catalog['version']=V
catalog_path='catalogs/rtc-codes-'+V+'.json';save(F/catalog_path,catalog)
facts=read(F/bundle['resource_paths']['fact_definitions'])
questions=read(F/bundle['resource_paths']['questions'])
for key,type_,prompt in [
    ('rtc.import_fx_source_id','STRING','Qual fonte de cotação de venda foi selecionada para a moeda e data do fato gerador?'),
    ('rtc.import_fx_proof','ARRAY','Qual evidência vincula moeda, valor original, conversão em BRL, fornecedor, importador e documentos da operação?')]:
    assert not any(item['key']==key for item in facts)
    facts.append({'key':key,'type':type_,'material':True})
    questions.append({'fact_key':key,'prompt':prompt,'rank':155,'answer_type':type_})
for resource,value,path in [('fact_definitions',facts,'facts/consumption-phase4b-'+V+'.json'),
    ('questions',questions,'questions/consumption-integrated-'+V+'.json'),('rtc_code_catalog',catalog,catalog_path)]:
    save(F/path,value);bundle['resource_paths'][resource]=path;bundle['content_hashes'][resource]=digest(value)
controls=read(F/bundle['production_controls']['path']);controls['version']=V
controls_path='production/phase4b-controls-'+V+'.json';save(F/controls_path,controls)
bundle.update(predecessor_bundle_id=bundle['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
bundle['catalog_versions']['rtc_codes']=V
bundle['production_controls']={'path':controls_path,'sha256':digest(controls)}
save(target,bundle)
save(E/'bundle-preparation.json',{'version':V,'knowledge_available_at':known,'policy':policy,
    'new_facts':['rtc.import_fx_source_id','rtc.import_fx_proof'],'old66_preserved':True,'production_ready':False})
print('PASS:own68 FX policy, source bytes, facts/questions and controls published reproducibly')
