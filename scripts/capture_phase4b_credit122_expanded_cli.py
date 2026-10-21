"""Current122 corpus:124 prices plus16 geography/45 contracts;370 native decisions and own exact replays."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import os
import copy
from decimal import Decimal,ROUND_HALF_UP
from phase4b_credit122_capture_support import canonical,compare_compatibility_result
import subprocess

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021')
OLD = E.parent/'phase4b-dated108-integration-20261019/cli-v1'
C = E/'expanded-cli-v1'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/phase4b-credit122-expanded-cli-v1'

def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f, 'sha256').hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as f: json.dump(v, f, indent=2, ensure_ascii=False)
def inventory():
    paths = [p for root in ['app','tests','resources/fiscal'] for p in (R/root).rglob('*') if p.is_file()]
    paths += [Path(__file__),R/'scripts/phase4b_credit122_capture_support.py', R/'composer.lock', R/'phpunit.xml',compat_index_path,E/'credit122-expanded-preparation-validation.json',history_receipt_path]
    paths += list(source_snapshot_paths)
    return {str(p):sha(p) for p in sorted(set(paths))}
def rtc_body(result, kind):
    return result if kind == 'rtc' else {r['type']:r['payload'] for r in result['trace']}['CONSUMPTION_REFORM']

assert not C.exists(),'Exclusive current122 expanded capture'
directed = read(E/'credit122-expanded-preparation-validation.json')
assert directed['directed_tests'] == 447 and directed['directed_assertions']==9942
for p,h in directed['runtime_hashes'].items(): assert sha(R/p) == h, p
manifests = [read(R/'tests/Fixtures'/name) for name in ['credit122-price-expectations.json','credit122-price-upstream-expectations.json']]
assert manifests[0]['bundle'] == manifests[1]['bundle']
cases = manifests[0]['cases']+manifests[1]['cases']; assert len(cases) == 124
bundle_path = R/'resources/fiscal'/directed['bundle']; bundle = read(bundle_path)
assert sha(bundle_path) == directed['bundle_sha256']
prior = read(OLD/'dated108-cli-validation.json')
old_cases = {r['path']:r for r in prior['cases']}
compat_index_path=E/'credit122-compatibility119-source-index-v1.json'
assert sha(compat_index_path)=='d6704cef42dee0ae4849f1434844065c98a7d27256d3d8ee764f662615b0473d'
compat_cases=read(compat_index_path)['cases'];assert len(compat_cases)==61
assert not any(row['source_document_price_optin']for row in compat_cases)
history_receipt_path=E/'historical-replay-v1/credit122-historical-validation.json'
assert sha(history_receipt_path)=='ddffca53a90188148c06c90d8b3cedb12ba24a2201e5198f823fb6505b349520'
history_hashes={Path(row['path']):row['sha256']for row in read(history_receipt_path)['records']}
source_snapshot_paths={Path(value['snapshot'])for row in compat_cases for value in row['runners'].values()}
for source in prior['cases']:
    for runner in ['rtc','integrated']:source_snapshot_paths.add(OLD/source[runner+'_snapshot'])
for path in source_snapshot_paths:assert sha(path)==history_hashes[path],path
baseline_assessment_path=E/'production-assess122-directed-v1/stdout.log'
baseline_assessment=read(baseline_assessment_path)
assert len(baseline_assessment['blockers'])==128
before = inventory()
C.mkdir(exist_ok=False)
save(C/'preflight.json', {'created_at':datetime.now(timezone.utc).isoformat(),'input_hashes':before,
    'directed_audit_sha256':sha(E/'credit122-expanded-preparation-validation.json'),
    'planned_decisions':370,'planned_own_replays':370,'planned_price_cases':124,'planned_compatibility_cases':61,'historical1441_collected_separately':True})
subprocess.run([D,'compose','exec','-T','app','mkdir','-p',TEMP+'/history'], cwd=R, check=True)
cid = subprocess.run([D,'compose','ps','-q','app'], cwd=R, capture_output=True, text=True, check=True).stdout.strip()
assert cid
save(C/'process-start.json', {'created_at':datetime.now(timezone.utc).isoformat(),'container':cid,'host_python_pid':os.getpid(),'collector_sha256':sha(Path(__file__))})

def run(label, args, expected_exit=0):
    cmd = [D,'compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args]
    record = {'command':cmd,'expected_exit_code':expected_exit,'started_at':datetime.now(timezone.utc).isoformat()}
    proc = subprocess.run(cmd, cwd=R, capture_output=True)
    record.update(exit_code=proc.returncode,finished_at=datetime.now(timezone.utc).isoformat())
    for ch in ['stdout','stderr']:
        with (C/(label+'.'+ch)).open('xb') as f: f.write(getattr(proc,ch))
        record[ch+'_sha256'] = sha(C/(label+'.'+ch))
    save(C/(label+'-execution.json'),record)
    assert proc.returncode == expected_exit, (label,proc.stdout.decode(errors='replace')[-1800:],proc.stderr.decode(errors='replace'))
    return json.loads(proc.stdout)


compat_records=[];compat_decimal_checks=0
for index,case in enumerate(compat_cases):
    old_rtc=read(Path(case['runners']['rtc']['snapshot']))
    scenario=copy.deepcopy(old_rtc['input'])
    scenario.update(scenario_id='CREDIT122-COMPAT-'+case['family']+'-'+case['name'],
                    knowledge_at='2026-10-21T11:10:00.000000+00:00',
                    rule_bundle_id=bundle['bundle_id'],catalog_versions=bundle['catalog_versions'])
    local_input=C/(f'compat-{index:03d}-scenario.json');save(local_input,scenario)
    remote_input=TEMP+'/'+local_input.name
    subprocess.run([D,'cp',str(local_input),cid+':'+remote_input],cwd=R,capture_output=True,check=True)
    record={'family':case['family'],'name':case['name'],'scenario':local_input.name,'scenario_sha256':sha(local_input)}
    for runner,command,replay_command in [('rtc','fiscal:rtc:run','fiscal:rtc:replay'),('integrated','fiscal:scenario:run','fiscal:decision:replay')]:
        source=case['runners'][runner];source_path=Path(source['snapshot'])
        assert sha(source_path)==source['snapshot_sha256']==history_hashes[source_path]
        original=read(source_path);label=f'compat-{index:03d}-{runner}';remote=TEMP+'/'+label+'-snapshot.json'
        result=run(label,[command,remote_input,'--bundle-manifest='+directed['bundle'],'--snapshot='+remote],
                   1 if runner=='rtc'and source['status']=='INVALID'else 0)
        changes=compare_compatibility_result(original['result'],result,runner,old_input=original['input'],new_input=scenario)
        rtc=rtc_body(result,runner)if runner=='rtc'or any(x['type']=='CONSUMPTION_REFORM'for x in result['trace'])else None
        if case['family']=='contracts'and rtc and rtc['decision']:
            decision=rtc['decision'];base_value=Decimal(scenario['facts']['rtc.gross_amount'])
            for adjustment in scenario['facts']['rtc.base_adjustments']:
                amount=Decimal(str(adjustment['amount']))
                base_value=base_value+amount if adjustment['kind']=='INCLUDED_CHARGE'else base_value-amount
            assert Decimal(decision['base']['taxable'])==base_value
            compat_decimal_checks+=1
            for tax,rate,amount in [('IBS','state_rate_percent','state_amount'),('IBS','municipal_rate_percent','municipal_amount'),('CBS','federal_rate_percent','nominal_amount')]:
                component=decision[tax.lower()]
                assert Decimal(component[amount])==(base_value*Decimal(component[rate])/100).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
                compat_decimal_checks+=1
        if case['name']=='acquirer-basis-correct':assert rtc['decision']['collection']['extinction_status']=='UNCONFIRMED'
        replay=run(label+'-replay',[replay_command,remote]);assert replay['replay_matches']is True
        if runner=='integrated':assert canonical(replay['result'])==canonical(result)
        snapshot=C/(label+'-snapshot.json');subprocess.run([D,'cp',cid+':'+remote,str(snapshot)],cwd=R,capture_output=True,check=True)
        captured=read(snapshot)
        assert canonical(captured['input'])==canonical(scenario)and canonical(captured['result'])==canonical(result)
        record[runner]={'snapshot':snapshot.name,'snapshot_sha256':sha(snapshot),'exact_replay':True,
                        'source_snapshot':str(source_path),'source_snapshot_sha256':sha(source_path),
                        'business_values_and_fiscal_assessment_preserved':True,'declared_transport_metadata_changes':changes,
                        'outer_cross_version_execution_plan_trace_compared':False}
    compat_records.append(record)
    print(f'PASS:compatibility122 {index+1}/61 '+case['family']+'/'+case['name'],flush=True)
print('PASS:all61 compatibility cases;price124 capture follows',flush=True)

records = []; nominal_fx_controls = 0; usd_compositions = 0; composed_decisions = 0; lifecycle_refusals = 0
for index,case in enumerate(cases):
    assert sha(R/case['path']) == case['scenario_sha256']
    record = dict(case)
    for kind,cmd,replaycmd in [('rtc','fiscal:rtc:run','fiscal:rtc:replay'),('integrated','fiscal:scenario:run','fiscal:decision:replay')]:
        label = f'{index:03d}-{kind}'; remote = TEMP+'/'+label+'-snapshot.json'
        actual = run(label,[cmd,case['path'],'--bundle-manifest='+directed['bundle'],'--snapshot='+remote],
                     1 if kind == 'rtc' and case['status'] == 'INVALID' else 0)
        assert actual['status'] == case['status'] and case['reason'] in actual['reason_codes'], label
        rtc = rtc_body(actual,kind); stages = {s['stage']:s for s in rtc['trace']}
        assert rtc['decision'] == []
        if 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' in stages:
            assert stages['MATERIAL_INPUT_POLICY119']['status'] == 'RESOLVED'
            assert stages['ADJUSTMENT_INPUT119']['status'] == 'RESOLVED'
            assert stages['MATERIAL_INPUT_POLICY119']['payment_proven'] is False
            geography = stages['OBSERVED_GEOGRAPHIC_REFERENCE']
            credit_policy=stages['CREDIT_INPUT_POLICY122']
            assert credit_policy['status']=='RESOLVED'
            for flag in ['credit_granted','document_authenticated','extinction_proven']:assert credit_policy[flag]is False
            assert geography['status'] == 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT'
            for flag in ['historical_validity_proven','fiscal_destination_determined','juridical_municipality_status_proven',
                         'supplier_establishment_proven','acquirer_or_recipient_domicile_proven','tax_rate_selected',
                         'legal_applicability_approved','posting_authorized','production_ready']:
                assert geography[flag] is False, (label,flag)
        transition = case.get('temporal_transition')
        if transition is not None:
            assert actual['status'] == 'ACTION_REQUIRED'
            assert 'PRICE111_COMPOSITION_EVIDENCE_REVOKED_OR_SUPERSEDED' in actual['reason_codes']
            source_facts = read(R/case['path'])['facts']
            for event,value in transition['preserved_events'].items():
                assert source_facts['rtc.export_price_composition_proof_records'][0][event] == value
                assert datetime.fromisoformat(transition['old_known_at']) < datetime.fromisoformat(value) <= datetime.fromisoformat(transition['new_known_at'])
            lifecycle_refusals += 1
        for name in ['PARTIAL_EXPORT_DATED_DOCUMENTS','PARTIAL_EXPORT_DPS_IDENTITY','PARTIAL_EXPORT_ISS_RESULTS','PARTIAL_EXPORT_SP_RESULTS']:
            assert name not in stages, (label,name)
        if 'upstream108_path' in case:
            source = old_cases[case['upstream108_path']]; snapshot = OLD/source[kind+'_snapshot']
            assert sha(snapshot) == source[kind+'_snapshot_sha256']==history_hashes[snapshot]
            old_stages = {s['stage']:s for s in rtc_body(read(snapshot)['result'],kind)['trace']}
            for name in ['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION','PARTIAL_EXPORT_FX_TRANSACTION']:
                assert stages.get(name) == old_stages.get(name), (label,name)
                nominal_fx_controls += 1
        if case['price_status'] is None:
            assert 'PARTIAL_EXPORT_CONTRACT_PRICES' not in stages and 'PARTIAL_EXPORT_ISS_PRICE_COMPOSITION' not in stages
        else:
            price = stages['PARTIAL_EXPORT_CONTRACT_PRICES']; context = stages['PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT']
            assert price['status'] == case['price_status']
            if 'expected_price_dated_reason' in case: assert price['dated_reason'] == case['expected_price_dated_reason']
            if case['composition_status'] is None: assert 'PARTIAL_EXPORT_ISS_PRICE_COMPOSITION' not in stages
            else:
                comp = stages['PARTIAL_EXPORT_ISS_PRICE_COMPOSITION']
                assert comp['status'] == case['composition_status']
                for flag in ['rtc_facts_rewritten','rtc_allocation_used_as_iss_price_rule','conditional_discount_subtracted_from_iss_price',
                    'iss_base_calculated','per_document_tax_amount_calculated','documentary_currency_conversion_qualified',
                    'payment_receipt_proven','legal_applicability_approved','government_evidence_authenticated',
                    'xml_generated','posting_authorized','production_ready']:
                    assert comp[flag] is False, (label,flag)
                if comp['status'] == 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED':
                    composed_decisions += 1
                    assert price['current_facts_hash'] == context['qualification_context_facts_hash']
                    assert comp['price110_binding_hash'] == price['binding_hash']
                    for field,key in [('price_for_iss_evaluation_in_input_currency','expected_iss_prices'),
                                      ('payment_candidate_if_conditional_terms_satisfied','expected_payment_candidates'),('iss_supply_on','expected_iss_dates')]:
                        assert [doc[field] for doc in comp['documents']] == case[key]
                    if case.get('rtc_gross_currency') == 'USD':
                        assert stages['PARTIAL_EXPORT_FX_TRANSACTION']['status'] == 'LOCAL_PARTIAL_EXPORT_FX_TRANSACTION_BOUND'
                        assert price['rtc_price_reconciled'] is False
                        usd_compositions += 1
        replay = run(label+'-replay',[replaycmd,remote]); assert replay['replay_matches'] is True
        if kind == 'integrated': assert canonical(replay['result']) == canonical(actual)
        local = C/(label+'-snapshot.json')
        subprocess.run([D,'cp',cid+':'+remote,str(local)], cwd=R, capture_output=True, check=True)
        assert canonical(read(local)['result']) == canonical(actual)
        assert canonical(read(local)['input']) == canonical(read(R/case['path']))
        record[kind+'_snapshot'] = local.name; record[kind+'_snapshot_sha256'] = sha(local)
    records.append(record)
    print(f'PASS:{index+1}/124 {case["name"]};both runners/exact replays',flush=True)

assessment = run('production-assess122-expanded',['fiscal:rtc:production-assess','--bundle-manifest='+directed['bundle'],
    '--controls=resources/fiscal/'+bundle['production_controls']['path'],'--target=local-validation'])
assert assessment['ready']is False and assessment['eligible_for_local_validation']is True
assert assessment['official_source_hashes_valid']==assessment['official_sources_checked']==98
assert len(assessment['blockers'])==128 and set(assessment['blockers'])==set(baseline_assessment['blockers'])
assert inventory() == before, 'Frozen input membership and bytes changed during CLI'
assert nominal_fx_controls == 232 and usd_compositions == 22
assert composed_decisions == 142 and lifecycle_refusals == 4
save(C/'credit122-expanded-cli-validation.json', {'status':'CREDIT122_EXPANDED_NATIVE_PRICE_GEOGRAPHY_CONTRACT_CORPUS_VERIFIED',
    'created_at':datetime.now(timezone.utc).isoformat(),'bundle':directed['bundle'],'bundle_sha256':sha(bundle_path),
    'cases':records,'native_decisions':370,'exact_replays':370,'price_native_decisions':248,'compatibility_native_decisions':122,'compatibility_cases':compat_records,'compatibility_independent_decimal_checks':compat_decimal_checks,'historical1441_current_runtime_complete':False,
    'full_captured108_nominal_and_fx_comparisons':nominal_fx_controls,'locally_composed_decisions':composed_decisions,'literal_lifecycle_refusals':lifecycle_refusals,'actual_usd_composition_decisions':usd_compositions,
    'official_sources_valid':98,'production_blocker_count':128,'runtime_hashes':directed['runtime_hashes'],
    'frozen_inputs':len(before),'input_inventory_unchanged':True,'full_upstream_corpus_complete':True,
    'document_obligation_complete':False,'full_suite_complete':False,'phase4b_complete':False,'production_ready':False})
print('PASS:370 current122 native decisions and exact replays;98 sources/128 blockers;historical1441 and full acceptance pending',flush=True)
