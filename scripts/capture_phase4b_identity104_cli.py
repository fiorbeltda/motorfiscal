"""104 dual-runner native corpus and337 immutable historical replays; no production acceptance."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019')
OLD = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-integration-20261016\cli')
C = E / 'cli-v2'
F = ROOT / 'resources/fiscal'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/phase4b-identity104-cli-v2'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def save(p, value):
    with p.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)

C.mkdir(exist_ok=False)
manifest = read(E / 'identity104-case-manifest-v2.json')
bundle = read(F / manifest['bundle'])
assert len(manifest['cases']) == 45
subprocess.run([D, 'compose', 'exec', '-T', 'app', 'mkdir', '-p', TEMP + '/history'], cwd=ROOT, check=True)
container = subprocess.run([D, 'compose', 'ps', '-q', 'app'], cwd=ROOT, capture_output=True, text=True, check=True).stdout.strip()
assert container
save(C / 'process-start.json', {'created_at': datetime.now(timezone.utc).isoformat(), 'container': container,
    'bundle_sha256': sha(F / manifest['bundle']), 'scenarios': 45, 'planned_historical_replays': 337})

def run(label, args, expected_exit=0):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    record = {'command': command, 'expected_exit_code': expected_exit, 'started_at': datetime.now(timezone.utc).isoformat()}
    proc = subprocess.run(command, cwd=ROOT, capture_output=True)
    record.update(exit_code=proc.returncode, finished_at=datetime.now(timezone.utc).isoformat())
    for channel in ['stdout', 'stderr']:
        with (C / (label + '.' + channel)).open('xb') as stream:
            stream.write(getattr(proc, channel))
        record[channel + '_sha256'] = sha(C / (label + '.' + channel))
    save(C / (label + '-execution.json'), record)
    assert proc.returncode == expected_exit, (label, proc.stdout.decode(errors='replace')[-1600:], proc.stderr.decode(errors='replace'))
    return json.loads(proc.stdout)

records = []
for case in manifest['cases']:
    assert sha(ROOT / case['path']) == case['sha256']
    source = read(ROOT / case['path'])
    if 'original_path' in case:
        assert sha(ROOT / case['original_path']) == case['original_sha256']
        assert {k: v for k, v in source['facts'].items() if k not in ['rtc.assess_export_dps_identity', 'rtc.export_dps_identity_records']} == read(ROOT / case['original_path'])['facts']
    record = {**case, 'scenario_sha256': sha(ROOT / case['path'])}
    for kind, command, replay in [('rtc', 'fiscal:rtc:run', 'fiscal:rtc:replay'), ('integrated', 'fiscal:scenario:run', 'fiscal:decision:replay')]:
        label = case['name'] + '-' + kind
        target = TEMP + '/' + label + '-snapshot.json'
        expected_exit = 1 if kind == 'rtc' and case['status'] == 'INVALID' else 0
        actual = run(label, [command, case['path'], '--bundle-manifest=' + manifest['bundle'], '--snapshot=' + target], expected_exit)
        assert actual['status'] == case['status'] and case['reason'] in actual['reason_codes'], (label, actual['status'], actual['reason_codes'])
        rtc = actual if kind == 'rtc' else {r['type']: r['payload'] for r in actual['trace']}['CONSUMPTION_REFORM']
        stages = {r['stage']: r for r in rtc['trace']}
        if case['identity_status'] is None:
            assert 'PARTIAL_EXPORT_DPS_IDENTITY' not in stages
        else:
            identity = stages['PARTIAL_EXPORT_DPS_IDENTITY']
            assert identity['status'] == case['identity_status'] and identity['reason'] == case['identity_reason']
            assert stages['PARTIAL_EXPORT_ISS_RESULTS']['status'] == 'LOCAL_CURRENT_EXPORT_ISS_RESULTS_BOUND'
            assert stages['PARTIAL_EXPORT_SP_RESULTS']['status'] == 'LOCAL_CURRENT_SP_EXPORT_RESULTS_BOUND'
            for flag in ['fiscal_document_identifiers_qualified', 'cnpj_check_digits_verified', 'municipal_authorization_verified',
                'cadastral_identity_authenticated', 'remote_uniqueness_verified', 'government_evidence_authenticated',
                'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated',
                'document_obligation_complete', 'remote_deployment_proven', 'xml_generated', 'posting_authorized', 'production_ready']:
                assert identity[flag] is False, (label, flag)
            if case['identity_status'] == 'LOCAL_DPS_IDENTITY_PAIR_BOUND':
                assert [r['dps_id'] for r in identity['documents']] == case['expected_ids']
                assert [r['document_reference'] for r in identity['documents']] == [source['facts']['rtc.export_foreign_document_id'], source['facts']['rtc.export_domestic_document_id']]
        assert rtc['decision'] == []
        replayed = run(label + '-replay', [replay, target])
        assert replayed['replay_matches'] is True
        if kind == 'integrated':
            assert replayed['result'] == actual
        destination = C / (label + '-snapshot.json')
        subprocess.run([D, 'cp', container + ':' + target, str(destination)], cwd=ROOT, check=True, capture_output=True)
        assert read(destination)['result'] == actual
        record[kind + '_snapshot'] = destination.name
        record[kind + '_snapshot_sha256'] = sha(destination)
    records.append(record)
    print('PASS:' + case['name'] + ';both native runners/exact replays', flush=True)

prior = read(OLD / 'catalog101-cli-validation.json')
targets = []
for row in prior['historical_replays']:
    path = Path(row['path'])
    assert sha(path) == row['sha256']
    command = 'fiscal:decision:replay' if 'integrated-snapshot' in str(path) or 'foreign-acquirer76' in str(path) else 'fiscal:rtc:replay'
    targets.append((path, command))
for case in prior['cases']:
    for kind, command in [('rtc', 'fiscal:rtc:replay'), ('integrated', 'fiscal:decision:replay')]:
        path = OLD / case[kind + '_snapshot']
        assert sha(path) == case[kind + '_snapshot_sha256']
        targets.append((path, command))
assert len(targets) == 337
historical = []
for i, (path, command) in enumerate(targets):
    remote = TEMP + '/history/' + str(i) + '-snapshot.json'
    digest = sha(path)
    subprocess.run([D, 'cp', str(path), container + ':' + remote], cwd=ROOT, capture_output=True, check=True)
    actual = run('historical-' + str(i), [command, remote])
    assert actual['replay_matches'] is True and sha(path) == digest
    if command == 'fiscal:decision:replay':
        assert actual['result'] == read(path)['result']
    historical.append({'path': str(path), 'sha256': digest, 'command': command, 'exact_replay': True})
    if (i + 1) % 25 == 0:
        print('PASS:historical ' + str(i + 1) + '/337', flush=True)
assessment = run('production-assess104', ['fiscal:rtc:production-assess', '--bundle-manifest=' + manifest['bundle'],
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation'])
previous = read(OLD / 'production-assess101.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 71
assert set(assessment['blockers']) == set(previous['blockers']) | {'DPS_IDENTITY_LOCAL_MAPPING_ONLY_NOT_ISSUANCE_APPROVAL'}
save(C / 'identity104-cli-validation.json', {'status': 'IDENTITY104_NATIVE_CORPUS_VERIFIED_REGRESSION_PENDING',
    'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': manifest['bundle'], 'bundle_sha256': sha(F / manifest['bundle']),
    'cases': records, 'native_decisions': 90, 'exact_replays': 90, 'historical_replays': historical, 'historical_exact_replays': 337,
    'official_sources_valid': 71, 'production_blocker_count': len(assessment['blockers']),
    'document_obligation_complete': False, 'full_suite_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:90 native decisions/replays,337 historical,71 sources,123 production blockers', flush=True)
