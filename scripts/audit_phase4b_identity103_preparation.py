"""Audit isolated103 native gates and source bindings; never publish installed fiscal acceptance."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-identity103-local-preparation-20261016')
P=Path(r'D:\ProjetoSaasExports\phase4b-identity102-primary-preparation-20261016')
F=Path(r'D:\ProjetoSaasExports\phase4b-catalog101-full-suite-20261016')
A=ROOT/'storage/app/phase4b-catalog101-development-20261016/catalog101-development-validation.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream:return hashlib.file_digest(stream,'sha256').hexdigest()
assert sha(A)=='900bd64cfa7a0eb7775974f936a9d12369929b12028c59fe50e3932a2ed11360'
accepted=read(A)
for p,h in accepted['runtime_hashes'].items():assert sha(ROOT/p)==h,p
frozen=read(F/'preflight.json')['input_hashes']
for p,h in frozen.items():assert sha(Path(p))==h,p
print('PASS:accepted101 '+str(len(frozen))+' inputs/'+str(len(accepted['runtime_hashes']))+' runtime hashes unchanged',flush=True)
source=read(E/'municipal-source-observation.json')
assert source['sha256']=='3b5e982be9298ecc72ee254e26eb6824dc13d2e05704a83f01aa7a8fd321916e'
assert source['original_equals_official_capture'] is True and source['pages']==6
assert sha(E/source['file'])==sha(Path(source['original_path']))==sha(E/'municipal-original-k.pdf')==source['sha256']
proof102=read(P/'identity102-research-validation-v2.json')
for row in proof102['sources']:assert sha(P/row['file'])==row['sha256']
manifest=read(E/'identity103-cases.json')
assert sha(Path(manifest['source_scenario']))==manifest['source_sha256']
quality={}
for label in ['native-v3','phpstan-v2','pint-v2']:
    gate=read(E/(label+'.json'));assert gate['exit_code']==0
    for channel in ['stdout','stderr']:assert sha(E/(label+'.'+channel))==gate[channel+'_sha256']
    for name,h in gate['native_file_hashes'].items():assert sha(E/name)==h,name
    quality[label]=gate
assert '[OK] No errors' in (E/'phpstan-v2.stdout').read_text(encoding='utf-8')
assert 'PASS' in (E/'pint-v2.stdout').read_text(encoding='utf-8')
native=read(E/'native-v3.stdout')
assert native['cases']==len(manifest['cases'])==128 and native['checks']==2350
assert sha(E/'native-v1.stdout')==sha(E/'native-v2.stdout')==sha(E/'native-v3.stdout')
statuses={}
for case,row in zip(manifest['cases'],native['results'],strict=True):
    assert case['name']==row['name']
    result=row['result']
    assert result['status']==case['expected_status'] and result['reason']==case['expected_reason']
    statuses[result['status']]=statuses.get(result['status'],0)+1
    for key in ['fiscal_document_identifiers_qualified','cnpj_check_digits_verified','municipal_authorization_verified',
        'cadastral_identity_authenticated','remote_uniqueness_verified','government_evidence_authenticated',
        'iss_supply_date_materially_qualified','documentary_currency_conversion_qualified','per_document_tax_amount_calculated',
        'document_obligation_complete','remote_deployment_proven','xml_generated','posting_authorized','production_ready']:
        assert result[key] is False
    if case['expected_status']=='LOCAL_DPS_IDENTITY_PAIR_BOUND':
        assert case['policy']['available_at']==source['observed_at']
assert statuses=={'LOCAL_DPS_IDENTITY_PAIR_BOUND':11,'ACTION_REQUIRED':59,'INVALID':58}
report={'status':'IDENTITY103_LOCAL_PAIR_PREPARATION_VERIFIED_NOT_INTEGRATED',
    'auditor_version':2,'auditor_sha256':sha(Path(__file__)),
    'created_at':datetime.now(timezone.utc).isoformat(),'source_capture_date':'2026-10-16','native_execution_date':'2026-10-19',
    'native_cases':128,'native_checks':2350,'native_statuses':statuses,'manual_original_matches_official':True,
    'manual_pages_read':6,'manual_identity_page_visually_reviewed':4,'source103':source,
    'research102_report_sha256':sha(P/'identity102-research-validation-v2.json'),
    'acceptance101_sha256':sha(A),'accepted101_inputs_unchanged':len(frozen),'accepted101_runtime_hashes_unchanged':len(accepted['runtime_hashes']),
    'native_file_hashes':quality['native-v3']['native_file_hashes'],'quality':quality,
    'source_policy_clock_bound':True,'original_document_references_preserved':True,
    'runtime_integrated':False,'new_pest_tests':0,'full_regression_run':False,'fiscal_identifiers_qualified':False,
    'government_evidence_authenticated':False,'xml_generated':False,'production_ready':False,
    'next_required':['Integrate only under own versioned policy after current RTC/ISS guards;do not rewrite historical proofs or references.',
        'Permanent tests and native dual-runner corpus/historical replays/full regression and audit before local acceptance.',
        'Keep cadastral/DV/municipal authorization/remote uniqueness and material supply-date/FX/base/XML requirements separate.']}
with (E/'identity103-preparation-validation.json').open('x',encoding='utf-8') as stream:json.dump(report,stream,ensure_ascii=False,indent=2)
print(json.dumps({k:report[k] for k in ['status','native_cases','native_checks','accepted101_inputs_unchanged','accepted101_runtime_hashes_unchanged','runtime_integrated']}),flush=True)
