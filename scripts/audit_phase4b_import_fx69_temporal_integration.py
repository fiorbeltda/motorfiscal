"""Audit strict dates, current source generation and fresh exact18 replays; require terminal suite for final artifact."""
from pathlib import Path
import hashlib,json,re,sys
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-import-fx69-temporal-preparation-20261014'
P=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def ref(p):return {'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)}
def checked(label):
    record=read(P/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(P/(label+'.log')),label
    return re.sub(r'\x1b\[[0-9;]*m','',(P/(label+'.log')).read_text(encoding='utf-8'))

labels=['fx69-directed-tests','fx69-phpstan-all','fx69-pint-all','fx69-existing-replays']
for label in labels:
    text=checked(label)
    if label=='fx69-directed-tests':assert '56 passed' in text and '193 assertions' in text
installation=read(E/'temporal-installation.json')
assert sha(E/'before-install-temporal-transaction.php')==installation['before_sha256']
assert sha(E/'before-temporal-transaction.php')==installation['before_sha256']
interrupted=read(P/'fx68-full-suite.json')
assert interrupted['exit_code']==143 and interrupted['log_sha256']==sha(P/'fx68-full-suite.log')
native=read(E/'temporal-native-result.json')
assert len(native['cases'])==7
for name,case in native['cases'].items():
    if name=='valid':assert case['current']==case['planned']
    else:
        assert case['current']['status']=='LOCAL_IMPORT_FX_TRANSACTION_BOUND'
        assert case['planned']['status']=='ACTION_REQUIRED'
    assert case['planned']['production_ready'] is False
replay=read(E/'post-fix-replay-validation.json')
assert len(replay['cases'])==18
for case in replay['cases']:
    path=ROOT/case['path'];log=E/(path.stem+'-replay.log')
    assert sha(path)==case['sha256'] and sha(log)==case['log_sha256'] and case['exact_replay']
    assert read(log)['replay_matches'] is True
prior=read(P/'fx-integration-progress-validation.json')
changed='app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php'
for item in prior['implementation']:
    path=E/'before-install-temporal-transaction.php' if item['path']==changed else ROOT/item['path']
    assert sha(path)==item['sha256'],item['path']
source=(ROOT/changed).read_text(encoding='utf-8')
assert 'TRANSACTION_EXACT_DATE_REQUIRED' in source and 'POLICY_EXACT_DATE_REQUIRED' in source
files=[ROOT/p for p in ['app/Modules/Fiscal/ConsumptionReform/ImportFxQuoteConverter.php',changed,
    'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php','tests/Fiscal/Unit/ImportFxTemporal69Test.php',
    'tests/Fiscal/Unit/ImportFxTransaction68Test.php','tests/Fiscal/Unit/ImportFxQuoteConverter68Test.php',
    'tests/Fiscal/Integration/ImportFxEngine68Test.php','scripts/verify_phase4b_import_fx69_existing_replays.py',
    'docs/fiscal/phase4b-import-fx69-temporal-correction.md']]+[Path(__file__)]
full='--full' in sys.argv;counts=None
if full:
    counts=re.search(r'(\d+) passed \((\d+) assertions\)',checked('fx69-full-suite'))
    assert counts and int(counts[1])==2213
    labels.append('fx69-full-suite')
with (E/('temporal-integration-development-validation.json' if full else 'temporal-integration-progress-validation.json')).open('x',encoding='utf-8') as f:
    json.dump({'status':'STRICT_FX_CONTEXT_DATES_AND_DIRECT_IMPORT_INTEGRATION_'+('FULL_QUALITY_VALIDATED' if full else 'VALIDATED_FULL_SUITE_PENDING'),
        'directed_tests':56,'directed_assertions':193,'invalid_date_counterexamples':6,'fresh_exact_replays':18,
        'quality':[ref(P/(label+'.json')) for label in labels],'implementation':[ref(p) for p in files],
        'native_evidence':[ref(E/name) for name in ['temporal-native-result.json','post-fix-replay-validation.json']],
        'pre_fix_generation_archive':ref(E/'before-install-temporal-transaction.php'),'pre_fix_suite_interrupted':True,
        'full_suite_complete':full,'full_tests':int(counts[1]) if counts else None,'full_assertions':int(counts[2]) if counts else None,
        'local_direct_fx_with_strict_dates_complete':full,'cross_currency_unquoted_path_qualified':False,
        'import_document_obligation_proven':False,'scope68_complete':False,'phase4b_complete':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:six strict temporal refusals,18 exact direct FX/credit replays and generation archive; '+('full suite verified' if full else 'full suite pending')+'; broad68 pending')
