"""Exclusive expanded119 corpus:124 cases,248 own and1071 historical exact replays."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import os
import subprocess

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-material-input119-integration-20261020')
OLD = E.parent/'phase4b-dated108-integration-20261019/cli-v1'
C = E/'expanded-cli-v1'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/phase4b-material119-expanded-cli-v1'

def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f, 'sha256').hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as f: json.dump(v, f, indent=2, ensure_ascii=False)
def inventory():
    paths = [p for root in ['app','tests','resources/fiscal'] for p in (R/root).rglob('*') if p.is_file()]
    paths += [Path(__file__), R/'composer.lock', R/'phpunit.xml']
    return {str(p):sha(p) for p in sorted(set(paths))}
def rtc_body(result, kind):
    return result if kind == 'rtc' else {r['type']:r['payload'] for r in result['trace']}['CONSUMPTION_REFORM']

directed = read(E/'material119-expanded-preparation-validation.json')
assert directed['directed_tests'] == 599
for p,h in directed['runtime_hashes'].items(): assert sha(R/p) == h, p
manifests = [read(R/'tests/Fixtures'/name) for name in ['material119-price-expectations.json','material119-price-upstream-expectations.json']]
assert manifests[0]['bundle'] == manifests[1]['bundle']
cases = manifests[0]['cases']+manifests[1]['cases']; assert len(cases) == 124
bundle_path = R/'resources/fiscal'/directed['bundle']; bundle = read(bundle_path)
assert sha(bundle_path) == directed['bundle_sha256']
prior = read(OLD/'dated108-cli-validation.json')
old_cases = {r['path']:r for r in prior['cases']}
targets = [(Path(row['path']),row['command'],row['sha256']) for row in prior['historical_replays']]
for case in prior['cases']:
    for kind,cmd in [('rtc','fiscal:rtc:replay'),('integrated','fiscal:decision:replay')]:
        targets.append((OLD/case[kind+'_snapshot'],cmd,case[kind+'_snapshot_sha256']))
PREVIOUS = E.parent/'phase4b-price112-integration-20261019/cli-v1'
assert sha(PREVIOUS/'price112-cli-validation.json') == '64a6143f268520c26bbe6b3536d465e9adfea7390f8e455cfca42d12488e86c6'
for previous_case in read(PREVIOUS/'price112-cli-validation.json')['cases']:
    for kind,command in [('rtc','fiscal:rtc:replay'),('integrated','fiscal:decision:replay')]:
        targets.append((PREVIOUS/previous_case[kind+'_snapshot'],command,previous_case[kind+'_snapshot_sha256']))
PRIOR116 = E.parent/'phase4b-geographic116-integration-20261020'
archive119 = read(E/'before-integration-archive.json')
prior116 = PRIOR116/'expanded-cli-v1/geographic116-expanded-cli-validation.json'
assert sha(prior116) == archive119['source116_corpus_sha256']
for previous_case in read(prior116)['cases']:
    for kind,command in [('rtc','fiscal:rtc:replay'),('integrated','fiscal:decision:replay')]:
        targets.append((prior116.parent/previous_case[kind+'_snapshot'],command,previous_case[kind+'_snapshot_sha256']))
for previous_case in read(PRIOR116/'cli-v2/geographic116-cli-validation.json')['cases']:
    for kind,command in [('rtc','fiscal:rtc:replay'),('integrated','fiscal:decision:replay')]:
        targets.append((PRIOR116/'cli-v2'/previous_case[kind]['snapshot'],command,previous_case[kind]['snapshot_sha256']))
assert len(targets) == 1071
for path,cmd,h in targets:
    assert cmd in ['fiscal:rtc:replay','fiscal:decision:replay'] and sha(path) == h
before = inventory()
C.mkdir(exist_ok=False)
save(C/'preflight.json', {'created_at':datetime.now(timezone.utc).isoformat(),'input_hashes':before,
    'directed_audit_sha256':sha(E/'material119-expanded-preparation-validation.json'),
    'planned_decisions':248,'planned_own_replays':248,'planned_historical_replays':1071})
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
            assert sha(snapshot) == source[kind+'_snapshot_sha256']
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
        if kind == 'integrated': assert replay['result'] == actual
        local = C/(label+'-snapshot.json')
        subprocess.run([D,'cp',cid+':'+remote,str(local)], cwd=R, capture_output=True, check=True)
        assert read(local)['result'] == actual
        record[kind+'_snapshot'] = local.name; record[kind+'_snapshot_sha256'] = sha(local)
    records.append(record)
    print(f'PASS:{index+1}/124 {case["name"]};both runners/exact replays',flush=True)

history = []
for i,(path,command,digest) in enumerate(targets):
    assert sha(path) == digest
    remote = TEMP+'/history/'+str(i)+'-snapshot.json'
    subprocess.run([D,'cp',str(path),cid+':'+remote], cwd=R, capture_output=True, check=True)
    result = run('historical-'+str(i),[command,remote])
    assert result['replay_matches'] is True and sha(path) == digest
    if command == 'fiscal:decision:replay': assert result['result'] == read(path)['result']
    history.append({'path':str(path),'sha256':digest,'command':command,'exact_replay':True})
    if (i+1)%25 == 0: print(f'PASS:historical {i+1}/1071',flush=True)
assessment = run('production-assess119-expanded',['fiscal:rtc:production-assess','--bundle-manifest='+directed['bundle'],
    '--controls=resources/fiscal/'+bundle['production_controls']['path'],'--target=local-validation'])
previous = read(PREVIOUS/'production-assess112.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 98
assert len(assessment['blockers']) == 127 and set(assessment['blockers']) == set(previous['blockers'])|{'CONTROL_NOT_APPROVED:4B.2.OBSERVED_GEOGRAPHY_REFERENCE','CONTROL_NOT_APPROVED:4B.4.MATERIAL_INPUT119'}
assert inventory() == before, 'Frozen input membership and bytes changed during CLI'
assert nominal_fx_controls == 232 and usd_compositions == 22
assert composed_decisions == 142 and lifecycle_refusals == 4
save(C/'material119-expanded-cli-validation.json', {'status':'MATERIAL119_EXPANDED_NATIVE_CORPUS_VERIFIED_FULL_REGRESSION_PENDING',
    'created_at':datetime.now(timezone.utc).isoformat(),'bundle':directed['bundle'],'bundle_sha256':sha(bundle_path),
    'cases':records,'native_decisions':248,'exact_replays':248,'historical_replays':history,'historical_exact_replays':1071,
    'full_captured108_nominal_and_fx_comparisons':nominal_fx_controls,'locally_composed_decisions':composed_decisions,'literal_lifecycle_refusals':lifecycle_refusals,'actual_usd_composition_decisions':usd_compositions,
    'official_sources_valid':98,'production_blocker_count':127,'runtime_hashes':directed['runtime_hashes'],
    'frozen_inputs':len(before),'input_inventory_unchanged':True,'full_upstream_corpus_complete':True,
    'document_obligation_complete':False,'full_suite_complete':False,'phase4b_complete':False,'production_ready':False})
print('PASS:248 decisions/own exact replays,1071 historical;98 sources/127 blockers;full regression pending',flush=True)
