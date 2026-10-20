"""Independent read-only review of the final directed116 and CLI-v2 evidence."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-geographic116-integration-20261020')
C = E/'cli-v2'
checks = 0
def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f, 'sha256').hexdigest()
def canonical(v): return hashlib.sha256(json.dumps(v, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
def check(condition, label):
    global checks
    assert condition, label
    checks += 1
def clean(p): return re.sub(r'\x1b\[[0-9;]*m', '', p.read_text(encoding='utf-8-sig', errors='strict'))
def body(result, kind):
    return result if kind == 'rtc' else next(x['payload'] for x in result['trace'] if x['type'] == 'CONSUMPTION_REFORM')
def gate(label):
    record = read(C/(label+'-execution.json'))
    check(record['exit_code'] == record['expected_exit_code'], 'exit:'+label)
    for channel in ['stdout','stderr']:
        check(sha(C/(label+'.'+channel)) == record[channel+'_sha256'], 'log:'+label+channel)
    return read(C/(label+'.stdout'))

def verify():
    global checks
    checks = 0
    acceptance = R/'storage/app/phase4b-price112-development-20261019/price112-development-validation.json'
    check(sha(acceptance) == '60acd38875bb50cf77f625951f21f5b5eee503f9c857d6aaa906f83590684034', '112 immutable acceptance')
    accepted = read(acceptance)
    previous_runtime = accepted['runtime_hashes']
    changed = sorted(p for p,h in previous_runtime.items() if sha(R/p) != h)
    expected = sorted(['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
                       'app/Modules/Fiscal/ConsumptionReform/TransitionCatalog.php'])
    check(changed == expected, 'only expected post112 runtime changes')
    for p,h in previous_runtime.items():
        if p not in expected: check(sha(R/p) == h, 'unchanged runtime:'+p)
    check(sha(E/'ConsumptionReformEngine-before116.php') == previous_runtime[expected[0]], 'archived112 engine')
    check(sha(R/expected[1]) == 'd2f0bab70ebedf25187184c8b5db0f9dec863b612a7119df70b07a51e1430906', 'preserved114 fix')
    report = read(C/'geographic116-cli-validation.json')
    check(report['native_decisions'] == report['exact_replays'] == 32 and len(report['cases']) == 16, 'native and replay totals')
    for p,h in read(C/'preflight.json')['input_hashes'].items(): check(sha(Path(p)) == h, 'frozen CLI input:'+p)
    bundle_path = R/'resources/fiscal'/report['bundle']
    check(sha(bundle_path) == report['bundle_sha256'], 'bundle unchanged')
    bundle = read(bundle_path)
    codes = read(R/'resources/fiscal'/bundle['resource_paths']['rtc_code_catalog'])
    old = read(R/'resources/fiscal/catalogs/rtc-codes-2026.10.19-112-draft2.json')
    stripped = {k:v for k,v in codes.items() if k not in ['observed_geographic_reference','observed_geographic_reference_policy']}
    stripped['version'] = old['version']
    check(stripped == old, 'prior catalog semantics unchanged')
    ref = codes['observed_geographic_reference']
    states = read(R/'resources/fiscal/sources/official/geographic116-ibge-estados.json')
    territories = read(R/'resources/fiscal/sources/official/geographic116-ibge-municipios.json')
    check(len(states) == 27 and len(territories) == 5571, 'all official rows retained')
    for index, row in enumerate(states, 1):
        check(ref['states'][row['sigla']] == {'ibge_uf_code':str(row['id']), 'name':row['nome'], 'source_row':index}, 'raw state:'+row['sigla'])
    for index, row in enumerate(territories, 1):
        uf = row['regiao-imediata']['regiao-intermediaria']['UF']
        code = str(row['id'])
        check(ref['municipalities'][code] == {'name':row['nome'], 'uf':uf['sigla'], 'ibge_uf_code':str(uf['id']), 'source_row':index}, 'raw territory:'+code)
        check(code[:2] == str(uf['id']), 'territory prefix:'+code)
    check(canonical(ref['states']) == ref['states_sha256'] == codes['observed_geographic_reference_policy']['states_sha256'], 'state canonical binding')
    check(canonical(ref['municipalities']) == ref['municipalities_sha256'] == codes['observed_geographic_reference_policy']['municipalities_sha256'], 'municipal canonical binding')
    for filename, provenance in ref['sources'].items():
        check(sha(R/'resources/fiscal/sources/official'/('geographic116-'+filename)) == provenance['sha256'], 'raw source:'+filename)
    for key in ['historical_validity_proven','fiscal_location_determined','production_ready']:
        check(ref[key] is False, 'reference limit:'+key)
    check('effective_from' not in ref and 'effective_to' not in ref, 'no invented validity')
    limits = ['historical_validity_proven','fiscal_destination_determined','juridical_municipality_status_proven',
        'supplier_establishment_proven','acquirer_or_recipient_domicile_proven','tax_rate_selected',
        'legal_applicability_approved','posting_authorized','production_ready']
    seen = set()
    for case in report['cases']:
        label = case['branch']+'-'+case['destination_case']
        check(label not in seen, 'unique case:'+label)
        seen.add(label)
        scenario_path = C/(label+'-scenario.json')
        check(sha(scenario_path) == case['scenario_sha256'], 'input:'+label)
        source = read(scenario_path)
        check(source['rule_bundle_id'] == bundle['bundle_id'] and source['catalog_versions'] == bundle['catalog_versions'], 'new bundle bindings:'+label)
        check(datetime.fromisoformat(source['knowledge_at']) >= datetime.fromisoformat(bundle['knowledge_available_at']), 'knowledge time:'+label)
        for kind in ['rtc','integrated']:
            tag = label+'-'+kind
            result = gate(tag)
            destination = case['destination_case']
            expected_status = ('INVALID' if destination in ['conflict','unknown-uf'] else 'ACTION_REQUIRED') if destination != 'valid' else (
                ('VALID' if kind == 'rtc' else 'VALID_WITH_WARNINGS') if case['branch'] == 'ordinary' else 'ACTION_REQUIRED')
            check(result['status'] == expected_status, 'independent expected status:'+tag)
            if destination != 'valid':
                reason = {'conflict':'UF_CODE_PREFIX_CONFLICT','unknown-uf':'UF_NOT_IN_OBSERVED_REFERENCE',
                          'absent-code':'TERRITORIAL_CODE_NOT_IN_OBSERVED_REFERENCE'}[destination]
                check('GEOGRAPHY115_'+reason in result['reason_codes'], 'independent expected reason:'+tag)
            replay = gate(tag+'-replay')
            record = case[kind]
            snapshot = C/record['snapshot']
            check(sha(snapshot) == record['snapshot_sha256'], 'snapshot bytes:'+tag)
            captured = read(snapshot)
            check(captured['input'] == source and captured['result'] == result, 'captured native result:'+tag)
            check(replay['replay_matches'] is True, 'exact replay:'+tag)
            if kind == 'integrated': check(replay['result'] == result, 'exact integrated body:'+tag)
            rtc = body(result, kind)
            trace = [s for s in rtc['trace'] if s['stage'] == 'OBSERVED_GEOGRAPHIC_REFERENCE']
            check(len(trace) == 1, 'one geography gate:'+tag)
            for limit in limits: check(trace[0][limit] is False, 'scope limit:'+tag+limit)
            check(trace[0]['operation_date_not_reference_validity'] == '2026-09-25', 'explicit date limit:'+tag)
            if case['destination_case'] == 'valid':
                check(trace[0]['status'] == 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT', 'coherent reference:'+tag)
            else:
                check(result['decision'] == [] and rtc['decision'] == [], 'no decision on refusal:'+tag)
                check(any(r.startswith('GEOGRAPHY115_') for r in result['reason_codes']), 'refusal reason:'+tag)
    assessment = gate('production-assess116')
    check(assessment['ready'] is False and assessment['eligible_for_local_validation'] is True, 'production blocked')
    check(assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 97, 'all97 sources')
    check(len(assessment['blockers']) == 126, '126 production blockers')
    check('96 passed (416 assertions)' in clean(E/'directed-v5.log'), 'final96 targeted tests')
    check('[OK] No errors' in clean(E/'phpstan-v2.log'), 'final PHPStan')
    check(re.search(r'PASS.*584 files', clean(E/'pint-final-v2.log')) is not None, 'final Pint584')
    return {'created_at':datetime.now(timezone.utc).isoformat(), 'status':'GEOGRAPHIC116_TARGETED_AND_32_NATIVE_REPLAYS_VERIFIED',
        'independent_checks':checks, 'targeted_tests':96, 'targeted_assertions':416, 'new_permanent_tests':80,
        'native_decisions':32, 'exact_replays':32, 'official_sources_valid':97, 'production_blocker_count':126,
        'bundle':report['bundle'], 'bundle_sha256':sha(bundle_path),
        'runtime_hashes':{p:sha(R/p) for p in sorted(set(previous_runtime)|{'app/Modules/Fiscal/ConsumptionReform/ObservedGeographicReference115.php'})},
        'previous112_acceptance_preserved':True, 'changed_since112':changed,
        'cli_report_sha256':sha(C/'geographic116-cli-validation.json'),
        'final_logs_sha256':{p:sha(E/p) for p in ['directed-v5.log','phpstan-v2.log','pint-final-v2.log']},
        'historical_price112_corpus_rebound':False, 'historical_replays_recaptured_after116':False,
        'full_regression_complete':False, 'historical_validity_proven':False, 'fiscal_destination_determined':False,
        'legal_applicability_approved':False, 'phase4b_complete':False, 'production_ready':False}

if __name__ == '__main__': print(json.dumps(verify(), ensure_ascii=False, indent=2))
