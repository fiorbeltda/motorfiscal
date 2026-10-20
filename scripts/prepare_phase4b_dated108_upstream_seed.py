"""Explicit expected paths for all30 predecessor export routes plus six earlier FX refusals."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT=Path(__file__).resolve().parents[1];E=Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019')
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
cases=[]
for c in read(ROOT/'tests/Fixtures/identity104-scenario-expectations-v2.json')['cases']:
    if not c['name'].startswith('upstream-'):continue
    name=c['name'];source=read(ROOT/c['path'])
    dated_status='LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND';dated_reason='DPS107_DATED_COMPOSITION_AND_MAPPING_NOT_ISSUANCE_APPROVAL'
    status='ACTION_REQUIRED';reason='RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN';subreason=None
    if 'wrong-route' in name:
        dated_status=dated_reason=None;reason='RTC_EXPORT_DATED108_PARTIAL_EXPORT_ROUTE_REQUIRED'
    elif 'temporal-combination' in name:
        dated_status=dated_reason=None;reason='RTC_EXPORT_DATED108_SEPARATE_TEMPORAL_CONTEXT_REQUIRED'
    elif 'duplicate-role' in name:
        status=dated_status='INVALID';reason=dated_reason='DPS107_DATED_MATERIAL_RESULTS_NOT_BOUND';subreason='ISS_DATED106_DUPLICATE_RESULT_ROLE'
    elif 'missing-proofs' in name or 'disabled-malformed-proofs' in name:
        dated_status='ACTION_REQUIRED';reason=dated_reason='DPS107_DATED_MATERIAL_RESULTS_NOT_BOUND';subreason='ISS_DATED106_TWO_DATED_RESULT_RECORDS_REQUIRED'
    elif 'national-proof-fails-first' in name:
        dated_status='ACTION_REQUIRED';reason=dated_reason='DPS107_DATED_MATERIAL_RESULTS_NOT_BOUND';subreason='ISS_DATED106_NATIONAL_RESULT_NOT_BOUND'
    elif c['reason']=='RTC_EXPORT_SP98_MUNICIPAL_RESULT_NOT_BOUND':
        dated_status='ACTION_REQUIRED';reason=dated_reason='DPS107_DATED_MATERIAL_RESULTS_NOT_BOUND';subreason='ISS_DATED106_MUNICIPAL_RESULT_NOT_BOUND'
    cases.append({'name':name,'original_path':c['path'],'original_sha256':sha(ROOT/c['path']),'source':source,
        'status':status,'reason':reason,'dated_status':dated_status,'dated_reason':dated_reason,'dated_subreason':subreason,
        'fx_refusal_before_nominal':False,'note':('New opt-in includes fresh national95/municipal97;old96 flags are not gates'
            if 'national-assessment-disabled' in name else 'Preserve original business facts and95/97 proof bodies;new material/identity proofs are synthetic')})
for c in read(ROOT/'storage/app/phase4b-partial-export-fx73-preparation-20261014/export-integrated-case-manifest.json')['cases']:
    if c['nominal_path']:continue
    source=read(ROOT/c['path'])
    cases.append({'name':'fx-refusal-'+c['name'],'original_path':c['path'],'original_sha256':sha(ROOT/c['path']),'source':source,
        'status':'ACTION_REQUIRED','reason':c['expected_reason'],'dated_status':None,'dated_reason':None,'dated_subreason':None,
        'fx_refusal_before_nominal':True,'note':'Original FX refusal must occur before dated-document qualification'})
assert len(cases)==36
with (E/'upstream-scenario-seed.json').open('x',encoding='utf-8') as f:
    json.dump({'created_at':datetime.now(timezone.utc).isoformat(),'cases':cases,
        'bundle':'bundles/consumption-phase4b-2026.10.19-108-draft3.json','production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:36 explicit expectations;all30 export routes and six original FX negatives',flush=True)
