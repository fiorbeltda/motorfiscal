"""Verify installed lifecycle/economic binding progress and preserve explicit remaining activation gates."""
from pathlib import Path
import hashlib,json,re
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-60-version-binding-20261013'
P=ROOT/'storage/app/phase4b-dere9121-59-version-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path':path.relative_to(ROOT).as_posix(),'sha256':sha(path)}

quality=[]
for label in ['installed60-binding-tests','installed60-binding-phpstan','installed60-format','installed60-financial-baseline-replay']:
    record=read(P/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(P/(label+'.log'))
    quality.append(ref(P/(label+'.json')))
text=re.sub(r'\x1b\[[0-9;]*m','',(P/'installed60-binding-tests.log').read_text(encoding='utf-8'))
assert '19 passed' in text and '58 assertions' in text
assert read(P/'installed60-financial-baseline-replay.log')['replay_matches'] is True
for label in ['capture','replay']:
    record=read(E/('native-business-'+label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/('native-business-'+label+'.log'))
native=read(E/'business-binding-native-result.json')
matched=native['cases']['matching-native-business']['result']
assert matched['status']=='LOCAL_ACTIVE_PUBLIC_BUSINESS_BOUND' and matched['total_vApur']=='98.17'
assert matched['active_event']['sequence']=='03' and len(matched['version_chain']['trace'])==4
assert matched['active_event_business_locally_reconciled'] and not matched['government_return_authenticated']
assert not matched['period_state_externally_verified'] and not matched['pgcc_receipt_externally_verified']
assert native['cases']['changed-economics']['result']['reason']=='RTC_DERE_PUBLIC_BINDING_ACTIVE_PUBLIC_BUSINESS_DIFFERS_FROM_REBUILT_MONTH'
assert not native['monthly_version_selection_integrated']
files=[ROOT/'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferEventVersionReconciler.php',
    ROOT/'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferActiveEventBusinessBinder.php',
    ROOT/'tests/Fiscal/Unit/DerePublicOfferEventVersions60Test.php',ROOT/'tests/Fiscal/Unit/DerePublicOfferActiveBusiness60Test.php',
    ROOT/'scripts/capture_phase4b_dere9121_60_business_binding.py',Path(__file__),ROOT/'docs/fiscal/phase4b-dere9121-60-active-business-binding.md']
with (E/'business-binding-development-validation.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'PERMANENT_PUBLIC_VERSION_SELECTOR_AND_NATIVE_BUSINESS_BINDING_VALIDATED_POLICY_AND_COMMAND_PENDING',
        'tests':19,'assertions':58,'quality':quality,'native_capture':ref(E/'business-binding-native-result.json'),
        'implementation':[ref(path) for path in files],'exact_native_replay':True,
        'monthly_version_selection_integrated':False,'new_temporal_policy_activated':False,
        'full_suite_after60_passed':False,'scope59_complete':False,'scope54_complete':False,
        'phase4b_complete':False,'production_ready':False},stream,indent=2)
print('PASS:19/58 tests,installed selector/native business binding,exact replay,original financial replay; activation pending')
