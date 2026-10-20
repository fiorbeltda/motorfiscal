"""Independently audit forty active versions, title economics, stock, exact evidence and unchanged accepted runtime."""
from pathlib import Path
from decimal import Decimal,localcontext,ROUND_HALF_EVEN
from xml.etree import ElementTree as ET
import hashlib,json
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-65-version-corpus-20261013'
P=ROOT/'storage/app/phase4b-dere9121-56-corpus-preparation-20261013'
P63=ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013'

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

accepted=read(P63/'period-development-validation.json')
assert accepted['full_suite_after63_complete'] and accepted['full_tests']==2157 and accepted['full_assertions']==45338
for item in accepted['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
manifest=read(E/'source-corpus-manifest.json')
assert manifest['predecessor63_acceptance_sha256']==sha(P63/'period-development-validation.json')
assert manifest['source56_manifest_sha256']==sha(P/'source-corpus-manifest.json')
capture=read(E/'corpus-capture-validation.json')
assert capture['source_manifest_sha256']==sha(E/'source-corpus-manifest.json')
assert len(manifest['cases'])==20 and len(capture['cases'])==40 and capture['xml_hashes']==240
results=[]
for case in manifest['cases']:
    name=case['name']
    for key in ['source_month','source_financial_input','source_monthly_report','source_d2101']:
        assert sha(ROOT/case[key]['path'])==case[key]['sha256']
    previous=read(P/(name+'-monthly-report.json'))
    financial=json.loads(checked(name+'-financial-capture'))
    assert financial['status']=='ACTION_REQUIRED' and financial['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
    assert json.loads(checked(name+'-financial-replay'))['replay_matches'] is True
    snapshot=read(E/(name+'-snapshot.json'))
    assert snapshot['input']['rule_bundle_id']=='CONSUMPTION-PHASE4B-2026.10.13-28-draft65'
    for sequence in ['01','03']:
        stem=name+'-active'+sequence
        entry=next(c for c in capture['cases'] if c['name']==name and c['active_sequence']==sequence)
        assert sha(E/(stem+'-settlement.json'))==entry['native_settlement_sha256']
        assert sha(E/(stem+'-monthly-report.json'))==entry['report_sha256']
        for suffix in ['native-capture','native-replay','monthly-capture','monthly-replay']:
            output=checked(stem+'-'+suffix)
            if suffix.startswith('native'):
                assert 'PASS:' in output and 'own65 active'+sequence in output
        native=read(E/(stem+'-settlement.json'))
        report=read(E/(stem+'-monthly-report.json'))
        proposal=native['result']
        assert report['negative_base_settlement']==proposal
        assert proposal['original_gross_debits']==native['input']['gross']==previous['negative_base_settlement']['original_gross_debits']
        assert proposal['original_gross_debits_hash']==digest(native['input']['gross'])
        values=proposal['composition']['calculation_input']
        assert values==previous['negative_base_settlement']['composition']['calculation_input']
        assert values['public_offer_debt_return']==case['original_public_total']
        with localcontext() as context:
            context.prec=100
            partial=Decimal(values['general_revenue'])+Decimal(values['public_offer_debt_return'])-Decimal(values['other_deductible_expenses'])-Decimal(values['allocated_funding_expenses'])
            divisor=Decimal(1)+sum((Decimal(r) for r in values['rates'].values()),Decimal(0))/100
            net=(partial/divisor).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN)
            positive=max(net,Decimal(0)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
            assert Decimal(native['input']['operation']['base']['net_base'])==partial
            assert Decimal(proposal['composition']['calculation']['total_partial'])==partial
            for root,deduction in [('1010I','50.00'),('1010C','10.00')]:
                pool=proposal['assessed_pools'][root]
                assert Decimal(pool['positive_base'])==positive and pool['deduction']==deduction
                assert Decimal(pool['assessed_base'])==positive-Decimal(deduction)
            taxes={}
            for tax,rate in values['rates'].items():
                root='1010C' if tax=='CBS' else '1010I'
                taxes[tax]=(Decimal(proposal['assessed_pools'][root]['assessed_base'])*Decimal(rate)/100).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
                assert Decimal(proposal['proposed_tax_components'][tax]['nominal_amount'])==taxes[tax]
            assert Decimal(proposal['ibs_nominal_proposal'])==taxes['IBS_STATE']+taxes['IBS_MUNICIPAL']
            assert Decimal(proposal['cbs_nominal_proposal'])==taxes['CBS']
        stock=proposal['stock_after_proposed_consumption']
        assert {r['codBCN']:r['available_after_proposed_consumption'] for r in stock}=={
            '1010I20260600':'0.00','1010C20260600':'80.00','1010C20260801':'0.00'}
        for row in stock:
            assert Decimal(row['available_after_return'])-Decimal(row['proposed_current_use'])==Decimal(row['available_after_proposed_consumption'])
        binding=proposal['composition']['active_version_binding']
        active=binding['active_event']
        assert active['sequence']==sequence and binding['active_event_business_locally_reconciled']
        assert binding['period_history_qualification']['reopening_count']==2
        assert binding['period_history_qualification']['period_state_externally_verified'] is False
        assert binding['period_history_qualification']['closing_origin_economics_reconciled'] is False
        assert binding['monthly_input_hash']==digest(native['input']['month'])
        origin=ET.fromstring(active['origin_xml'])
        title_values=[Decimal(n.text) for n in origin.iter() if n.tag.split('}')[-1]=='vApur']
        assert len(title_values)==case['title_count'] and sum(title_values,Decimal(0))==Decimal(case['original_public_total'])
        assert (ROOT/report['event_outputs']['D2101']).read_bytes()==active['origin_xml'].encode('utf-8')
        for event,path in report['event_outputs'].items():
            assert sha(ROOT/path)==entry['xml_sha256'][event]==report['event_validations'][event]['xml_sha256']
            assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
            if event!='D2101':
                assert sha(ROOT/path)==sha(ROOT/previous['event_outputs'][event])
        assert entry['exact_native_replay'] and entry['exact_monthly_replay'] and entry['financial_replay_matches']
        assert report['production_ready'] is False and proposal['received_return_verified'] is False and proposal['eligible_for_posting'] is False
        results.append({'name':name,'active_sequence':sequence,'title_count':len(title_values),'public_total':case['original_public_total'],
            'base_after_gross_down':str(positive),'ibs':proposal['ibs_nominal_proposal'],'cbs':proposal['cbs_nominal_proposal']})
guards=read(E/'corpus-guard-validation.json')
assert len(guards['cases'])==guards['guard_cases']==44
for case in guards['cases']:
    label=case['name']+'-guard-'+case['guard']
    output=json.loads(checked(label,1))
    assert output['reason']==case['expected_reason'] and case['no_xml_or_report_written']
    candidate=E/(label+'-candidate.xml')
    assert not candidate.exists() and not (E/(label+'-report.json')).exists()
    assert not any(Path(str(candidate)+'.'+event+'.xml').exists() for event in ['D1001','D1011','D1101','D1121','D2101'])
quality=['production65-check','bundle65-reproduction']
for label in quality:
    output=checked(label)
    if label!='production65-check':
        assert 'PASS' in output
assert read(E/'production65-check.log')['ready'] is False
assert read(E/'production65-check.log')['eligible_for_local_validation'] is True
regressions=[]
for label in ['candidate25-regression','candidate26-regression','candidate27-regression']:
    evidence=next(item for item in accepted['quality'] if item['path'].endswith('/'+label+'.json'))
    path=ROOT/evidence['path']
    assert sha(path)==evidence['sha256']
    record=read(path)
    log=path.with_suffix('.log')
    assert record['exit_code']==0 and record['log_sha256']==sha(log)
    assert 'PASS' in log.read_text(encoding='utf-8')
    regressions.append(ref(path))
for record_path in E.glob('*.compression.json'):
    record=read(record_path)
    assert record['content_preserved'] and sha(ROOT/record['path'])==record['sha256']
files=[ROOT/p for p in ['scripts/build_phase4b_dere9121_65_corpus_bundle.py','scripts/prepare_phase4b_dere9121_65_corpus_drivers.py',
    'scripts/capture_phase4b_dere9121_65_version_corpus.py','scripts/verify_phase4b_dere9121_65_corpus_guards.py',
    'docs/fiscal/phase4b-next-scope-dere9121-version-corpus65.md']]+[Path(__file__)]
with (E/'version-corpus-development-validation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'TWENTY_NATIVE_PUBLIC_CASES_FORTY_ACTIVE_VERSIONS_LOCALLY_VALIDATED',
        'cases':results,'financial_cases':20,'active_monthly_versions':40,'xml_hashes':240,
        'financial_replays':20,'native_replays':40,'monthly_replays':40,'command_guard_cases':44,
        'source_manifest':ref(E/'source-corpus-manifest.json'),'native_capture':ref(E/'corpus-capture-validation.json'),
        'negative_evidence':ref(E/'corpus-guard-validation.json'),'quality':[ref(E/(label+'.json')) for label in quality]+regressions,
        'implementation':[ref(p) for p in files],'accepted_runtime':ref(P63/'period-development-validation.json'),
        'runtime_unchanged_from_accepted63':True,'full_suite_reused_from63':True,'historical_regressions_reused_from63':True,
        'full_tests':2157,'full_assertions':45338,
        'local_corpus65_complete':True,'period_state_externally_verified':False,'d1021_proceeding_qualified':False,
        'scope28_complete':False,'scope54_complete':False,'scope59_complete':False,'phase4b_complete':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:40 active versions,240 XMLs,100 exact replays,44 pre-write refusals, independent title/tax/stock arithmetic and unchanged accepted63 runtime')
