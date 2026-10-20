"""Verify policy-gated no-movement integration and historical migrations, leaving full-suite gate pending."""
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
import hashlib,json,re
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path':path.relative_to(ROOT).as_posix(),'sha256':sha(path)}

quality=[]
for label in ['settlement58-final-tests','command58-initial-tests','activation58-phpstan-all','activation58-pint-all',
    'native-monthly-capture','candidate25-regression','candidate26-regression','candidate27-regression',
    'financial58-replay','production58-check','bundle58-reproduction']:
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log')),label
    quality.append(ref(E/(label+'.json')))
for label,tests,assertions in [('settlement58-final-tests',50,167),('command58-initial-tests',15,108)]:
    log=re.sub(r'\x1b\[[0-9;]*m','',(E/(label+'.log')).read_text(encoding='utf-8'))
    assert str(tests)+' passed' in log and str(assertions)+' assertions' in log
assert read(E/'financial58-replay.log')['replay_matches'] is True
assert read(E/'production58-check.log')['ready'] is False
old=read(ROOT/'resources/fiscal/catalogs/rtc-codes-2026.10.13-28-draft54.json')
new=read(ROOT/'resources/fiscal/catalogs/rtc-codes-2026.10.13-28-draft58.json')
assert old['dere_negative_base_local_settlement']==new['dere_negative_base_local_settlement']
assert old['dere_public_offer_negative_base_local_settlement'].get('allow_native_no_movement') is None
assert new['dere_public_offer_negative_base_local_settlement']['allow_native_no_movement'] is True
accepted=read(ROOT/'storage/app/phase4b-dere9121-55-command-integration-20261013/monthly-development-validation.json')
archives={item['source_path']:E/Path(item['archive_path']).name for item in read(E/'source-activation.json')['sources']}
archives['app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php']=ROOT/'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013/before-no-movement-reader.php'
for item in accepted['implementation']:
    assert sha(archives.get(item['path'],ROOT/item['path']))==item['sha256'],item['path']
native=read(E/'native-monthly-validation.json')
assert len(native['cases'])==3
for case in native['cases']:
    report_path=E/(case['name']+'-monthly-report.json')
    assert sha(report_path)==case['report_sha256'] and case['exact_replay']
    report=read(report_path)
    proposal=report['negative_base_settlement']
    assert proposal==read(E/(case['name']+'-no-movement-settlement.json'))['result']
    assert proposal['composition']['calculation_input']['public_offer_debt_return']=='0.00'
    positive=(Decimal(700)/Decimal('1.01')).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
    assert proposal['assessed_pools']['1010I']['positive_base']==str(positive)=='693.07'
    assert proposal['ibs_nominal_proposal']=='0.64' and proposal['cbs_nominal_proposal']=='6.15'
    for event,path in report['event_outputs'].items():
        assert sha(ROOT/path)==case['xml_sha256'][event]
        assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
    assert not report['production_ready'] and not proposal['received_return_verified']
regression=read(E/'public-corpus56-regression.json')
assert len(regression['cases'])==20 and regression['xml_hashes']==120
files=[ROOT/'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php',
    ROOT/'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferCurrentBaseCompositionQualifier.php',
    ROOT/'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferNegativeBaseSettlementPlanner.php',
    ROOT/'tests/Fiscal/Unit/DerePublicOfferNoMovementReturn57Test.php',
    ROOT/'tests/Fiscal/Unit/DerePublicOfferNoMovementSettlement58Test.php',
    ROOT/'tests/Fiscal/Integration/DerePublicOfferNoMovementMonthly58Test.php',Path(__file__)]
with (E/'no-movement-integration-progress.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'POLICY_GATED_NATIVE_NO_MOVEMENT_MONTHLY_INTEGRATION_VALIDATED_FULL_SUITE_PENDING',
        'component_tests':50,'component_assertions':167,'command_tests':15,'command_assertions':108,
        'native_cases':3,'native_xmls':18,'exact_monthly_replays':3,'public56_regression_cases':20,
        'quality':quality,'implementation':[ref(path) for path in files],
        'full_suite_after58_complete':False,'scope57_complete':False,'scope54_complete':False,
        'phase4b_complete':False,'production_ready':False},stream,indent=2)
print('PASS:policy58 gating,three native no-movement closures,18 XMLs,old54 corpus and accepted55 archives; full suite pending')
