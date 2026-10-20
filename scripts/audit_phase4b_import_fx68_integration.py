"""Audit actual direct FX integration and generation boundaries; full quality remains a distinct gate."""
from pathlib import Path
from decimal import Decimal,ROUND_HALF_UP
import hashlib,json,re,sys
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'
P=ROOT/'storage/app/phase4b-fx67-preparation-20261013'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def ref(p):return {'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)}
def check(label):
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log')),label
    return re.sub(r'\x1b\[[0-9;]*m','',(E/(label+'.log')).read_text(encoding='utf-8'))

labels=['fx68-final-directed-tests','fx68-final-phpstan-all','fx68-final-pint-all','fx68-integrated-corpus-capture',
    'fx68-historical-integrated-replays','fx68-candidate27-regression','fx68-production-check','fx68-bundle-reproduction']
for label in labels:
    text=check(label)
    if label=='fx68-final-directed-tests':assert '73 passed' in text and '301 assertions' in text
    if label in ['fx68-candidate27-regression','fx68-bundle-reproduction']:assert 'PASS' in text
assessment=read(E/'fx68-production-check.log')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
for item in read(P/'fx-preparation-validation.json')['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
activation=read(E/'engine-fx-activation.json')
assert sha(ROOT/activation['archive_path'])==activation['before_sha256']
bundle=read(ROOT/'resources/fiscal/bundles/consumption-phase4b-2026.10.14-68-draft1.json')
catalog=read(ROOT/'resources/fiscal'/bundle['resource_paths']['rtc_code_catalog'])
policy=catalog['ordinary_import_fx']
material=dict(policy);material.pop('source_hash')
assert digest(material)==policy['source_hash']
for currency,source in policy['quote_sources'].items():
    assert hashlib.sha256(source['raw_json'].encode()).hexdigest()==source['sha256']
    assert source['sha256']==sha(P/('bcb-'+currency.lower()+'-20260920-28.json'))
old=read(ROOT/'resources/fiscal/catalogs/rtc-codes-2026.10.13-28-draft66.json')
assert 'ordinary_import_fx' not in old
for key in ['dere_public_offer_negative_base_local_settlement','dere_negative_base_local_settlement']:
    assert old[key]==catalog[key]
corpus=read(E/'integrated-corpus-validation.json')
assert len(corpus['cases'])==14 and corpus['exact_replays']==14
manifest=read(E/'scenario-manifest-v3.json')
for case in corpus['cases']:
    name=case['name'];path=E/(name+'-integrated-snapshot.json')
    assert sha(path)==case['snapshot_sha256'] and case['exact_replay']
    capture=read(E/(name+'-integrated-capture.json'));replay=read(E/(name+'-integrated-replay.json'))
    for label,record in [(name+'-integrated-capture',capture),(name+'-integrated-replay',replay)]:
        assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log'))
    assert read(E/(name+'-integrated-replay.log'))['replay_matches'] is True
    snapshot=read(path);trace={r['stage']:r for r in snapshot['result']['trace']}
    assert case['expected_reason'] in snapshot['result']['reason_codes']
    if case['nominal_calculated']:
        fx=trace['IMPORT_FX_TRANSACTION'];conversion=fx['conversion']
        gross=(Decimal(conversion['original_amount'])*Decimal(conversion['selected_quote']['rate'])).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
        assert str(gross)==conversion['converted_gross_brl']==fx['converted_gross_brl']
        assert snapshot['input']['facts']['operation.amount']==str(gross)
        if name=='brl-adjustment':gross+=10
        if name=='usd-partial':gross=(gross*Decimal('.25')).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
        assert Decimal(trace['IMPORT_BRAZIL_ALLOCATION']['brazil_taxable_base_brl'])==gross
        for key,rate in [('ibs_state','.001'),('ibs_municipal','0'),('cbs','.009')]:
            assert Decimal(trace['IMPORT_NOMINAL_CALCULATION'][key])==(gross*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
        assert fx['document_authenticated'] is False and fx['credit_appropriated'] is False and fx['production_ready'] is False
        assert 'IMPORT_LOCAL_DPS_PROJECTION' in trace
    else:assert 'IMPORT_NOMINAL_CALCULATION' not in trace
historical=read(E/'historical-integrated-replay-validation.json')
assert historical['count']==len(historical['cases'])==5
for case in historical['cases']:
    assert sha(ROOT/case['path'])==case['sha256'] and case['exact_replay']
    label='historical-'+Path(case['path']).stem
    assert sha(E/(label+'.log'))==case['log_sha256']
    assert read(E/(label+'.log'))['replay_matches'] is True
files=[ROOT/p for p in ['app/Modules/Fiscal/ConsumptionReform/ImportFxQuoteConverter.php',
    'app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php','app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'tests/Fiscal/Unit/ImportFxQuoteConverter68Test.php','tests/Fiscal/Unit/ImportFxTransaction68Test.php',
    'tests/Fiscal/Integration/ImportFxEngine68Test.php','scripts/build_phase4b_import_fx68_bundle.py',
    'scripts/capture_phase4b_import_fx68_corpus.py','scripts/verify_phase4b_import_fx68_historical_replays.py']]+[Path(__file__)]
full='--full' in sys.argv;counts=None
if full:
    counts=re.search(r'(\d+) passed \((\d+) assertions\)',check('fx68-full-suite'))
    assert counts and int(counts[1])==2207
    labels.append('fx68-full-suite')
with (E/('direct-fx-development-validation.json' if full else 'fx-integration-progress-validation.json')).open('x',encoding='utf-8') as f:
    json.dump({'status':'POLICY_GATED_DIRECT_QUOTE_IMPORT_FX_INTEGRATION_'+('FULL_QUALITY_VALIDATED' if full else 'VALIDATED_FULL_SUITE_PENDING'),
        'directed_tests':73,'directed_assertions':301,'integrated_cases':14,'exact_replays':14,'historical_integrated_replays':5,
        'quality':[ref(E/(label+'.json')) for label in labels],'implementation':[ref(p) for p in files],
        'native_evidence':ref(E/'integrated-corpus-validation.json'),'pre_fx_engine_archive':ref(ROOT/activation['archive_path']),
        'full_suite_complete':full,'full_tests':int(counts[1]) if counts else None,'full_assertions':int(counts[2]) if counts else None,
        'cross_currency_unquoted_path_complete':False,'input_credit_fx_corpus_complete':False,'import_document_obligation_proven':False,
        'scope68_complete':False,'phase4b_complete':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:14 direct FX decisions, independent tax arithmetic, five ISS/4A/credit replays and immutable migration; '+('full suite verified' if full else 'full suite pending')+'; broader68 pending')
