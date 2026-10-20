"""Exclusive geographic116 CLI corpus; not a full regression or legal acceptance."""
from pathlib import Path
from datetime import datetime, timezone
import argparse
import hashlib
import json
import subprocess

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-geographic116-integration-20261020')
parser = argparse.ArgumentParser()
parser.add_argument('--attempt', choices=['cli-v1', 'cli-v2'], default='cli-v1')
ATTEMPT = parser.parse_args().attempt
C = E/ATTEMPT
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/phase4b-geographic116-'+ATTEMPT
BUNDLE = 'bundles/consumption-phase4b-2026.10.20-116-draft2.json'

def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f, 'sha256').hexdigest()
def save(p, value):
    with p.open('x', encoding='utf-8') as f: json.dump(value, f, ensure_ascii=False, indent=2)
def inventory():
    paths = [p for name in ['app', 'tests', 'resources/fiscal'] for p in (R/name).rglob('*') if p.is_file()]
    paths += [Path(__file__), R/'composer.lock', R/'phpunit.xml']
    return {str(p):sha(p) for p in sorted(paths)}
def docker(args, **kw): return subprocess.run([D, *args], cwd=R, check=True, **kw)
def run(label, args, expected=0):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    started = datetime.now(timezone.utc).isoformat()
    p = subprocess.run(command, cwd=R, capture_output=True)
    record = {'command':command, 'started_at':started, 'finished_at':datetime.now(timezone.utc).isoformat(),
              'exit_code':p.returncode, 'expected_exit_code':expected}
    for channel in ['stdout', 'stderr']:
        with (C/(label+'.'+channel)).open('xb') as f: f.write(getattr(p, channel))
        record[channel+'_sha256'] = sha(C/(label+'.'+channel))
    save(C/(label+'-execution.json'), record)
    assert p.returncode == expected, (label, p.stdout[-1600:], p.stderr[-1600:])
    return json.loads(p.stdout)
def body(result, kind):
    return result if kind == 'rtc' else next(x['payload'] for x in result['trace'] if x['type'] == 'CONSUMPTION_REFORM')

assert not C.exists(), 'Never overwrite an earlier attempt'
before = inventory()
C.mkdir()
save(C/'preflight.json', {'created_at':datetime.now(timezone.utc).isoformat(), 'input_hashes':before,
    'planned_native_decisions':32, 'planned_exact_replays':32, 'full_regression_complete':False})
docker(['compose', 'exec', '-T', 'app', 'mkdir', '-p', TEMP])
cid = docker(['compose', 'ps', '-q', 'app'], capture_output=True, text=True).stdout.strip()
bundle = read(R/'resources/fiscal'/BUNDLE)
sources = {
    'ordinary':'scenarios/consumption-phase4b/regular-saas-2026.json',
    'import':'scenarios/consumption-phase4b/import-digital-2026-document-gap.json',
    'donation':'scenarios/consumption-phase4b-2026.09.28-17/donation-market-nominal-2026.json',
    'partial':'scenarios/document-prices-2026.10.19-112-draft2/000-no-adjustments-gross-before-all-discounts.json'}
destinations = [('valid', 'SP', '3509502', None),
    ('conflict', 'RS', '3509502', 'UF_CODE_PREFIX_CONFLICT'),
    ('unknown-uf', 'XX', '3509502', 'UF_NOT_IN_OBSERVED_REFERENCE'),
    ('absent-code', 'SP', '3599999', 'TERRITORIAL_CODE_NOT_IN_OBSERVED_REFERENCE')]
valid_reasons = {'import':'RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN',
    'donation':'RTC_DONATION_DOCUMENT_OBLIGATION_NOT_MATERIALIZED',
    'partial':'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'}
limits = ['historical_validity_proven', 'fiscal_destination_determined', 'juridical_municipality_status_proven',
    'supplier_establishment_proven', 'acquirer_or_recipient_domicile_proven', 'tax_rate_selected',
    'legal_applicability_approved', 'posting_authorized', 'production_ready']
