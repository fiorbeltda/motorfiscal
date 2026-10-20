"""Verify reader fix migration and no-movement prototype arithmetic while leaving monthly activation pending."""
from pathlib import Path
from decimal import Decimal, ROUND_HALF_EVEN
import hashlib
import json
import re
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path':path.relative_to(ROOT).as_posix(),'sha256':sha(path)}

accepted = read(ROOT/'storage/app/phase4b-dere9121-55-command-integration-20261013/monthly-development-validation.json')
reader = ROOT/'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php'
archive = E/'before-no-movement-reader.php'
for item in accepted['implementation']:
    assert sha(archive if ROOT/item['path']==reader else ROOT/item['path']) == item['sha256']
before = archive.read_text(encoding='utf-8')
after = reader.read_text(encoding='utf-8')
expected = before.replace("$o.'/d:semTitulos)'", "$o.'/d:infoDebOpOfPublic/d:semTitulos)'")
assert re.sub(r'\s+', '', expected) == re.sub(r'\s+', '', after)
quality=[]
for label in ['reader57-initial-tests','reader57-final-phpstan','reader57-format','no-movement-prototypes-final-phpstan']:
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log'))
    quality.append(ref(E/(label+'.json')))
text=re.sub(r'\x1b\[[0-9;]*m','',(E/'reader57-initial-tests.log').read_text(encoding='utf-8'))
assert '28 passed' in text and '145 assertions' in text
cases=[]
for name,label in [('qualified-direct-only','direct'),('qualified-fund-only','fund'),('qualified-both-codes','both')]:
    for kind in ['capture','replay']:
        record=read(E/(label+'-no-movement-native-'+kind+'.json'))
        assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'-no-movement-native-'+kind+'.log'))
    capture=read(E/(name+'-no-movement-settlement.json'))
    proposal=capture['result']
    assert capture['financial_replay_matches'] and not capture['production_ready']
    assert proposal['composition']['calculation_input']['public_offer_debt_return']=='0.00'
    assert proposal['composition']['d9121_reconciliation']['title_count']==0
    net=(Decimal('700.00')/Decimal('1.01')).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN)
    assert proposal['composition']['calculation']['general_net']==str(net)
    assert proposal['assessed_pools']['1010I']['positive_base']=='693.07'
    assert proposal['assessed_pools']['1010I']['assessed_base']=='643.07'
    assert proposal['assessed_pools']['1010C']['assessed_base']=='683.07'
    assert proposal['ibs_nominal_proposal']=='0.64' and proposal['cbs_nominal_proposal']=='6.15'
    assert proposal['original_gross_debits']==capture['input']['gross']
    assert not proposal['received_return_verified'] and not proposal['eligible_for_posting']
    cases.append({'name':name,'capture':ref(E/(name+'-no-movement-settlement.json')),'exact_replay':True})
files=[reader,ROOT/'tests/Fiscal/Unit/DerePublicOfferNoMovementReturn57Test.php',
    E/'planned-no-movement-composition.php',E/'planned-no-movement-settlement.php',Path(__file__)]
with (E/'no-movement-development-validation.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'NATIVE_NO_MOVEMENT_READER_FIX_AND_COMPOSITION_PROTOTYPE_VALIDATED_ACTIVATION_PENDING',
        'reader_tests_with_legacy':28,'reader_assertions_with_legacy':145,'cases':cases,'quality':quality,
        'accepted55_reader_archive':ref(archive),'implementation':[ref(p) for p in files],
        'new_no_movement_policy_selected':False,'no_movement_monthly_command_integrated':False,
        'full_suite_after_reader_fix_passed':False,'scope57_complete':False,'scope54_complete':False,
        'phase4b_complete':False,'production_ready':False},stream,indent=2)
print('PASS:reader57 fix,28/145 tests,three no-movement native prototypes,exact replays and independent bases; activation pending')
