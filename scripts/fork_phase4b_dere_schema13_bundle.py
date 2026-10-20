"""Migrate the supported monthly subset to one unchanged official 1.3.0 archive."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
VERSION='2026.10.06-22'


def read(path):return json.loads((F/path).read_text(encoding='utf-8'))


def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()


def write(path,value):
    p=F/path
    if p.exists():assert read(path)==value,f'Refusing to overwrite {p}';return
    p.parent.mkdir(parents=True,exist_ok=True)
    with p.open('x',encoding='utf-8') as f:f.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')


old=read('bundles/consumption-phase4b-2026.10.06-21.json')
b=copy.deepcopy(old)
path=f'bundles/consumption-phase4b-{VERSION}.json'
available=read(path)['knowledge_available_at'] if (F/path).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
b.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}',version=VERSION,knowledge_available_at=available,predecessor_bundle_id=old['bundle_id'])
c=read(old['resource_paths']['rtc_code_catalog']);c['version']=VERSION
xsd=copy.deepcopy(c['dere_monthly_closing_projection']['debt_no_movement_projection']['xsd'])
for key in ['dere_monthly_closing_projection','dere_trial_balance_projection','dere_deduction_projection','dere_pgcc_projection','dere_contributor_projection']:
    c[key]['xsd']=copy.deepcopy(xsd)
c['dere_pgcc_projection']['schema_version']='v1_0_4'
c['dere_deduction_projection']['schema_version']='v0_0_2'
c['dere_deduction_projection']['scope']='MONTHLY_QUALIFIED_SINGLE_ACCOUNT_CORRESPONDENT_ART192_SCHEMA_1_3_0_ONLY'
cpath=f'catalogs/rtc-codes-{VERSION}.json';write(cpath,c)
b['resource_paths']['rtc_code_catalog']=cpath;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=VERSION
controls=read(old['production_controls']['path']);controls['version']=VERSION
for row in controls['controls']:
    if row['id']=='4B.7.DOCUMENT':row['gap']+=' Candidato22: seis eventos do subconjunto mensal original usam um unico pacoteXSD1.3.0, D1011v1.0.4 e D1121v0.0.2 com desdobramento por conta; continuacao exige historico vinculado a conta. Sem homologacao, recebimento, novas atividades ou planos referenciais.'
p=f'production/phase4b-controls-{VERSION}.json';write(p,controls);b['production_controls']={'path':p,'sha256':digest(controls)}
write(path,b);write(f'sources/official/phase4b-manifest-{VERSION}.json',{'version':VERSION,'retrieved_on':'2026-10-06','status':'SANDBOX_EVIDENCE_REVIEW_REQUIRED','artifacts':b['official_sources']})
for family in ['consumption-phase4b','consumption-integrated']:
    for p in sorted((F/f'scenarios/{family}-2026.10.06-21').glob('*.json')):
        v=json.loads(p.read_text(encoding='utf-8'));v.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available)
        write(f'scenarios/{family}-{VERSION}/{p.name}',v)
for folder,prefix in [('dere-d2101-no-movement-2026.10.06-21','debt-'),('dere-d1121-binding-2026.10.05-20','deduction-')]:
    for p in sorted((F/f'scenarios/{folder}').glob('*.json')):
        v=json.loads(p.read_text(encoding='utf-8'))
        if p.name.startswith('financial-scenario'):
            v.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available)
            if folder.startswith('dere-d1121'):write(f'scenarios/dere-schema13-{VERSION}/{p.name}',v)
            continue
        if prefix=='deduction-':
            v['identity']['generated_at']='2026-10-06T08:00:00.000-03:00'
            for d in v.get('deductions',{}).get('documents',[]):
                if 'previous_deduction' in d:
                    prev=d['previous_deduction'];prev['pgcc_account_code']=d['pgcc_account_codes'][0]
                    prev.pop('source_hash');prev['source_hash']=digest(prev)
        write(f'scenarios/dere-schema13-{VERSION}/{prefix}{p.name}',v)
base=read(f'scenarios/dere-schema13-{VERSION}/deduction-qualified-continuation.json')
for name,change in [('missing-previous-account',None),('wrong-previous-account','funding000'),('tampered-previous-account','correspondent000')]:
    v=copy.deepcopy(base);prev=v['deductions']['documents'][0]['previous_deduction']
    if change is None:prev.pop('pgcc_account_code')
    else:prev['pgcc_account_code']=change
    if name!='tampered-previous-account':prev.pop('source_hash');prev['source_hash']=digest(prev)
    else:prev['accumulated_deduction']='49.00'
    v['corpus_expectation']={'status':'ACTION_REQUIRED','reason':'RTC_DERE_DEDUCTIONS_1_3_PREVIOUS_ACCOUNT_UNPROVEN'}
    write(f'scenarios/dere-schema13-{VERSION}/{name}.json',v)
print('Candidate22:46 monthly scenarios; single official1.3.0 archive; predecessor resources frozen.')
