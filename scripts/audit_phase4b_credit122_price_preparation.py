"""Audit the124 synthetic119 transports against immutable112 business inputs."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021')
checks = 0
def canonical(v): return json.dumps(v,sort_keys=True,ensure_ascii=False,separators=(',',':'))
def same(a,b): return canonical(a)==canonical(b)
def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f, 'sha256').hexdigest()
def clean(p): return re.sub(r'\x1b\[[0-9;]*m', '', p.read_text(encoding='utf-8-sig'))
def check(ok, label):
    global checks
    assert ok, label
    checks += 1
def verify():
    global checks
    checks = 0
    baseline = read(E/'question-fix-v2/question-fix-directed-validation.json')
    check(sha(E/'question-fix-v2/question-fix-directed-validation.json') == '220307a0f8edff5fba0f47c78f5e9837bdc8b69d6dfbc07610c7820f4b3b5332', 'immutable question-repair122 directed baseline')
    runtime = baseline['runtime_hashes']
    for p,h in runtime.items(): check(sha(R/p) == h, 'unchanged runtime:'+p)
    acceptance = R/'storage/app/phase4b-price112-development-20261019/price112-development-validation.json'
    check(sha(acceptance) == '60acd38875bb50cf77f625951f21f5b5eee503f9c857d6aaa906f83590684034', 'immutable112 acceptance')
    old_files = ['price112-scenario-expectations-v2.json','price112-upstream-expectations.json']
    new_files = ['credit122-price-expectations.json','credit122-price-upstream-expectations.json']
    old_manifests = [read(R/'tests/Fixtures'/f) for f in old_files]
    new_manifests = [read(R/'tests/Fixtures'/f) for f in new_files]
    old = [row for manifest in old_manifests for row in manifest['cases']]
    new = [row for manifest in new_manifests for row in manifest['cases']]
    check(len(old) == len(new) == 124, 'all124 sources retained')
    check([r['name'] for r in new] == [r['name'] for r in old], 'one to one unchanged order and names')
    excluded = ['rtc.export_dated_dps_identity_records','rtc.export_contract_price_proof_records','rtc.export_price_composition_proof_records']
    allowed_mapping = {'dated_pair_binding_hash','dated_document_hash','observed_at','source_hash'}
    transitions = []
    positive = usd = 0
    for before, after in zip(old, new, strict=True):
        label = before['name']
        check(sha(R/before['path']) == before['scenario_sha256'], 'old input:'+label)
        check(sha(R/after['path']) == after['scenario_sha256'], 'new input:'+label)
        a, b = read(R/before['path']), read(R/after['path'])
        old_facts, new_facts = a['facts'], b['facts']
        check(same({k:v for k,v in old_facts.items() if k not in excluded},
              {k:v for k,v in new_facts.items() if k not in excluded}), 'all material and monetary facts unchanged:'+label)
        old_ids = old_facts.get(excluded[0], [])
        new_ids = new_facts.get(excluded[0], [])
        check(len(old_ids) == len(new_ids), 'identity count:'+label)
        for i, (old_id, new_id) in enumerate(zip(old_ids, new_ids, strict=True)):
            check(same({k:v for k,v in old_id.items() if k != 'proof'}, {k:v for k,v in new_id.items() if k != 'proof'}), 'identity business record:'+label+str(i))
            op, np = old_id['proof'], new_id['proof']
            changed = {k for k in set(op)|set(np) if op.get(k) != np.get(k)}
            check(changed <= allowed_mapping, 'only mapping bindings reissued:'+label+str(i))
            old_valid = op.get('source_hash') == hashlib.sha256(json.dumps({k:v for k,v in op.items() if k != 'source_hash'}, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
            new_valid = np.get('source_hash') == hashlib.sha256(json.dumps({k:v for k,v in np.items() if k != 'source_hash'}, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
            check(old_valid == new_valid, 'mapping hash defect preserved:'+label+str(i))
        for carrier in excluded[1:]:
            ap, bp = old_facts.get(carrier), new_facts.get(carrier)
            check((ap is None) == (bp is None), 'proof absence preserved:'+label+carrier)
            if ap is not None:
                check(len(ap) == len(bp), 'proof cardinality:'+label+carrier)
                for p, q in zip(ap, bp, strict=True):
                    for event in ['revoked_at','superseded_at','valid_from','valid_to']:
                        check(p.get(event) == q.get(event), 'literal proof lifecycle retained:'+label+carrier+event)
        for key in ['expected_iss_prices','expected_payment_candidates','expected_iss_dates','price_status']:
            check(same(after.get(key),before.get(key)), 'unchanged expected document semantics:'+label+key)
        transition = after.get('temporal_transition')
        if transition is None:
            for key in ['status','reason','composition_status']:
                check(after[key] == before[key], 'unchanged nominal/refusal expectation:'+label+key)
        else:
            check(before['composition_status'] == 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED', 'previously locally composed:'+label)
            check(after['status'] == after['composition_status'] == 'ACTION_REQUIRED', 'newly effective lifecycle refusal:'+label)
            check(after['reason'] == 'PRICE111_COMPOSITION_EVIDENCE_REVOKED_OR_SUPERSEDED' and transition['event_dates_rewritten'] is False, 'explicit transition:'+label)
            for event, instant in transition['preserved_events'].items():
                check(datetime.fromisoformat(a['knowledge_at']) < datetime.fromisoformat(instant) <= datetime.fromisoformat(b['knowledge_at']), 'event crosses real synthetic knowledge boundary:'+label)
                check(old_facts[excluded[2]][0][event] == new_facts[excluded[2]][0][event] == instant, 'no event moved:'+label)
            transitions.append({'name':label, **transition})
        if after['composition_status'] == 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED':
            positive += 1
            if after.get('rtc_gross_currency') == 'USD': usd += 1
    check(len(transitions) == 2 and positive == 71 and usd == 11, 'two lifecycle transitions,71 compositions,11 USD sources')
    check(len({r['upstream108_path'] for r in new if 'upstream108_path' in r}) == 58, 'all58 upstream108 sources')
    future = next(row for row in new if row.get('negative_mutation')=='composition-observation')
    future_input = read(R/future['path'])
    future_proof = future_input['facts']['rtc.export_price_composition_proof_records'][0]
    check(datetime.fromisoformat(future_proof['observed_at'])>datetime.fromisoformat(future_input['knowledge_at']), 'synthetic negative observation remains strictly future')
    prior_inputs=read(E/'credit-cli-v2/preflight.json')['input_hashes']
    scopes=[R/folder for folder in ['app','tests','resources/fiscal']]
    prior_scoped={Path(p):h for p,h in prior_inputs.items() if any(Path(p).is_relative_to(scope) for scope in scopes)}
    current_scoped={p for scope in scopes for p in scope.rglob('*') if p.is_file()}
    added_expected={R/row['path'] for row in new}|{R/'tests/Fixtures'/name for name in new_files}|{R/'tests/Fiscal/Integration/Credit122DocumentPricesTest.php'}
    check(set(prior_scoped)<=current_scoped,'no original code/test/resource deleted')
    check(current_scoped-set(prior_scoped)==added_expected and len(added_expected)==127,'only124 scenarios,2 manifests and1 test added')
    for path,digest in prior_scoped.items(): check(sha(path)==digest,'unchanged existing question-repair input:'+str(path))
    directed = clean(E/'price122-directed-v1/stdout.log')
    match = re.search(r'Tests:\s+447 passed \((\d+) assertions\)', directed)
    check(match is not None, '447 directed tests terminal')
    check('[OK] No errors' in clean(E/'price122-phpstan-v1/stdout.log'), 'general static analysis')
    check(re.search(r'PASS.*606 files', clean(E/'price122-pint-test-v1/stdout.log')) is not None, 'general style606')
    bundle = new_manifests[0]['bundle']
    check(bundle == new_manifests[1]['bundle'] == 'bundles/consumption-phase4b-2026.10.21-122-draft1.json', 'same122 bundle')
    return {'created_at':datetime.now(timezone.utc).isoformat(), 'status':'CREDIT122_124_PRICE_TRANSPORTS_PREPARED_AND_DIRECTED_VERIFIED',
        'audit_checks':checks, 'bundle':bundle, 'bundle_sha256':sha(R/'resources/fiscal'/bundle),
        'runtime_hashes':runtime, 'directed_tests':447, 'directed_assertions':int(match[1]),
        'cases':124, 'positive_compositions':positive, 'usd_composition_sources':usd,
        'literal_lifecycle_transitions':transitions,
        'source_manifests_sha256':{f:sha(R/'tests/Fixtures'/f) for f in old_files},
        'new_manifests_sha256':{f:sha(R/'tests/Fixtures'/f) for f in new_files},
        'quality_logs_sha256':{f:sha(E/f) for f in ['price122-directed-v1/stdout.log','price122-phpstan-v1/stdout.log','price122-pint-test-v1/stdout.log']},
        'source_business_facts_rewritten':False, 'source_lifecycle_events_shifted':False,
        'existing_files_verified_unchanged':len(prior_scoped),'new_resource_and_test_files':len(added_expected),
        'typed_business_facts_compared':True,'future_observation_control_preserved':True,
        'native_cli_capture_complete':False, 'full_suite_complete':False, 'phase4b_complete':False, 'production_ready':False}

if __name__ == '__main__':
    report = verify()
    with (E/'credit122-expanded-preparation-validation.json').open('x', encoding='utf-8') as stream:
        json.dump(report, stream, ensure_ascii=False, indent=2)
    print(json.dumps({k:report[k] for k in ['status','audit_checks','directed_tests','directed_assertions','cases','positive_compositions','usd_composition_sources']}), flush=True)
