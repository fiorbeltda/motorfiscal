"""Audit actual active-version integration and immutable migrations; full acceptance is a separate artifact."""
from pathlib import Path
import hashlib,json,re,sys
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'
P58=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'
P60=ROOT/'storage/app/phase4b-dere9121-60-version-binding-20261013'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def ref(p):
    return {'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)}

def log(label):
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log')),label
    return re.sub(r'\x1b\[[0-9;]*m','',(E/(label+'.log')).read_text(encoding='utf-8'))

labels=['version61-initial-tests','monthly61-initial-tests','version61-phpstan-all','version61-pint-all',
    'selection61-native-regressions','candidate25-regression','candidate26-regression','candidate27-regression',
    'financial61-replay','production61-check','bundle61-reproduction','native-version-post-format-replay']
for label in labels:
    output=log(label)
    if label in ['version61-initial-tests','monthly61-initial-tests']:
        tests,assertions=(27,73) if label=='version61-initial-tests' else (7,54)
        assert str(tests)+' passed' in output and str(assertions)+' assertions' in output
    if label.startswith('candidate') or label in ['selection61-native-regressions','bundle61-reproduction','native-version-post-format-replay']:
        assert 'PASS' in output,label
assert read(E/'financial61-replay.log')['replay_matches'] is True
assessment=read(E/'production61-check.log')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
archives={i['source_path']:ROOT/i['archive_path'] for i in read(E/'source-selection-activation.json')['sources']}
for item in read(P58/'no-movement-development-validation.json')['implementation']:
    assert sha(archives.get(item['path'],ROOT/item['path']))==item['sha256'],item['path']
for item in read(P60/'business-binding-development-validation.json')['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
accepted55=read(ROOT/'storage/app/phase4b-dere9121-55-command-integration-20261013/monthly-development-validation.json')
command='app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php'
assert sha(archives[command])==next(i['sha256'] for i in accepted55['implementation'] if i['path']==command)
old=read(ROOT/'resources/fiscal/catalogs/rtc-codes-2026.10.13-28-draft58.json')
new=read(ROOT/'resources/fiscal/catalogs/rtc-codes-2026.10.13-28-draft61.json')
assert old['dere_negative_base_local_settlement']==new['dere_negative_base_local_settlement']
assert old['dere_public_offer_negative_base_local_settlement'].get('allow_public_event_versions') is None
policy=new['dere_public_offer_negative_base_local_settlement']
assert policy['allow_public_event_versions'] is True and policy['allow_native_no_movement'] is True
selection=read(E/'selection-regression-validation.json')
assert selection['active01_exact_replay'] and selection['active03_post_format_exact_replay']
assert selection['five_auxiliary_and_closing_xmls_preserved'] and len(selection['no_movement_cases'])==3
for case in selection['no_movement_cases']:
    assert case['exact_replay']
    assert sha(P58/(case['name']+'-monthly-report.json'))==case['report_sha256']
    report=read(P58/(case['name']+'-monthly-report.json'))
    assert len(case['xml_sha256'])==6
    for event,path in report['event_outputs'].items():
        assert sha(ROOT/path)==case['xml_sha256'][event]
native=read(E/'native-version-settlement.json')
for name,sequence in [('monthly-report.json','03'),('active01-monthly-report.json','01')]:
    report=read(E/name)
    result=report['negative_base_settlement']
    active=result['composition']['active_version_binding']['active_event']
    assert active['sequence']==sequence
    assert (ROOT/report['event_outputs']['D2101']).read_bytes()==active['origin_xml'].encode('utf-8')
    assert result['ibs_nominal_proposal']=='0.74' and result['cbs_nominal_proposal']=='7.02'
    assert result['composition']['active_version_binding']['active_event_business_locally_reconciled'] is True
    assert report['production_ready'] is False
    for event,path in report['event_outputs'].items():
        assert sha(ROOT/path)==report['event_validations'][event]['xml_sha256']
        assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
assert read(E/'monthly-report.json')['negative_base_settlement']==native['result']
assert native['financial_replay_matches'] is True
assert sha(E/'active01-monthly-report.json')==selection['active01_report_sha256']
corpus=read(E/'public-corpus56-regression.json')
assert len(corpus['cases'])==20 and corpus['xml_hashes']==120
assert all(c['financial_replay_matches'] and c['monthly_report_matches'] for c in corpus['cases'])
files=[ROOT/p for p in [
    'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferEventVersionReconciler.php',
    'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferActiveEventBusinessBinder.php',
    'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferCurrentBaseCompositionQualifier.php',
    'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferNegativeBaseSettlementPlanner.php',command,
    'tests/Fiscal/Unit/DerePublicOfferVersionSettlement61Test.php',
    'tests/Fiscal/Integration/DerePublicOfferVersionMonthly61Test.php',
    'scripts/verify_phase4b_dere9121_61_selection_regressions.py',
    'docs/fiscal/phase4b-dere9121-61-active-version-integration.md']]+[Path(__file__)]
full='--full' in sys.argv
counts=None
if full:
    output=log('version61-full-suite')
    counts=re.search(r'(\d+) passed \((\d+) assertions\)',output)
    assert counts and (int(counts[1]),int(counts[2]))==(2121,45190)
    labels.append('version61-full-suite')
result={'status':'POLICY_GATED_ACTIVE_PUBLIC_VERSION_MONTHLY_INTEGRATION_'+('FULL_QUALITY_VALIDATED' if full else 'VALIDATED_FULL_SUITE_PENDING'),
    'component_tests':27,'component_assertions':73,'command_tests':7,'command_assertions':54,
    'active_sequences':['01','03'],'native_xmls':12,'no_movement_regression_cases':3,'public56_regression_cases':20,
    'quality':[ref(E/(label+'.json')) for label in labels],
    'native_evidence':[ref(E/name) for name in ['native-version-settlement.json','monthly-report.json','active01-monthly-report.json','selection-regression-validation.json','public-corpus56-regression.json']],
    'implementation':[ref(p) for p in files],'accepted_source_archives':[ref(p) for p in archives.values()],
    'full_suite_after61_complete':full,'full_tests':int(counts[1]) if counts else None,'full_assertions':int(counts[2]) if counts else None,
    'period_state_externally_verified':False,'d1021_proceeding_qualified':False,'scope59_complete':False,
    'scope54_complete':False,'scope28_complete':False,'phase4b_complete':False,'production_ready':False}
target=E/('version-development-validation.json' if full else 'integration-progress-validation.json')
with target.open('x',encoding='utf-8') as f:
    json.dump(result,f,ensure_ascii=False,indent=2)
print('PASS:active01/03 native monthly integration, immutable archives and regressions; '+('full suite verified' if full else 'full suite pending')+'; broad59/54/4B pending')
