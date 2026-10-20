"""Independent expanded116 evidence review; never mutates captured inputs."""
from pathlib import Path
from datetime import datetime, timezone
from collections import Counter
from decimal import Decimal
import hashlib
import json
import re
from audit_phase4b_geographic116_price_preparation import verify as verify_preparation

ROOT = Path(__file__).resolve().parents[1]
BASE = Path(r'D:\ProjetoSaasExports')
E = BASE/'phase4b-geographic116-integration-20261020'
C = E/'expanded-cli-v1'
OLD_C = BASE/'phase4b-dated108-integration-20261019/cli-v1'
PREVIOUS = BASE/'phase4b-price112-integration-20261019/cli-v1'
F = BASE/'phase4b-geographic116-full-suite-20261020'
A112 = ROOT/'storage/app/phase4b-price112-development-20261019/price112-development-validation.json'
REVIEW117 = BASE/'phase4b-requirements117-subjects-audit-20261020-v1/requirements-review-v2/requirements117-subjects-review.json'
checks = 0
def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f,'sha256').hexdigest()
def canonical(v): return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def clean(p): return re.sub(r'\x1b\[[0-9;]*m','',p.read_text(encoding='utf-8-sig'))
def check(ok,label):
    global checks
    assert ok,label
    checks += 1
def body(result,kind): return result if kind == 'rtc' else {r['type']:r['payload'] for r in result['trace']}['CONSUMPTION_REFORM']
def gate(directory,label,suffix='.json',expected=0):
    record = read(directory/(label+suffix))
    check(record['exit_code'] == expected, 'exit:'+label)
    for channel in ['stdout','stderr']: check(sha(directory/(label+'.'+channel)) == record[channel+'_sha256'], 'bytes:'+label+channel)
    return record
def cli_inventory():
    paths = [p for name in ['app','tests','resources/fiscal'] for p in (ROOT/name).rglob('*') if p.is_file()]
    paths += [ROOT/'scripts/capture_phase4b_geographic116_expanded_cli.py',ROOT/'composer.lock',ROOT/'phpunit.xml']
    return {str(p):sha(p) for p in sorted(set(paths))}
def old_inputs(): return read(BASE/'phase4b-price112-full-suite-20261019/preflight.json')['input_hashes']
def inputs():
    paths = {Path(p) for p in old_inputs()}
    paths |= {p for name in ['app','tests','resources/fiscal','scripts'] for p in (ROOT/name).rglob('*') if p.is_file() and '__pycache__' not in p.parts}
    paths |= {p for p in E.rglob('*') if p.is_file()}
    paths |= {p for p in A112.parent.rglob('*') if p.is_file()}
    paths.add(REVIEW117)
    paths |= {p for p in REVIEW117.parent.parent.rglob('*') if p.is_file() and '__pycache__' not in p.parts}
    return {str(p):sha(p) for p in sorted(paths)}

