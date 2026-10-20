"""Audit zero-title native versions, original credit/gross economics, eighteen guards and accepted runtime."""
from pathlib import Path
from decimal import Decimal,localcontext,ROUND_HALF_EVEN
from xml.etree import ElementTree as ET
import hashlib,json
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-66-no-movement-versions-20261013'
P=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'
P65=ROOT/'storage/app/phase4b-dere9121-65-version-corpus-20261013'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def ref(p):
    return {'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)}

def checked(label,code=0):
    record=read(E/(label+'.json'))
    assert record['exit_code']==code and record['log_sha256']==sha(E/(label+'.log')),label
    return (E/(label+'.log')).read_text(encoding='utf-8')

accepted65=read(P65/'version-corpus-development-validation.json')
assert accepted65['local_corpus65_complete']
runtime_path=ROOT/accepted65['accepted_runtime']['path']
assert sha(runtime_path)==accepted65['accepted_runtime']['sha256']
runtime=read(runtime_path)
assert runtime['full_suite_after63_complete'] and runtime['full_tests']==2157 and runtime['full_assertions']==45338
for item in runtime['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
assert json.loads(checked('financial66-replay'))['replay_matches']
financial=json.loads(checked('financial66-capture'))
assert financial['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
snapshot=read(E/'financial-snapshot.json')
previous_snapshot=read(P/'financial-snapshot.json')
old_credit=[s for s in previous_snapshot['result']['trace'] if 'BORROWER_CREDIT' in s['stage']]
new_credit=[s for s in snapshot['result']['trace'] if 'BORROWER_CREDIT' in s['stage']]
assert old_credit and new_credit==old_credit
capture=read(E/'no-movement-version-capture-validation.json')
assert len(capture['cases'])==6 and capture['xml_hashes']==36
for case in capture['cases']:
    name=case['name']
    sequence=case['active_sequence']
    stem=name+'-active'+sequence
    native=read(E/(stem+'-settlement.json'))
    report=read(E/(stem+'-monthly-report.json'))
    previous=read(P/(name+'-monthly-report.json'))
    proposal=native['result']
    assert sha(E/(stem+'-settlement.json'))==case['native_settlement_sha256']
    assert sha(E/(stem+'-monthly-report.json'))==case['report_sha256']
    assert report['negative_base_settlement']==proposal
    assert proposal['original_gross_debits']==native['input']['gross']==previous['negative_base_settlement']['original_gross_debits']
    assert proposal['original_gross_debits_hash']==digest(native['input']['gross'])
    binding=proposal['composition']['active_version_binding']
    assert binding['active_event']['sequence']==sequence and binding['active_event']['reconciliation']['title_count']==0
    assert binding['active_event']['reconciliation']['total_vApur']=='0.00'
    origin=ET.fromstring(binding['active_event']['origin_xml'])
    assert not [n for n in origin.iter() if n.tag.split('}')[-1]=='detTitulo']
    markers=[n for n in origin.iter() if n.tag.split('}')[-1]=='semTitulos']
    assert len(markers)==1 and markers[0].text=='1'
    assert binding['period_history_qualification']['reopening_count']==2
    assert binding['period_history_qualification']['period_state_externally_verified'] is False
    assert binding['monthly_input_hash']==digest(native['input']['month'])
    values=proposal['composition']['calculation_input']
    assert values['public_offer_debt_return']=='0.00'
    with localcontext() as context:
        context.prec=100
        partial=Decimal(values['general_revenue'])-Decimal(values['other_deductible_expenses'])-Decimal(values['allocated_funding_expenses'])
        divisor=Decimal(1)+sum((Decimal(r) for r in values['rates'].values()),Decimal(0))/100
        positive=(partial/divisor).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
        assert positive==Decimal('693.07')
        for root,deduction in [('1010I','50.00'),('1010C','10.00')]:
            assert Decimal(proposal['assessed_pools'][root]['positive_base'])==positive
            assert Decimal(proposal['assessed_pools'][root]['assessed_base'])==positive-Decimal(deduction)
        for tax,rate in values['rates'].items():
            root='1010C' if tax=='CBS' else '1010I'
            nominal=(Decimal(proposal['assessed_pools'][root]['assessed_base'])*Decimal(rate)/100).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
            assert Decimal(proposal['proposed_tax_components'][tax]['nominal_amount'])==nominal
    assert proposal['ibs_nominal_proposal']=='0.64' and proposal['cbs_nominal_proposal']=='6.15'
    assert proposal['stock_after_proposed_consumption']==previous['negative_base_settlement']['stock_after_proposed_consumption']
    for row in proposal['stock_after_proposed_consumption']:
        assert Decimal(row['available_after_return'])-Decimal(row['proposed_current_use'])==Decimal(row['available_after_proposed_consumption'])
    for suffix in ['native-capture-v2','native-replay','monthly-capture','monthly-replay']:
        output=checked(stem+'-'+suffix)
        if suffix.startswith('native'):
            assert 'PASS:' in output and 'own66 active' in output
    for event,path in report['event_outputs'].items():
        assert sha(ROOT/path)==case['xml_sha256'][event]==report['event_validations'][event]['xml_sha256']
        assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
        if event!='D2101':
            assert sha(ROOT/path)==sha(ROOT/previous['event_outputs'][event])
    assert (ROOT/report['event_outputs']['D2101']).read_bytes()==binding['active_event']['origin_xml'].encode('utf-8')
    assert report['production_ready'] is False and proposal['eligible_for_posting'] is False
guards=read(E/'no-movement-guard-validation.json')
assert len(guards['cases'])==18
for case in guards['cases']:
    label=case['label']
    assert json.loads(checked(label,1))['reason']==case['expected_reason']
    candidate=E/(label+'-candidate.xml')
    assert not candidate.exists() and not (E/(label+'-report.json')).exists()
    assert not any(Path(str(candidate)+'.'+event+'.xml').exists() for event in ['D1001','D1011','D1101','D1121','D2101'])
quality=['production66-check','bundle66-reproduction']
for label in quality:
    checked(label)
assert read(E/'production66-check.log')['ready'] is False and read(E/'production66-check.log')['eligible_for_local_validation']
files=[ROOT/p for p in ['scripts/build_phase4b_dere9121_66_no_movement_version_bundle.py',
    'scripts/prepare_phase4b_dere9121_66_no_movement_drivers.py','scripts/capture_phase4b_dere9121_66_no_movement_versions.py',
    'scripts/verify_phase4b_dere9121_66_no_movement_guards.py','docs/fiscal/phase4b-next-scope-dere9121-no-movement-versions66.md']]+[Path(__file__)]
with (E/'no-movement-version-development-validation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'THREE_NATIVE_NO_MOVEMENT_PROFILES_ACTIVE01_AND03_LOCALLY_VALIDATED',
        'monthly_versions':6,'xml_hashes':36,'financial_replays':1,'native_replays':6,'monthly_replays':6,'command_guard_cases':18,
        'native_capture':ref(E/'no-movement-version-capture-validation.json'),'negative_evidence':ref(E/'no-movement-guard-validation.json'),
        'quality':[ref(E/(label+'.json')) for label in quality],'implementation':[ref(p) for p in files],
        'accepted_runtime':ref(runtime_path),'runtime_unchanged_from_accepted63':True,'full_suite_reused_from63':True,
        'full_tests':2157,'full_assertions':45338,'borrower_credit_eligibility_preserved_not_appropriated':True,
        'local_no_movement_version_integration_complete':True,'period_state_externally_verified':False,
        'd1021_proceeding_qualified':False,'scope28_complete':False,'scope54_complete':False,'scope59_complete':False,
        'phase4b_complete':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:six zero-title active versions,36 XMLs,13 replays,18 pre-write refusals, preserved borrower eligibility and unchanged accepted runtime')
