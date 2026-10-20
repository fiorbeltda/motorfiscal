"""Read-only independent112 evidence audit; no execution or acceptance side effects."""
from pathlib import Path
from collections import Counter
from decimal import Decimal
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
BASE = Path(r'D:\ProjetoSaasExports')
E = BASE/'phase4b-price112-integration-20261019'
C = E/'cli-v1'
F = BASE/'phase4b-price112-full-suite-20261019'
OLD_C = BASE/'phase4b-dated108-integration-20261019/cli-v1'
A108 = ROOT/'storage/app/phase4b-dated108-development-20261019/dated108-development-validation.json'
checks = 0
def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f,'sha256').hexdigest()
def canonical(v): return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def clean(p): return re.sub(r'\x1b\[[0-9;]*m','',p.read_text(encoding='utf-8',errors='replace'))
def body(result,kind): return result if kind == 'rtc' else {r['type']:r['payload'] for r in result['trace']}['CONSUMPTION_REFORM']
def check(ok,label):
    global checks
    assert ok,label
    checks += 1
def gate(directory,label,suffix='.json',expected=0):
    record = read(directory/(label+suffix))
    check(record['exit_code'] == expected,'exit:'+label)
    for ch in ['stdout','stderr']: check(sha(directory/(label+'.'+ch)) == record[ch+'_sha256'],'bytes:'+label+ch)
    return record
def cli_inventory():
    paths = [p for root in ['app','tests','resources/fiscal'] for p in (ROOT/root).rglob('*') if p.is_file()]
    paths += [ROOT/'scripts/capture_phase4b_price112_cli.py',ROOT/'composer.lock',ROOT/'phpunit.xml']
    return {str(p):sha(p) for p in sorted(set(paths))}
def old_inputs(): return read(BASE/'phase4b-dated108-full-suite-20261019/preflight.json')['input_hashes']
def inputs():
    paths = {Path(p) for p in old_inputs()}
    paths |= {p for root in ['app','tests','resources/fiscal','scripts'] for p in (ROOT/root).rglob('*') if p.is_file() and '__pycache__' not in p.parts}
    paths |= {p for p in E.rglob('*') if p.is_file()}
    return {str(p):sha(p) for p in sorted(paths)}