def verify():
    global checks
    checks = 0
    preparation = verify_preparation()
    recorded = read(E/'geographic116-expanded-preparation-validation.json')
    for key in ['runtime_hashes','bundle','bundle_sha256','directed_tests','directed_assertions','new_manifests_sha256']:
        check(preparation[key] == recorded[key], 'current directed preparation:'+key)
    runtime = preparation['runtime_hashes']
    subjects = read(REVIEW117)
    subjects_root = REVIEW117.parent.parent
    check(subjects['runtime_hashes_verified'] == runtime and subjects['runtime_modified'] is False, '117 observed same frozen runtime')
    check(len(subjects['requirements']) == 80 and subjects['reviewed_in_this_cut'] == 10 and subjects['formal_requirements_closed'] == 0, '117 is partial audit,not closure')
    expected_reviewed = {'4B.1.'+str(i) for i in range(1,9)} | {'4B.2.'+str(i) for i in range(1,13)} | {'4B.3.'+str(i) for i in range(1,11)}
    check(set(subjects['cumulative114_115_117_unique_reviewed_ids']) == expected_reviewed and subjects['cumulative_not_reviewed_count'] == 50, 'exact30 reviewed IDs,50 not reviewed')
    for relative,key in [('native-probe-v1/execution.json','native_execution_sha256'),
                         ('native-probe-v1/observations.json','native_observations_sha256'),
                         ('primary-source-v1/primary-source-observation.json','primary_capture_sha256'),
                         ('primary-extract-v1/extraction-observation.json','primary_extraction_sha256')]:
        check(sha(subjects_root/relative) == subjects[key], '117 evidence chain:'+relative)
    subjects_execution = read(subjects_root/'native-probe-v1/execution.json')
    check(subjects_execution['exit_code'] == 0, '117 native observation terminal')
    for channel in ['stdout','stderr']:
        check(sha(subjects_root/'native-probe-v1'/('probe.'+channel)) == subjects_execution[channel+'_sha256'], '117 native bytes:'+channel)
    observed_subjects = read(subjects_root/'native-probe-v1/observations.json')
    check(observed_subjects == read(subjects_root/'native-probe-v1/probe.stdout'), '117 actual native output')
    observed_by_name = {row['name']:row for row in observed_subjects['cases']}
    findings = subjects['confirmed_material_basis_counterexamples']
    check({f['case'] for f in findings} == {'acquirer-basis-whitespace','acquirer-basis-wrong-article','acquirer-basis-arbitrary'}, 'all three known unresolved basis findings retained')
    for finding in findings:
        observed = observed_by_name[finding['case']]
        check(observed['rtc']['status'] == 'VALID' and observed['integrated']['status'] == 'VALID_WITH_WARNINGS', '117 counterexample remains unfixed:'+finding['case'])
        check(observed['input']['facts']['rtc.collection_legal_basis'] == finding['supplied_basis'], '117 material basis witness:'+finding['case'])
    for source in read(subjects_root/'primary-source-v1/primary-source-observation.json')['sources']:
        check(source['exit_code'] == 0 and sha(subjects_root/'primary-source-v1'/source['file']) == source['sha256'], '117 primary research bytes:'+source['file'])
    corpus = read(C/'geographic116-expanded-cli-validation.json')
    check(corpus['native_decisions'] == corpus['exact_replays'] == 248 and corpus['historical_exact_replays'] == 791, 'expanded corpus totals')
    check(corpus['locally_composed_decisions'] == 142 and corpus['literal_lifecycle_refusals'] == 4, '71 compositions and2 newly known refusals in both runners')
    check(corpus['runtime_hashes'] == runtime, 'same88 runtime')
    bundle_path = ROOT/'resources/fiscal'/corpus['bundle']
    check(sha(bundle_path) == corpus['bundle_sha256'] == preparation['bundle_sha256'], 'bundle unchanged')
    bundle = read(bundle_path)
    prior = read(OLD_C/'dated108-cli-validation.json')
    controls = read(ROOT/'resources/fiscal'/bundle['production_controls']['path'])
    check(canonical(controls) == bundle['production_controls']['sha256'],'controls binding')
    manifests = [read(ROOT/'tests/Fixtures'/n)['cases'] for n in ['geographic116-price-expectations.json','geographic116-price-upstream-expectations.json']]
    check([len(rows) for rows in manifests] == [74,50] and len(corpus['cases']) == 124,'124scenarios')
    expected_cases = manifests[0]+manifests[1]
    old_cases = {r['path']:r for r in prior['cases']}
    transported = [r['upstream108_path'] for r in expected_cases if 'upstream108_path' in r]
    check(set(transported) == set(old_cases) and len(transported) == 58 and set(Counter(transported).values()) == {1},'all58once')
    exits = {'production-assess116-expanded':0,**{'historical-'+str(i):0 for i in range(791)}}
    captured_controls = usd = price_checks = 0
    for index,(case,expected) in enumerate(zip(corpus['cases'],expected_cases,strict=True)):
        for k,v in expected.items(): check(case[k] == v,'manifest:'+str(index)+k)
        scenario = read(ROOT/case['path'])
        check(sha(ROOT/case['path']) == case['scenario_sha256'],'scenario:'+str(index))
        for kind,cmd,replaycmd in [('rtc','fiscal:rtc:run','fiscal:rtc:replay'),('integrated','fiscal:scenario:run','fiscal:decision:replay')]:
            label = f'{index:03d}-{kind}'; exits[label] = 1 if kind == 'rtc' and case['status'] == 'INVALID' else 0; exits[label+'-replay'] = 0
            snapshot = C/case[kind+'_snapshot']; actual = read(C/(label+'.stdout'))
            check(sha(snapshot) == case[kind+'_snapshot_sha256'] and read(snapshot)['result'] == actual,'snapshot:'+label)
            check(actual['status'] == case['status'] and case['reason'] in actual['reason_codes'],'result:'+label)
            execution = read(C/(label+'-execution.json'))
            check(execution['command'][-4:-1] == [cmd,case['path'],'--bundle-manifest='+corpus['bundle']], 'native command:'+label)
            replay = read(C/(label+'-replay.stdout')); check(replay['replay_matches'] is True,'own replay:'+label)
            check(read(C/(label+'-replay-execution.json'))['command'][-2] == replaycmd,'replay command:'+label)
            if kind == 'integrated': check(replay['result'] == actual,'whole integrated replay:'+label)
            rtc = body(actual,kind); stages = {s['stage']:s for s in rtc['trace']}
            check(rtc['decision'] == [],'no posting decision:'+label)
            if 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' in stages:
                geography = stages['OBSERVED_GEOGRAPHIC_REFERENCE']
                check(geography['status'] == 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT', 'geographic reference:'+label)
                for flag in ['historical_validity_proven','fiscal_destination_determined','juridical_municipality_status_proven',
                    'supplier_establishment_proven','acquirer_or_recipient_domicile_proven','tax_rate_selected',
                    'legal_applicability_approved','posting_authorized','production_ready']:
                    check(geography[flag] is False, 'geography limit:'+label+flag)
            transition = case.get('temporal_transition')
            if transition is not None:
                check(actual['status'] == 'ACTION_REQUIRED' and 'PRICE111_COMPOSITION_EVIDENCE_REVOKED_OR_SUPERSEDED' in actual['reason_codes'], 'preserved newly effective lifecycle refusal:'+label)
                for key,instant in transition['preserved_events'].items():
                    check(scenario['facts']['rtc.export_price_composition_proof_records'][0][key] == instant, 'unchanged lifecycle event:'+label+key)

            check(not {'PARTIAL_EXPORT_DATED_DOCUMENTS','PARTIAL_EXPORT_DPS_IDENTITY','PARTIAL_EXPORT_ISS_RESULTS','PARTIAL_EXPORT_SP_RESULTS'} & set(stages),'no old optin stages:'+label)
            if 'upstream108_path' in case:
                original = old_cases[case['upstream108_path']]; path = OLD_C/original[kind+'_snapshot']
                check(sha(path) == original[kind+'_snapshot_sha256'],'captured108bytes:'+label)
                old_stages = {s['stage']:s for s in body(read(path)['result'],kind)['trace']}
                for name in ['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION','PARTIAL_EXPORT_FX_TRANSACTION']:
                    check(stages.get(name) == old_stages.get(name),'full108stage:'+label+name); captured_controls += 1
            if case['price_status'] is None:
                check('PARTIAL_EXPORT_CONTRACT_PRICES' not in stages and 'PARTIAL_EXPORT_ISS_PRICE_COMPOSITION' not in stages,'no price before gates:'+label)
                continue
            price = stages['PARTIAL_EXPORT_CONTRACT_PRICES']; context = stages['PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT']
            check(price['status'] == case['price_status'],'price status:'+label)
            if 'expected_price_dated_reason' in case: check(price['dated_reason'] == case['expected_price_dated_reason'],'preserved refusal:'+label)
            if case['composition_status'] is None:
                check('PARTIAL_EXPORT_ISS_PRICE_COMPOSITION' not in stages,'no composition:'+label); continue
            comp = stages['PARTIAL_EXPORT_ISS_PRICE_COMPOSITION']; check(comp['status'] == case['composition_status'],'composition status:'+label)
            for flag in ['rtc_facts_rewritten','rtc_allocation_used_as_iss_price_rule','conditional_discount_subtracted_from_iss_price',
                'iss_base_calculated','per_document_tax_amount_calculated','documentary_currency_conversion_qualified','payment_receipt_proven',
                'legal_applicability_approved','government_evidence_authenticated','xml_generated','posting_authorized','production_ready']:
                check(comp[flag] is False,'no escalation:'+label+flag)
            if comp['status'] != 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED': continue
            check(price['current_facts_hash'] == context['qualification_context_facts_hash'] and comp['price110_binding_hash'] == price['binding_hash'],'fresh price binding:'+label)
            for field,key in [('price_for_iss_evaluation_in_input_currency','expected_iss_prices'),('payment_candidate_if_conditional_terms_satisfied','expected_payment_candidates'),('iss_supply_on','expected_iss_dates')]:
                check([doc[field] for doc in comp['documents']] == case[key],'document result:'+label+field)
            for doc in comp['documents']:
                source = next(r for r in scenario['facts']['rtc.export_price_composition_records'] if r['role'] == doc['role'])
                gross = Decimal(source['principal'])+sum((Decimal(a['amount']) for a in source['service_additions']),Decimal(0))
                iss = gross-sum((Decimal(a['amount']) for a in source['unconditional_discounts']),Decimal(0))
                candidate = iss-sum((Decimal(a['amount']) for a in source['conditional_discounts']),Decimal(0))
                check(Decimal(doc['price_for_iss_evaluation_in_input_currency']) == iss and Decimal(doc['payment_candidate_if_conditional_terms_satisfied']) == candidate,'independent Decimal:'+label+doc['role']); price_checks += 2
            if case.get('rtc_gross_currency') == 'USD':
                check(stages['PARTIAL_EXPORT_FX_TRANSACTION']['status'] == 'LOCAL_PARTIAL_EXPORT_FX_TRANSACTION_BOUND' and price['rtc_price_reconciled'] is False,'actualUSD:'+label); usd += 1
    check(captured_controls == corpus['full_captured108_nominal_and_fx_comparisons'] == 232,'232 full stage comparisons')
    check(usd == corpus['actual_usd_composition_decisions'] == 22,'22USD decisions')
    targets = [(r['path'],r['command'],r['sha256']) for r in prior['historical_replays']]
    targets += [(str(OLD_C/r[kind+'_snapshot']),cmd,r[kind+'_snapshot_sha256']) for r in prior['cases'] for kind,cmd in [('rtc','fiscal:rtc:replay'),('integrated','fiscal:decision:replay')]]
    previous = read(PREVIOUS/'price112-cli-validation.json')
    targets += [(str(PREVIOUS/r[kind+'_snapshot']),cmd,r[kind+'_snapshot_sha256']) for r in previous['cases'] for kind,cmd in [('rtc','fiscal:rtc:replay'),('integrated','fiscal:decision:replay')]]
    check(len(targets) == len(corpus['historical_replays']) == 791, 'all791 historic snapshots')
    for i,(record,target) in enumerate(zip(corpus['historical_replays'],targets,strict=True)):
        check((record['path'],record['command'],record['sha256']) == target,'historical scope:'+str(i))
        path = Path(record['path']); check(sha(path) == record['sha256'] and record['exact_replay'] is True,'historical bytes:'+str(i))
        replay = read(C/('historical-'+str(i)+'.stdout')); check(replay['replay_matches'] is True,'historical result:'+str(i))
        check(read(C/('historical-'+str(i)+'-execution.json'))['command'][-2] == record['command'],'historical command:'+str(i))
        if record['command'] == 'fiscal:decision:replay': check(replay['result'] == read(path)['result'],'historical whole result:'+str(i))
    labels = {p.name.removesuffix('-execution.json') for p in C.glob('*-execution.json')}
    check(labels == set(exits),'exact1288CLI execution receipts')
    for label,expected in exits.items(): gate(C,label,'-execution.json',expected)
    assessment = read(C/'production-assess116-expanded.stdout')
    command = read(C/'production-assess116-expanded-execution.json')['command']
    check('--controls=resources/fiscal/'+bundle['production_controls']['path'] in command and '--target=local-validation' in command,'explicit controls/target')
    check(assessment['ready'] is False and assessment['eligible_for_local_validation'] is True,'local only')
    check(assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 97,'97source hashes')
    check(len(assessment['blockers']) == 126 and set(assessment['blockers']) == set(read(PREVIOUS/'production-assess112.stdout')['blockers'])|{'CONTROL_NOT_APPROVED:4B.2.OBSERVED_GEOGRAPHY_REFERENCE'},'126blockers none removed')
    frozen = read(C/'preflight.json')['input_hashes']; check(cli_inventory() == frozen,'CLI frozen input membership and bytes')
    check(price_checks == 568, '568 independent Decimal comparisons')
    protected = old_inputs()
    check(len(protected) == 17418, 'all17418 original112 frozen inputs')
    archives = {
        ROOT/'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php':E/'ConsumptionReformEngine-before116.php',
        ROOT/'app/Modules/Fiscal/ConsumptionReform/TransitionCatalog.php':BASE/'phase4b-requirements114-temporal-audit-20261020-v1/integration-v1/TransitionCatalog-before.php'}
    for p,h in protected.items():
        path = Path(p)
        check(sha(archives.get(path,path)) == h, 'old112 protected or archived:'+p)
    return corpus,bundle,{'created_at':datetime.now(timezone.utc).isoformat(),'audit_checks':checks,
        'preparation_checks':preparation['audit_checks'],'independent_decimal_checks':price_checks,
        'full108stage_comparisons':captured_controls,'runtime_hashes':runtime}

if __name__ == '__main__':
    _,_,result = verify()
    print(json.dumps({k:v for k,v in result.items() if k != 'runtime_hashes'}),flush=True)
