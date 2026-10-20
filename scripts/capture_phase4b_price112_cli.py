"""Exclusive native112 corpus:124 cases,248 own and543 historical exact replays."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-price112-integration-20261019')
OLD = E.parent/'phase4b-dated108-integration-20261019/cli-v1'
C = E/'cli-v1'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/phase4b-price112-cli-v1'

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

directed = read(E/'price112-expanded-directed-validation.json')
assert directed['directed_tests'] == 1442
for p,h in directed['runtime_hashes'].items(): assert sha(R/p) == h, p
manifests = [read(R/'tests/Fixtures'/name) for name in ['price112-scenario-expectations-v2.json','price112-upstream-expectations.json']]
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
assert len(targets) == 543
for path,cmd,h in targets:
    assert cmd in ['fiscal:rtc:replay','fiscal:decision:replay'] and sha(path) == h
before = inventory()
C.mkdir(exist_ok=False)
save(C/'preflight.json', {'created_at':datetime.now(timezone.utc).isoformat(),'input_hashes':before,
    'directed_audit_sha256':sha(E/'price112-expanded-directed-validation.json'),
    'planned_decisions':248,'planned_own_replays':248,'planned_historical_replays':543})
subprocess.run([D,'compose','exec','-T','app','mkdir','-p',TEMP+'/history'], cwd=R, check=True)
cid = subprocess.run([D,'compose','ps','-q','app'], cwd=R, capture_output=True, text=True, check=True).stdout.strip()
assert cid
save(C/'process-start.json', {'created_at':datetime.now(timezone.utc).isoformat(),'container':cid,'collector_sha256':sha(Path(__file__))})

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

records = []; nominal_fx_controls = 0; usd_compositions = 0
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
    if (i+1)%25 == 0: print(f'PASS:historical {i+1}/543',flush=True)
assessment = run('production-assess112',['fiscal:rtc:production-assess','--bundle-manifest='+directed['bundle'],
    '--controls=resources/fiscal/'+bundle['production_controls']['path'],'--target=local-validation'])
previous = read(OLD/'production-assess108.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 94
assert len(assessment['blockers']) == 125 and set(assessment['blockers']) == set(previous['blockers'])|{'DOCUMENT_PRICE_COMPOSITION_LOCAL_ONLY_NOT_ISS_BASE_FX_OR_ISSUANCE_APPROVAL'}
assert inventory() == before, 'Frozen input membership and bytes changed during CLI'
assert nominal_fx_controls == 232 and usd_compositions == 22
save(C/'price112-cli-validation.json', {'status':'PRICE112_NATIVE_CORPUS_VERIFIED_FULL_REGRESSION_PENDING',
    'created_at':datetime.now(timezone.utc).isoformat(),'bundle':directed['bundle'],'bundle_sha256':sha(bundle_path),
    'cases':records,'native_decisions':248,'exact_replays':248,'historical_replays':history,'historical_exact_replays':543,
    'full_captured108_nominal_and_fx_comparisons':nominal_fx_controls,'actual_usd_composition_decisions':usd_compositions,
    'official_sources_valid':94,'production_blocker_count':125,'runtime_hashes':directed['runtime_hashes'],
    'frozen_inputs':len(before),'input_inventory_unchanged':True,'full_upstream_corpus_complete':True,
    'document_obligation_complete':False,'full_suite_complete':False,'phase4b_complete':False,'production_ready':False})
print('PASS:248 decisions/own exact replays,543 historical;94 sources/125 blockers;full regression pending',flush=True)