def verify():
    global checks
    checks = 0
    check(sha(A108) == '5d7585b577d2b974e2df09062eb8a271c16cb8870ae5952b11d132556a96c53e','108 immutable acceptance')
    initial = read(E/'price112-initial-directed-validation.json')
    check(sha(E/'price112-initial-directed-validation.json') == 'd991e06414407b5fcda7806252ab9ccad5db15bf884ab5853485cbff4dd9ef52','initial audit immutable')
    directed = read(E/'price112-expanded-directed-validation.json'); runtime = directed['runtime_hashes']
    check(directed['directed_tests'] == 1442 and directed['directed_assertions'] == 21844,'directed counts')
    check(runtime == initial['runtime_hashes'] and len(runtime) == 87,'same87runtime')
    for p,h in runtime.items(): check(sha(ROOT/p) == h,'runtime:'+p)
    for label in ['combined-directed-v4','phpstan-v3','pint-v4']:
        check(gate(E,label)['runtime_hashes'] == runtime,'gate runtime:'+label)
    check('1442 passed (21844 assertions)' in clean(E/'combined-directed-v4.stdout'),'test terminal log')
    check('[OK] No errors' in clean(E/'phpstan-v3.stdout'),'phpstan')
    check(re.search(r'PASS.*581 files',clean(E/'pint-v4.stdout')) is not None,'pint581')
    corpus = read(C/'price112-cli-validation.json')
    check(corpus['native_decisions'] == corpus['exact_replays'] == 248,'native decisions and replays')
    check(corpus['historical_exact_replays'] == 543 and len(corpus['historical_replays']) == 543,'historical543')
    check(corpus['runtime_hashes'] == runtime and corpus['production_ready'] is False,'corpus runtime and production')
    prior = read(OLD_C/'dated108-cli-validation.json')
    check(sha(OLD_C/'dated108-cli-validation.json') == read(A108)['corpus_sha256'],'108 corpus bound')
    bundle_path = ROOT/'resources/fiscal'/corpus['bundle']; bundle = read(bundle_path)
    check(sha(bundle_path) == corpus['bundle_sha256'] == directed['bundle_sha256'],'bundle binding')
    previous = read(ROOT/'resources/fiscal/bundles/consumption-phase4b-2026.10.19-108-draft3.json')
    check(bundle['status'] == 'SANDBOX_CANDIDATE' and len(bundle['official_sources']) == 94,'sandbox94sources')
    check(bundle['official_sources'][:76] == previous['official_sources'],'preserved76sources')
    for source in bundle['official_sources']: check(sha(ROOT/'resources/fiscal/sources/official'/source['file']) == source['sha256'],'source:'+source['id'])
    for role in ['rtc_rate_catalog','rtc_transition_catalog','municipal_parameters','service_catalog','rules']:
        check(bundle['resource_paths'][role] == previous['resource_paths'][role],'unchanged resource:'+role)
    for role in ['fact_definitions','questions','rtc_code_catalog','scenario_schema']:
        check(canonical(read(ROOT/'resources/fiscal'/bundle['resource_paths'][role])) == bundle['content_hashes'][role],'resource hash:'+role)
    for role in ['fact_definitions','questions']:
        new = read(ROOT/'resources/fiscal'/bundle['resource_paths'][role]); old = read(ROOT/'resources/fiscal'/previous['resource_paths'][role])
        check(len(new) == len(old)+7 and new[:len(old)] == old,'seven typed additions:'+role)
    controls = read(ROOT/'resources/fiscal'/bundle['production_controls']['path'])
    check(canonical(controls) == bundle['production_controls']['sha256'],'controls binding')
    manifests = [read(ROOT/'tests/Fixtures'/n)['cases'] for n in ['price112-scenario-expectations-v2.json','price112-upstream-expectations.json']]
    check([len(rows) for rows in manifests] == [74,50] and len(corpus['cases']) == 124,'124scenarios')
    expected_cases = manifests[0]+manifests[1]
    old_cases = {r['path']:r for r in prior['cases']}
    transported = [r['upstream108_path'] for r in expected_cases if 'upstream108_path' in r]
    check(set(transported) == set(old_cases) and len(transported) == 58 and set(Counter(transported).values()) == {1},'all58once')
    exits = {'production-assess112':0,**{'historical-'+str(i):0 for i in range(543)}}
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
    for i,(record,target) in enumerate(zip(corpus['historical_replays'],targets,strict=True)):
        check((record['path'],record['command'],record['sha256']) == target,'historical scope:'+str(i))
        path = Path(record['path']); check(sha(path) == record['sha256'] and record['exact_replay'] is True,'historical bytes:'+str(i))
        replay = read(C/('historical-'+str(i)+'.stdout')); check(replay['replay_matches'] is True,'historical result:'+str(i))
        check(read(C/('historical-'+str(i)+'-execution.json'))['command'][-2] == record['command'],'historical command:'+str(i))
        if record['command'] == 'fiscal:decision:replay': check(replay['result'] == read(path)['result'],'historical whole result:'+str(i))
    labels = {p.name.removesuffix('-execution.json') for p in C.glob('*-execution.json')}
    check(labels == set(exits),'exact1040CLI execution receipts')
    for label,expected in exits.items(): gate(C,label,'-execution.json',expected)
    assessment = read(C/'production-assess112.stdout')
    command = read(C/'production-assess112-execution.json')['command']
    check('--controls=resources/fiscal/'+bundle['production_controls']['path'] in command and '--target=local-validation' in command,'explicit controls/target')
    check(assessment['ready'] is False and assessment['eligible_for_local_validation'] is True,'local only')
    check(assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 94,'94source hashes')
    check(len(assessment['blockers']) == 125 and set(assessment['blockers']) == set(read(OLD_C/'production-assess108.stdout')['blockers'])|{'DOCUMENT_PRICE_COMPOSITION_LOCAL_ONLY_NOT_ISS_BASE_FX_OR_ISSUANCE_APPROVAL'},'125blockers none removed')
    frozen = read(C/'preflight.json')['input_hashes']; check(cli_inventory() == frozen,'CLI frozen input membership and bytes')
    authorized = {ROOT/'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',ROOT/'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php'}
    protected = old_inputs(); check(len(protected) == 13395,'13395old inputs')
    for p,h in protected.items():
        path = Path(p)
        check(sha(E/'before-integration'/path.relative_to(ROOT) if path in authorized else path) == h,'old protected/archive:'+p)
    return corpus,bundle,{'audit_checks':checks,'independent_decimal_checks':price_checks,'full108stage_comparisons':captured_controls,'runtime_hashes':runtime}

if __name__ == '__main__':
    _,_,result = verify()
    print(json.dumps({k:v for k,v in result.items() if k != 'runtime_hashes'}),flush=True)
