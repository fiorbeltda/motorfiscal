"""Declare exact documentary context fields without discarding them or widening unknown-fact admission."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib
import json

R=Path(__file__).resolve().parents[1];F=R/'resources/fiscal';E=Path(r'D:\ProjetoSaasExports\phase4b-price112-integration-20261019')
V='2026.10.19-112-draft2'
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def canonical(v):return hashlib.sha256(json.dumps(v,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8')as f:json.dump(v,f,indent=2,ensure_ascii=False)
bundle=read(F/'bundles/consumption-phase4b-2026.10.19-112-draft1.json')
facts=read(F/bundle['resource_paths']['fact_definitions']);questions=read(F/bundle['resource_paths']['questions'])
keys={f['key']for f in facts};rank=max(q['rank']for q in questions)
added=[('rtc.currency','STRING','Qual é a moeda adicional declarada no contexto documental? Não substitui a moeda da transaçãoRTC,nem qualifica cotação/conversão.'),
       ('rtc.payment_date','DATE','Qual é a data adicional declarada de pagamento? Não substitui a data de prestaçãoISS ou a data da operaçãoRTC,nem prova recebimento por si só.')]
unknown={}
for row in read(R/'tests/Fixtures/price111-native112-index.json')['cases']:
    c=read(R/row['path'])
    if c['expected_result']['status']=='LOCAL_ISS_DOCUMENT_PRICES_COMPOSED':
        extra={k:v for k,v in c['input']['input110']['facts'].items()if k not in keys and k!='provider.iss_regime_proof'}
        if extra:unknown[row['name']]=extra
assert {k for extra in unknown.values()for k in extra}=={'rtc.currency','rtc.payment_date'}
for i,(key,kind,prompt)in enumerate(added,1):
    assert key not in keys;facts.append({'key':key,'type':kind,'material':True})
    questions.append({'fact_key':key,'answer_type':kind,'rank':rank+i,'prompt':prompt})
codes=read(F/bundle['resource_paths']['rtc_code_catalog']);codes['version']=V
controls=read(F/bundle['production_controls']['path']);controls['version']=V
for role,value,relative in [('fact_definitions',facts,f'facts/consumption-phase4b-{V}.json'),
                          ('questions',questions,f'questions/consumption-integrated-{V}.json'),
                          ('rtc_code_catalog',codes,f'catalogs/rtc-codes-{V}.json')]:
    save(F/relative,value);bundle['resource_paths'][role]=relative;bundle['content_hashes'][role]=canonical(value)
target=F/f'production/phase4b-controls-{V}.json';save(target,controls)
bundle['production_controls']={'path':target.relative_to(F).as_posix(),'sha256':canonical(controls)}
bundle.update(predecessor_bundle_id=bundle['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,
              knowledge_available_at=datetime.now(timezone.utc).isoformat())
bundle['catalog_versions']['rtc_codes']=V;target=F/f'bundles/consumption-phase4b-{V}.json';save(target,bundle)
save(E/'typed-documentary-context-v2.json',{'created_at':datetime.now(timezone.utc).isoformat(),
 'first_failure':'Unknown fact in scenario:rtc.currency;no scenarios or final manifest written by the failed builder',
 'first_failure_receipt_sha256':sha(E/'scenario-build-v1.json'),
 'first_builder_sha256':sha(E/'build112-before-typed-context-upgrade.php'),
 'original_extra_facts':unknown,'new_fact_keys':[r[0]for r in added],
 'new_bundle':target.relative_to(R).as_posix(),'new_bundle_sha256':sha(target),
 'original_fields_or_values_discarded':False,'generic_unknown_fact_acceptance_enabled':False,
 'fields_used_to_rewrite_rtc_reference_currency_or_date':False,'historical_draft1_preserved':True,
 'production_ready':False})
print('PASS:112draft2 explicitly transports2 monetary/temporal business facts;draft1 and original values preserved',flush=True)
