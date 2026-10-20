"""Audit source-bound prototype conversion and independent decimal results, preserving integration boundaries."""
from pathlib import Path
from decimal import Decimal,ROUND_HALF_UP
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx67-preparation-20261013'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def ref(p):return {'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)}

research=read(E/'fx-research-contract.json')
for item in research['excerpts']+research['closing_packets']:
    assert sha(ROOT/item['path'])==item['sha256']
for item in read(E/'primary-source-observation.json')['sources']:
    assert item['status']=='CAPTURED' and sha(ROOT/item['path'])==item['sha256']
labels=['fx67-v2-capture','fx67-post-format-replay','fx67-final-pint','fx67-final-phpstan']
for label in labels:
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log'))
    if label in ['fx67-v2-capture','fx67-post-format-replay']:
        assert (E/(label+'.log')).read_text(encoding='utf-8').count('PASS:')==23
native=read(E/'fx-native-v2-result.json')
assert len(native['cases'])==23
positive=negative=0
for name,case in native['cases'].items():
    result=case['result']
    assert result['production_ready'] is False
    if case['expected_reason'] is None:
        positive+=1
        assert result['status']=='LOCAL_SOURCE_BOUND_IMPORT_FX_CONVERSION'
        product=Decimal(case['amount'])*Decimal(result['selected_quote']['rate'])
        assert Decimal(result['product_exact'])==product
        assert Decimal(result['intermediate_brl'])==product.quantize(Decimal('.00000001'),rounding=ROUND_HALF_UP)
        assert result['converted_gross_brl']==str(product.quantize(Decimal('.01'),rounding=ROUND_HALF_UP))==case['expected_brl']
        assert result['side']=='SALE' and result['bulletin']=='Fechamento'
        packet=read(E/(case['currency'].lower()+'-closing-packet.json'))
        row=next(r for r in packet['rows'] if r['quote_date']==result['selected_quote']['quote_date'])
        assert Decimal(row['sale_brl_per_unit'])==Decimal(result['selected_quote']['rate'])
        assert result['raw_source_sha256']==packet['raw_sha256']
        assert not result['tax_or_credit_authorized'] and not result['operation_document_binding_verified']
    else:
        negative+=1
        assert result['status']=='ACTION_REQUIRED' and result['reason']=='RTC_IMPORT_FX_'+case['expected_reason']
assert (positive,negative)==(6,17)
boundary=native['cases']['rounding-boundary']['result']
assert boundary['converted_gross_brl']=='82.44' and boundary['intermediate_brl']=='82.44500000'
assert Decimal(boundary['intermediate_brl']).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)==Decimal('82.45')
accepted=read(ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013/period-development-validation.json')
for item in accepted['implementation']:
    assert sha(ROOT/item['path'])==item['sha256']
files=[E/'planned-fx-quote-converter.php',E/'exercise-fx-converter.php',ROOT/'scripts/capture_phase4b_fx67_primary_sources.py',
    ROOT/'scripts/prepare_phase4b_fx67_research.py',ROOT/'docs/fiscal/phase4b-next-scope-import-fx68.md',Path(__file__)]
with (E/'fx-preparation-validation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'OFFICIAL_QUOTE_CONVERTER_PROTOTYPE_VALIDATED_ENGINE_INTEGRATION_PENDING','positive_cases':6,'negative_cases':17,
        'exact_replay':True,'independent_decimal_rounding_verified':True,'native_capture':ref(E/'fx-native-v2-result.json'),
        'quality':[ref(E/(label+'.json')) for label in labels],'implementation':[ref(p) for p in files],
        'accepted63_sources_preserved':True,'app_installed':False,'policy_activated':False,'engine_integrated':False,
        'unquoted_currency_usd_cross_path_qualified':False,'non_usd_missing_previous_day_fallback_qualified':False,
        'operation_document_binding_verified':False,'scope68_complete':False,'phase4b_complete':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:23 FX prototype cases, exact replay, independent source/decimal checks and preserved runtime; engine/cross/document integration pending')
