"""Synthetic local mappings over unchanged101 facts; independent composition oracles and refusal cases."""
from pathlib import Path
from datetime import datetime, timezone, timedelta
import copy
import hashlib
import json

ROOT=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-identity103-local-preparation-20261016')
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def canonical(v): return hashlib.sha256(json.dumps(v,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as stream: json.dump(v,stream,ensure_ascii=False,indent=2)
contract='DPS-PARTIAL-EXPORT-LOCAL-IDENTITY-2026.10.16-103'
available='2026-10-16T18:55:42.879759+00:00'
policy={'contract':contract,'available_at':available,'scope':'LOCAL_NUMERIC_CNPJ_SUPPLIER_OWN_APP_RESTRICTED_PAIR_ONLY',
    'sources':{'municipal_manual':'3b5e982be9298ecc72ee254e26eb6824dc13d2e05704a83f01aa7a8fd321916e',
    'contributor_manual':'ac2f36e34ff565cc36d67c5d67415c33cc09f27b2dea006e8da9bcd2ddce5581',
    'restricted_annex':'2ae2ac9f91efa9b64f0c9ed97acaf18bb7513b090fae39ba5bda59187e64d9e9',
    'production_annex_comparison_only':'de5bc492959eadc8bfa7540e16939995924f2188f743648eaf84d3b31e9eeb7c',
    'restricted_xsd':'6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc'},
    'remote_deployment_proven':False,'source_effective_date_proven':False}
source=ROOT/'resources/fiscal/scenarios/catalog-reference-2026.10.16-101-draft1-v2/export-brl-control.json'
facts=read(source)['facts']
def composed(d): return 'DPS'+d['issuer_municipality']+'2'+d['supplier_id']+d['series'].zfill(5)+d['number'].zfill(15)
def seal(record):
    record['proof']['identity_hash']=canonical(record['identity'])
    record['proof']['source_hash']=canonical({k:v for k,v in record['proof'].items() if k!='source_hash'})
records=[]
for index,role in enumerate(['RTC_FOREIGN','RTC_DOMESTIC']):
    ref=facts['rtc.export_foreign_document_id' if index==0 else 'rtc.export_domestic_document_id']
    identity={'model':'DPS','environment':'2','emission_channel':'OWN_APPLICATION','emitter_type':'1','registration_type':'2',
        'supplier_id':facts['rtc.supplier_id'],'issuer_municipality':facts['provider.municipality_ibge'],
        'series':'1','number':str(index+1),'issued_at':available,'competence_date':facts['rtc.operation_date']}
    identity['id']=composed(identity)
    record={'role':role,'document_reference':ref,'identity':identity,
        'proof':{'contract':contract,'operation_reference':facts['rtc.export_operation_reference'],'document_reference':ref,'role':role,
            'transaction_evidence_id':facts['rtc.export_transaction_evidence_id'],'supplier_id':identity['supplier_id'],
            'issuer_municipality':identity['issuer_municipality'],'observed_at':available,'evidence_id':'SYNTHETIC.LOCAL.MAPPING103.'+role}}
    seal(record);records.append(record)
base={'facts':facts,'records':records,'known_at':'2026-10-16T19:00:00+00:00','policy':policy}
cases=[]
def add(name,mutate=lambda c:None,reason=None,status='ACTION_REQUIRED',reseal=False):
    case=copy.deepcopy(base); mutate(case)
    if reseal:
        for r in case['records']: seal(r)
    case.update(name=name,expected_status='LOCAL_DPS_IDENTITY_PAIR_BOUND' if reason is None else status,
        expected_reason='DPS103_COMPOSITION_AND_LOCAL_MAPPING_ONLY' if reason is None else 'DPS103_'+reason)
    if reason is None:
        byrole={r['role']:r for r in case['records']}
        case['expected_ids']=[composed(byrole[r]['identity']) for r in ['RTC_FOREIGN','RTC_DOMESTIC']]
    cases.append(case)
def identity_change(c,key,value):
    c['records'][0]['identity'][key]=value
def composed_change(c,key,value):
    identity_change(c,key,value)
    d=c['records'][0]['identity']; d['id']=composed(d)
add('local-pair')
add('reversed-roles',lambda c:c['records'].reverse())
add('knowledge-at-source',lambda c:c.update(known_at=available))
add('knowledge-before-source-microsecond',lambda c:c.update(known_at='2026-10-16T18:55:42.879758+00:00'), 'SOURCE_NOT_YET_OBSERVED')
for offset in [-14,-3,14]:
    time=datetime.fromisoformat(available).astimezone(timezone(timedelta(hours=offset))).isoformat()
    add('equivalent-offset-'+str(offset),lambda c,t=time:c.update(known_at=t))
for value in ['2026-10-16','2026-02-30T12:00:00+00:00','2026-10-16T18:00:00+14:01','2026-10-16T24:00:00Z','2026-10-16T18:00:00+15:00']:
    add('bad-known-'+str(len(cases)),lambda c,v=value:c.update(known_at=v),'EXACT_KNOWLEDGE_TIMESTAMP_REQUIRED','INVALID')
for field in policy:
    add('missing-policy-'+field,lambda c,f=field:c['policy'].pop(f),'SOURCE_POLICY_NOT_BOUND')
add('policy-extra',lambda c:c['policy'].update(extra=True),'SOURCE_POLICY_NOT_BOUND')
for field in ['remote_deployment_proven','source_effective_date_proven']:
    add('policy-true-'+field,lambda c,f=field:c['policy'].update({f:True}),'SOURCE_POLICY_NOT_BOUND')
for field in policy['sources']:
    add('wrong-source-'+field,lambda c,f=field:c['policy']['sources'].update({f:'0'*64}),'SOURCE_POLICY_NOT_BOUND')
for share in ['0','100','-1','100.1',40,None]:
    add('bad-share-'+str(len(cases)),lambda c,v=share:c['facts'].update({'rtc.export_share_percent':v}),'CURRENT_PARTIAL_EXPORT_SCOPE_REQUIRED')
for field,value in [('rtc.market','DOMESTIC'),('rtc.operation_kind','SERVICE'),('rtc.supplier_country','US'),('provider.person_type','NATURAL_PERSON')]:
    add('route-'+field,lambda c,f=field,v=value:c['facts'].update({f:v}),'CURRENT_PARTIAL_EXPORT_SCOPE_REQUIRED')
for field in ['rtc.export_operation_reference','rtc.export_foreign_document_id','rtc.export_domestic_document_id','rtc.export_transaction_evidence_id']:
    for value in [None,'',"x\0"]:
        add('missing-fact-'+str(len(cases)),lambda c,f=field,v=value:c['facts'].update({f:v}),'CURRENT_REFERENCE_FACT_REQUIRED')
for field,value in [('rtc.operation_date','2026-02-30'),('rtc.supplier_id','ABC12345678901'),('rtc.supplier_id','12345678901'),('provider.municipality_ibge','SP')]:
    add('format-'+str(len(cases)),lambda c,f=field,v=value:c['facts'].update({f:v}),'CURRENT_FORMAT_SCOPE_REQUIRED','INVALID')
add('same-original-reference',lambda c:c['facts'].update({'rtc.export_domestic_document_id':c['facts']['rtc.export_foreign_document_id']}),'DISTINCT_ORIGINAL_REFERENCES_REQUIRED','INVALID')
add('missing-record',lambda c:c['records'].pop(),'TWO_IDENTITY_RECORDS_REQUIRED')
add('duplicate-role',lambda c:c['records'][1].update(role='RTC_FOREIGN'),'DUPLICATE_ROLE','INVALID')
add('bad-role',lambda c:c['records'][0].update(role='OTHER'),'EXACT_RECORD_CONTRACT_REQUIRED','INVALID')
add('extra-record-key',lambda c:c['records'][0].update(extra=1),'EXACT_RECORD_CONTRACT_REQUIRED','INVALID')
add('reference-sibling-swapped',lambda c:c['records'][0].update(document_reference=c['records'][1]['document_reference']),'ORIGINAL_DOCUMENT_REFERENCE_CONFLICT','INVALID')
add('identity-extra',lambda c:c['records'][0]['identity'].update(extra=1),'EXACT_IDENTITY_CONTRACT_REQUIRED','INVALID')
for field,value in [('model','NFSE'),('environment','1'),('emission_channel','WEB'),('emitter_type','2'),('registration_type','1')]:
    add('identity-scope-'+field,lambda c,f=field,v=value:identity_change(c,f,v),'EXPLICIT_LOCAL_EMITTER_CHANNEL_ENVIRONMENT_REQUIRED',reseal=True)
for field,value in [('supplier_id','22222222000191'),('issuer_municipality','3509502')]:
    add('identity-current-'+field,lambda c,f=field,v=value:identity_change(c,f,v),'CURRENT_SUPPLIER_MUNICIPALITY_CONFLICT','INVALID',True)
for value in ['1','00001','49999']:
    add('good-series-'+value,lambda c,v=value:composed_change(c,'series',v),reseal=True)
for value in ['0','00000','50000','69999','70000','79999','80000','89999','90000','99999','100000',1,True,'1.0',' 1','١']:
    add('bad-series-'+str(len(cases)),lambda c,v=value:identity_change(c,'series',v),'SERIES_OUTSIDE_OWN_APPLICATION_RANGE','INVALID',True)
for value in ['1','999999999999999']:
    add('good-number-'+value,lambda c,v=value:composed_change(c,'number',v),reseal=True)
for value in ['0','01','1000000000000000','-1','1.0',1,True,None]:
    add('bad-number-'+str(len(cases)),lambda c,v=value:identity_change(c,'number',v),'EXACT_NUMBER_REQUIRED','INVALID',True)
add('tampered-id',lambda c:identity_change(c,'id','DPS'+'0'*42),'ID_COMPONENTS_CONFLICT','INVALID',True)
for field,value in [('issued_at','2026-02-30T12:00:00Z'),('issued_at','2026-10-16'),('issued_at','2026-10-16T18:55:42+14:01'),('competence_date','2026-02-30')]:
    add('bad-declared-date-'+str(len(cases)),lambda c,f=field,v=value:identity_change(c,f,v),'EXACT_DECLARED_DATES_REQUIRED','INVALID',True)
for value in ['2026-10-16T19:00:00.000001Z','2026-10-16T18:55:42.879758Z']:
    add('issuance-window-'+str(len(cases)),lambda c,v=value:identity_change(c,'issued_at',v),'ISSUANCE_OUTSIDE_LOCAL_KNOWLEDGE_SCOPE',reseal=True)
add('declared-date-conflict',lambda c:identity_change(c,'competence_date','2026-09-26'),'DECLARED_COMPETENCE_CONFLICT','INVALID',True)
for field in records[0]['proof']:
    add('missing-proof-'+field,lambda c,f=field:c['records'][0]['proof'].pop(f),'EXACT_MAPPING_PROOF_REQUIRED')
for value in ['2026-10-16T18:55:42.879758Z','2026-10-16T19:00:00.000001Z','invalid']:
    add('proof-window-'+str(len(cases)),lambda c,v=value:c['records'][0]['proof'].update(observed_at=v),'MAPPING_PROOF_KNOWLEDGE_CONFLICT',reseal=True)
for field in ['contract','operation_reference','document_reference','role','transaction_evidence_id','supplier_id','issuer_municipality']:
    add('wrong-proof-'+field,lambda c,f=field:c['records'][0]['proof'].update({f:'OTHER'}),'MAPPING_PROOF_BINDING_CONFLICT','INVALID',True)
add('proof-identity-hash',lambda c:c['records'][0]['proof'].update(identity_hash='0'*64),'MAPPING_PROOF_BINDING_CONFLICT','INVALID')
add('proof-source-hash',lambda c:c['records'][0]['proof'].update(source_hash='0'*64),'MAPPING_PROOF_HASH_CONFLICT','INVALID')
add('proof-empty-evidence',lambda c:c['records'][0]['proof'].update(evidence_id=''),'MAPPING_PROOF_HASH_CONFLICT','INVALID',True)
def duplicate(c):
    d=c['records'][1]['identity'];d['series']='00001';d['number']='1';d['id']=composed(d)
add('same-canonical-id-despite-series-padding',duplicate,'DISTINCT_CANONICAL_DPS_IDS_REQUIRED','INVALID',True)
save(E/'identity103-cases.json',{'created_at':datetime.now(timezone.utc).isoformat(),'source_scenario':str(source),
    'source_sha256':hashlib.sha256(source.read_bytes()).hexdigest(),'synthetic_local_mappings_only':True,'cases':cases})
print('PASS:prepared '+str(len(cases))+' identity103 cases;no business fact or accepted snapshot edited')