records = []
for branch, source_path in sources.items():
    for name, uf, municipality, reason in destinations:
        label = branch+'-'+name
        source = read(R/'resources/fiscal'/source_path)
        source['knowledge_at'] = '2026-10-20T12:06:00+00:00'
        source['rule_bundle_id'] = bundle['bundle_id']
        source['catalog_versions'] = bundle['catalog_versions']
        facts = source['facts']
        facts['rtc.destination_state'] = uf
        facts['rtc.destination_municipality_ibge'] = municipality
        if branch == 'import':
            facts.update({'operation.service_origin':'FOREIGN', 'operation.issuer_role':'CUSTOMER',
                          'customer.municipality_ibge':'3550308'})
        if branch == 'partial': facts['rtc.assess_export_document_prices'] = False
        local = C/(label+'-scenario.json')
        save(local, source)
        remote_input = TEMP+'/'+local.name
        docker(['cp', str(local), cid+':'+remote_input], capture_output=True)
        row = {'branch':branch, 'destination_case':name, 'source':source_path,
            'source_sha256':sha(R/'resources/fiscal'/source_path), 'scenario_sha256':sha(local)}
        for kind, command, replay in [('rtc','fiscal:rtc:run','fiscal:rtc:replay'),
                                      ('integrated','fiscal:scenario:run','fiscal:decision:replay')]:
            tag = label+'-'+kind
            status = ('INVALID' if name in ['conflict','unknown-uf'] else 'ACTION_REQUIRED') if reason else (
                ('VALID' if kind == 'rtc' else 'VALID_WITH_WARNINGS') if branch == 'ordinary' else 'ACTION_REQUIRED')
            remote_snapshot = TEMP+'/'+tag+'-snapshot.json'
            actual = run(tag, [command, remote_input, '--bundle-manifest='+BUNDLE, '--snapshot='+remote_snapshot],
                         1 if kind == 'rtc' and status == 'INVALID' else 0)
            assert actual['status'] == status, (tag, actual['status'], status)
            if reason: assert 'GEOGRAPHY115_'+reason in actual['reason_codes'], tag
            elif branch in valid_reasons: assert valid_reasons[branch] in actual['reason_codes'], tag
            rtc = body(actual, kind)
            stages = [x for x in rtc['trace'] if x['stage'] == 'OBSERVED_GEOGRAPHIC_REFERENCE']
            assert len(stages) == 1 and all(stages[0][flag] is False for flag in limits), tag
            assert stages[0]['operation_date_not_reference_validity'] == '2026-09-25', tag
            if reason: assert rtc['decision'] == [] and actual['decision'] == [], tag
            else: assert stages[0]['municipality_ibge'] == municipality and stages[0]['uf'] == uf, tag
            replayed = run(tag+'-replay', [replay, remote_snapshot])
            assert replayed['replay_matches'] is True, tag
            if kind == 'integrated': assert replayed['result'] == actual, tag
            snapshot = C/(tag+'-snapshot.json')
            docker(['cp', cid+':'+remote_snapshot, str(snapshot)], capture_output=True)
            snap = read(snapshot)
            assert snap['result'] == actual and snap['input'] == source, tag
            row[kind] = {'status':status, 'reasons':actual['reason_codes'], 'snapshot':snapshot.name,
                         'snapshot_sha256':sha(snapshot), 'exact_replay':True}
        records.append(row)
        print('PASS:'+label+'; both native runners and exact replays', flush=True)
assessment = run('production-assess116', ['fiscal:rtc:production-assess', '--bundle-manifest='+BUNDLE,
    '--controls=resources/fiscal/'+bundle['production_controls']['path'], '--target=local-validation'])
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 97
assert len(assessment['blockers']) == 126
assert inventory() == before, 'Inputs changed during capture'
save(C/'geographic116-cli-validation.json', {'created_at':datetime.now(timezone.utc).isoformat(),
    'bundle':BUNDLE, 'bundle_sha256':sha(R/'resources/fiscal'/BUNDLE), 'cases':records,
    'native_decisions':32, 'exact_replays':32, 'input_inventory_unchanged':True,
    'official_sources_valid':97, 'production_blocker_count':126,
    'historical_validity_proven':False, 'fiscal_destination_determined':False,
    'upstream112_price_composition_corpus_rebound':False, 'full_regression_complete':False,
    'phase4b_complete':False, 'production_ready':False})
print('PASS:32 native decisions/32 exact replays; historical price112 expansion and full regression still pending', flush=True)
