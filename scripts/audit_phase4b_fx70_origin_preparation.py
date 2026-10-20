"""Audit actual material-origin prototype and preserve explicit lack of non-USD authority proof."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def ref(p):return {'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)}

labels=['origin70-final-replay','origin70-final-phpstan','origin70-final-pint2']
for label in labels:
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log'))
assert (E/'origin70-final-replay.log').read_text(encoding='utf-8').count('PASS:')==9
native=read(E/'origin-native-result.json')
assert len(native['cases'])==9
for name,case in native['cases'].items():
    result=case['result']
    assert result['production_ready'] is False
    if case['expected_reason'] is None:
        assert result['status']=='LOCAL_IMPORT_FX_TRANSACTION_BOUND' and result['converted_gross_brl']=='5179.50'
        if name=='old-policy':assert 'material_quote_origin' not in result
        else:assert result['material_quote_origin']['strategy']=='BCB_USD_BRL_PTAX'
    else:assert result['reason']=='RTC_IMPORT_FX_'+case['expected_reason']
preparation=read(E/'origin-guard-preparation.json')
current=ROOT/'app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php'
assert sha(current)==sha(E/'before-origin-transaction.php')==preparation['before_sha256']
limitation=read(E/'quote-origin-research-limitation.json')
assert limitation['non_usd_authority_origin_qualified'] is False
assert limitation['local_html_observation']['supports_rate_origin'] is False
assert sha(ROOT/limitation['local_html_observation']['path'])==limitation['local_html_observation']['sha256']
files=[E/'planned-origin-transaction.php',E/'exercise-origin-transaction.php',
    ROOT/'scripts/prepare_phase4b_fx70_origin_guard.py',ROOT/'scripts/prepare_phase4b_fx70_origin_exercise.py',
    ROOT/'docs/fiscal/phase4b-next-scope-fx-quote-origin70.md',Path(__file__)]
with (E/'origin-preparation-validation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'MATERIAL_ORIGIN_POLICY_PROTOTYPE_VALIDATED_NOT_ACTIVATED','cases':9,'exact_replay':True,
        'quality':[ref(E/(label+'.json')) for label in labels],'implementation':[ref(p) for p in files],
        'native_evidence':ref(E/'origin-native-result.json'),'current69_transaction_sha256':sha(current),
        'non_usd_authority_evidence_available':False,'local_primary_metadata_capture_successful':False,
        'app_activated':False,'scope70_complete':False,'phase4b_complete':False,'production_ready':False},f,indent=2)
print('PASS:nine material-origin cases, exact replay and quality; current69 unchanged, non-USD authority proof and activation pending')
