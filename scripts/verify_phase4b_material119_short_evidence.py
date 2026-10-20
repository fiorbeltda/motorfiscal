"""Read-only independent119 audit of geographic and contract native captures."""
from pathlib import Path
from datetime import datetime, timezone
from decimal import Decimal, ROUND_HALF_UP
import hashlib
import json

R=Path(__file__).resolve().parents[1]
BASE=Path(r'D:\ProjetoSaasExports')
E=BASE/'phase4b-material-input119-integration-20261020'
checks=0
def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
def check(ok,label):
    global checks
    assert ok,label
    checks+=1
def body(result,kind):
    return result if kind=='rtc' else next((r['payload']for r in result['trace']if r['type']=='CONSUMPTION_REFORM'),None)
def execution(directory,label,expected):
    receipt=read(directory/(label+'-execution.json'))
    check(receipt['exit_code']==receipt['expected_exit_code']==expected,'terminal:'+label)
    for channel in ['stdout','stderr']:
        check(sha(directory/(label+'.'+channel))==receipt[channel+'_sha256'],'bytes:'+label+channel)
    return receipt
def frozen(directory,collector):
    paths=[p for folder in ['app','tests','resources/fiscal']for p in(R/folder).rglob('*')if p.is_file()]
    paths += [R/'scripts'/collector,R/'composer.lock',R/'phpunit.xml']
    actual={str(p):sha(p)for p in sorted(set(paths))}
    check(actual==read(directory/'preflight.json')['input_hashes'],'frozen input membership:'+directory.name)
