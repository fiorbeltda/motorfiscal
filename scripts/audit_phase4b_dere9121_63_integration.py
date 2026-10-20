"""Audit contextual period integration, accepted61 source archives and exact predecessors; accept full separately."""
from pathlib import Path
import hashlib,json,re,sys
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013'
P61=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'
P62=ROOT/'storage/app/phase4b-dere9121-62-period-preparation-20261013'

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

labels=['period63-all-directed-tests','period63-phpstan-all','period63-pint-all','native-period63-post-format-replay',
    'monthly63-native-capture','public63-corpus-regression','candidate25-regression','candidate26-regression',
    'candidate27-regression','financial63-replay','production63-check','bundle63-reproduction']
for label in labels:
    output=log(label)
    if label=='period63-all-directed-tests':
        assert '36 passed' in output and '148 assertions' in output
    if label.startswith('candidate') or label in ['native-period63-post-format-replay','monthly63-native-capture','public63-corpus-regression','bundle63-reproduction']:
        assert 'PASS' in output,label
assert read(E/'financial63-replay.log')['replay_matches'] is True
assessment=read(E/'production63-check.log')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
accepted=read(P61/'version-development-validation.json')
assert accepted['full_suite_after61_complete'] and accepted['full_tests']==2121 and accepted['full_assertions']==45190
archives={i['source_path']:ROOT/i['archive_path'] for i in read(E/'period-history-activation.json')['sources']}
for item in accepted['implementation']:
    assert sha(archives.get(item['path'],ROOT/item['path']))==item['sha256'],item['path']
for item in read(P62/'period-preparation-validation.json')['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
installation=read(E/'period-source-installation.json')
assert installation['prototype_sha256']==sha(P62/'planned-period-qualifier.php')
for item in installation['implementation']:
    assert sha(E/('before-pint-'+Path(item['path']).name))==item['sha256']
old=read(ROOT/'resources/fiscal/catalogs/rtc-codes-2026.10.13-28-draft61.json')
new=read(ROOT/'resources/fiscal/catalogs/rtc-codes-2026.10.13-28-draft63.json')
assert old['dere_negative_base_local_settlement']==new['dere_negative_base_local_settlement']
assert old['dere_public_offer_negative_base_local_settlement'].get('require_public_period_history') is None
policy=new['dere_public_offer_negative_base_local_settlement']
assert policy['require_public_period_history'] and policy['allow_public_event_versions'] and policy['allow_native_no_movement']
native=read(E/'native-period-settlement.json')
report=read(E/'monthly-report.json')
assert report['negative_base_settlement']==native['result']
assert native['financial_replay_matches'] is True
result=native['result']
binding=result['composition']['active_version_binding']
qualified=binding['period_history_qualification']
assert qualified['status']=='LOCAL_PUBLIC_PERIOD_HISTORY_QUALIFIED' and qualified['current_state']=='OPEN'
assert qualified['closing_count']==2 and qualified['reopening_count']==2 and qualified['period_state_locally_reconciled']
assert qualified['period_state_externally_verified'] is False and qualified['closing_origin_economics_reconciled'] is False
assert qualified['government_return_authenticated'] is False
history=native['input']['request']['current_public_offer_return']['period_history']
assert history['monthly_input_hash']==digest(native['input']['month'])==binding['monthly_input_hash']
assert history['witness']['through'].replace('.0000000','')==native['input']['known_at']
assert qualified['history_hash']==history['witness']['history_hash']
assert binding['active_event']['sequence']=='03' and binding['active_event_business_locally_reconciled']
assert result['ibs_nominal_proposal']=='0.74' and result['cbs_nominal_proposal']=='7.02'
assert result['original_gross_debits']==native['input']['gross']
assert result['original_gross_debits']['ibs_nominal']=='0.80' and result['original_gross_debits']['cbs_nominal']=='7.18'
assert (ROOT/report['event_outputs']['D2101']).read_bytes()==binding['active_event']['origin_xml'].encode('utf-8')
monthly=read(E/'monthly-period-validation.json')
assert monthly['exact_replay'] and monthly['six_prior61_xml_bodies_preserved']
assert monthly['report_sha256']==sha(E/'monthly-report.json')
assert len(monthly['xml_sha256'])==6 and len(monthly['predecessor_regressions'])==5
for event,path in report['event_outputs'].items():
    assert sha(ROOT/path)==monthly['xml_sha256'][event]==report['event_validations'][event]['xml_sha256']
    assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
for case in monthly['predecessor_regressions']:
    assert case['exact_replay'] and sha(ROOT/case['report_path'])==case['report_sha256']
    previous=read(ROOT/case['report_path'])
    assert all(sha(ROOT/path)==case['xml_sha256'][event] for event,path in previous['event_outputs'].items())
corpus=read(E/'public-corpus56-regression.json')
assert len(corpus['cases'])==20 and corpus['xml_hashes']==120
assert all(c['financial_replay_matches'] and c['monthly_report_matches'] for c in corpus['cases'])
files=[ROOT/p for p in [
    'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferPeriodStateQualifier.php',
    'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferActiveEventBusinessBinder.php',
    'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferCurrentBaseCompositionQualifier.php',
    'app/Modules/Fiscal/ConsumptionReform/DereFinancialPublicOfferNegativeBaseSettlementPlanner.php',
    'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php',
    'tests/Fiscal/Unit/DerePublicOfferPeriodState63Test.php','tests/Fiscal/Unit/DerePublicOfferPeriodSettlement63Test.php',
    'tests/Fiscal/Integration/DerePublicOfferPeriodMonthly63Test.php','scripts/capture_phase4b_dere9121_63_monthly.py',
    'docs/fiscal/phase4b-dere9121-63-contextual-period-integration.md']]+[Path(__file__)]
full='--full' in sys.argv
counts=None
if full:
    counts=re.search(r'(\d+) passed \((\d+) assertions\)',log('period63-full-suite'))
    assert counts and (int(counts[1]),int(counts[2]))==(2157,45338)
    labels.append('period63-full-suite')
with (E/('period-development-validation.json' if full else 'integration-progress-validation.json')).open('x',encoding='utf-8') as f:
    json.dump({'status':'POLICY_GATED_MONTHLY_BOUND_CONTEXTUAL_PERIOD_INTEGRATION_'+('FULL_QUALITY_VALIDATED' if full else 'VALIDATED_FULL_SUITE_PENDING'),
        'directed_tests':36,'directed_assertions':148,'native_reopening_cycles':2,'native_xmls':6,
        'predecessor_monthly_regression_cases':5,'public56_regression_cases':20,'public56_xml_hashes':120,
        'quality':[ref(E/(label+'.json')) for label in labels],'implementation':[ref(p) for p in files],
        'accepted61_archives':[ref(p) for p in archives.values()],
        'native_evidence':[ref(E/name) for name in ['native-period-settlement.json','monthly-report.json','monthly-period-validation.json','public-corpus56-regression.json']],
        'full_suite_after63_complete':full,'full_tests':int(counts[1]) if counts else None,'full_assertions':int(counts[2]) if counts else None,
        'local_period_integration_complete':full,'period_state_externally_verified':False,'closing_origin_economics_reconciled':False,
        'd1021_proceeding_qualified':False,'scope62_complete':False,'scope59_complete':False,'scope54_complete':False,
        'scope28_complete':False,'phase4b_complete':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:monthly-bound contextual period, two reopening cycles, active03 economics, accepted61 archives and regressions; '+('full suite verified' if full else 'full suite pending')+'; broad scopes pending')