def verify():
    global checks
    checks=0
    geography_dir=E/'cli-v1'; contract_dir=E/'contract-cli-v1'
    geography=read(geography_dir/'material119-cli-validation.json')
    contracts=read(contract_dir/'material119-contract-cli-validation.json')
    baseline=read(E/'material119-targeted-validation.json')
    bundle='bundles/consumption-phase4b-2026.10.20-119-draft1.json'
    for report in [geography,contracts]:
        check(report['bundle']==bundle,'same119 bundle')
        check(report['bundle_sha256']==sha(R/'resources/fiscal'/bundle),'manifest bytes')
        check(report['input_inventory_unchanged']is True,'capture frozen')
        check(report['production_ready']is False,'no production claim')
    for path,digest in baseline['runtime_hashes'].items():check(sha(R/path)==digest,'current119 runtime:'+path)
    check(len(geography['cases'])==16 and geography['native_decisions']==geography['exact_replays']==32,'geographic scope')
    check(len(contracts['cases'])==45 and contracts['native_decisions']==contracts['exact_replays']==90,'contract scope')
    labels=set()
    for case in geography['cases']:
        for kind in ['rtc','integrated']:
            record=case[kind]; tag=case['branch']+'-'+case['destination_case']+'-'+kind
            labels.update([tag,tag+'-replay'])
            expected_exit=1 if kind=='rtc' and record['status']=='INVALID'else 0
            execution(geography_dir,tag,expected_exit);execution(geography_dir,tag+'-replay',0)
            result=read(geography_dir/(tag+'.stdout'));snapshot=geography_dir/record['snapshot']
            check(sha(snapshot)==record['snapshot_sha256'],'geo snapshot bytes:'+tag)
            check(read(snapshot)['result']==result,'geo snapshot result:'+tag)
            check(result['status']==record['status']and result['reason_codes']==record['reasons'],'geo result:'+tag)
            rtc=body(result,kind); stages={r['stage']:r for r in rtc['trace']}
            check(stages['MATERIAL_INPUT_POLICY119']['status']=='RESOLVED'and stages['ADJUSTMENT_INPUT119']['status']=='RESOLVED','mandatory119 before destination:'+tag)
            reference=stages['OBSERVED_GEOGRAPHIC_REFERENCE']
            for flag in ['historical_validity_proven','fiscal_destination_determined','juridical_municipality_status_proven',
                         'supplier_establishment_proven','acquirer_or_recipient_domicile_proven','tax_rate_selected',
                         'legal_applicability_approved','posting_authorized','production_ready']:
                check(reference[flag]is False,'geographic scope:'+tag+flag)
            replay=read(geography_dir/(tag+'-replay.stdout'));check(replay['replay_matches']is True,'geo exact replay:'+tag)
            if kind=='integrated':check(replay['result']==result,'geo whole integrated replay:'+tag)
    labels.add('production-assess119')
    check(labels=={p.name.removesuffix('-execution.json')for p in geography_dir.glob('*-execution.json')},'exact65 geographic execution receipts')
    execution(geography_dir,'production-assess119',0)
    assessment=read(geography_dir/'production-assess119.stdout')
    check(assessment['ready']is False and assessment['eligible_for_local_validation']is True,'local validation only')
    check(assessment['official_sources_checked']==assessment['official_source_hashes_valid']==98,'98 source hashes')
    check(len(assessment['blockers'])==127,'127 production blockers')
    frozen(geography_dir,'capture_phase4b_material119_geography_cli.py')
    source118=BASE/'phase4b-requirements118-base-audit-20261020-v1/native-probe-v2/observations.json'
    source117=BASE/'phase4b-requirements117-subjects-audit-20261020-v1/native-probe-v1/observations.json'
    old={origin:{r['name']:r for r in read(path)['cases']}for origin,path in [('118',source118),('117',source117)]}
    for path,digest in read(contract_dir/'preflight.json')['source_observations'].items():check(sha(Path(path))==digest,'original native witness:'+path)
    check(sum(r['origin']=='118'for r in contracts['cases'])==34,'all34 base118 witnesses')
    check(sum(r['origin']=='117'for r in contracts['cases'])==5,'five declared collection117 witnesses')
    labels=set();money_checks=0; former_exceptions=0
    for i,case in enumerate(contracts['cases']):
        scenario=read(contract_dir/case['scenario'])
        check(sha(contract_dir/case['scenario'])==case['scenario_sha256'],'native scenario:'+str(i))
        check(scenario['knowledge_at']=='2026-10-20T14:22:21.000000+00:00','synthetic known119:'+str(i))
        if case['origin']in old:
            original=old[case['origin']][case['name']]
            check(original['input']['facts']==scenario['facts'],'original witness facts unchanged:'+case['name'])
        if case['formerly_threw']:
            former_exceptions+=1;check(case['name']=='charge-boolean-false','only captured118 exception case')
        for kind in ['rtc','integrated']:
            tag=f'{i:03d}-{kind}';labels.update([tag,tag+'-replay']);record=case[kind]
            result=read(contract_dir/(tag+'.stdout'));snapshot=contract_dir/record['snapshot']
            execution(contract_dir,tag,1 if kind=='rtc'and record['status']=='INVALID'else 0);execution(contract_dir,tag+'-replay',0)
            check(sha(snapshot)==record['snapshot_sha256'],'contract snapshot bytes:'+tag)
            check(read(snapshot)['result']==result and read(snapshot)['input']==scenario,'whole native snapshot:'+tag)
            check(result['status']==record['status']and record['reason']in result['reason_codes'],'contract result:'+tag)
            replay=read(contract_dir/(tag+'-replay.stdout'));check(replay['replay_matches']is True,'contract exact replay:'+tag)
            if kind=='integrated':check(replay['result']==result,'whole contract integrated replay:'+tag)
            rtc=body(result,kind)
            if rtc is not None and rtc['decision']:
                d=rtc['decision'];base=Decimal(scenario['facts']['rtc.gross_amount'])
                for row in scenario['facts']['rtc.base_adjustments']:
                    value=Decimal(str(row['amount']));base=base+value if row['kind']=='INCLUDED_CHARGE'else base-value
                check(Decimal(d['base']['taxable'])==base,'Decimal base:'+tag);money_checks+=1
                for key,rate,amount in [('ibs','state_rate_percent','state_amount'),('ibs','municipal_rate_percent','municipal_amount'),('cbs','federal_rate_percent','nominal_amount')]:
                    expected=(base*Decimal(d[key][rate])/100).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
                    check(Decimal(d[key][amount])==expected,'Decimal nominal:'+tag+key+rate);money_checks+=1
            if case['formerly_threw']:check(result['status']=='INVALID'and'RTC_ADJUSTMENT119_AMOUNT_NOT_EXACT'in result['reason_codes'],'former exception is a controlled fiscal refusal:'+tag)
    check(former_exceptions==contracts['former_exception_cases_controlled']==1,'one old exception scenario,two native controlled refusals')
    check(money_checks==contracts['independent_decimal_checks'],'independent Decimal count')
    check(labels=={p.name.removesuffix('-execution.json')for p in contract_dir.glob('*-execution.json')},'exact180 contract execution receipts')
    frozen(contract_dir,'capture_phase4b_material119_contract_cli.py')
    return {'created_at':datetime.now(timezone.utc).isoformat(),'audit_checks':checks,'bundle':bundle,
        'bundle_sha256':geography['bundle_sha256'],'runtime_hashes':baseline['runtime_hashes'],
        'native_decisions':122,'exact_replays':122,'geographic_native_decisions':32,'contract_native_decisions':90,
        'independent_decimal_checks':money_checks,'official_sources_valid':98,'production_blockers':127,
        'full_suite_complete':False,'phase4b_complete':False,'production_ready':False}
if __name__=='__main__':
    report=verify();target=E/'material119-short-validation.json'
    with target.open('x',encoding='utf-8')as f:json.dump(report,f,ensure_ascii=False,indent=2)
    print(json.dumps({k:v for k,v in report.items()if k!='runtime_hashes'}),flush=True)
