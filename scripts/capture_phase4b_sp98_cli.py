"""Verify current98 through both native runners and exact historical replays96/91; evidence on D."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-sp98-integration-20261016')
OLD = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-integration-20261016\cli')
C = E / 'cli'
F = ROOT / 'resources/fiscal'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/phase4b-sp98-cli'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)
C.mkdir(exist_ok=False)
subprocess.run([D, 'compose', 'exec', '-T', 'app', 'mkdir', '-p', TEMP + '/history'], cwd=ROOT, check=True)
container = subprocess.run([D, 'compose', 'ps', '-q', 'app'], cwd=ROOT, capture_output=True, text=True, check=True).stdout.strip()
assert container
manifest = read(E / 'sp98-case-manifest.json')
bundle = read(F / manifest['bundle'])
def run(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    record = {'command': command, 'started_at': datetime.now(timezone.utc).isoformat()}
    proc = subprocess.run(command, cwd=ROOT, capture_output=True)
    record.update(exit_code=proc.returncode, finished_at=datetime.now(timezone.utc).isoformat())
    for channel in ['stdout', 'stderr']:
        with (C / (label + '.' + channel)).open('xb') as stream: stream.write(getattr(proc, channel))
        record[channel + '_sha256'] = sha(C / (label + '.' + channel))
    save(C / (label + '-execution.json'), record)
    assert proc.returncode == 0, (label, proc.stdout.decode(errors='replace')[-1600:], proc.stderr.decode(errors='replace'))
    return json.loads(proc.stdout)
records = []
for case in manifest['cases']:
    record = {**case, 'scenario_sha256': sha(ROOT / case['path'])}
    for kind, command, replay in [('rtc', 'fiscal:rtc:run', 'fiscal:rtc:replay'), ('integrated', 'fiscal:scenario:run', 'fiscal:decision:replay')]:
        label = case['name'] + '-' + kind
        target = TEMP + '/' + label + '-snapshot.json'
        actual = run(label, [command, case['path'], '--bundle-manifest=' + manifest['bundle'], '--snapshot=' + target])
        assert actual['status'] == 'ACTION_REQUIRED' and case['reason'] in actual['reason_codes'], (label, actual['reason_codes'])
        rtc = actual
        if kind == 'integrated':
            trace = {r['type']: r['payload'] for r in actual['trace']}
            rtc = trace['CONSUMPTION_REFORM']
            tax = '20.00' if case['name'] == 'brl-control' else ('103.98' if case['name'] == 'usd-weekend' else '103.59')
            assert trace['ISS_CALCULATION']['tax_amount'] == tax
        stages = {r['stage']: r for r in rtc['trace']}
        local = stages.get('PARTIAL_EXPORT_SP_RESULTS')
        assert (local is not None and local['status'] == 'LOCAL_CURRENT_SP_EXPORT_RESULTS_BOUND') == case['expected_bound'], label
        if case['expected_bound']:
            assert local['archive_snapshot_replayed'] is False and local['supplier_municipality'] == '3550308'
            assert [r['role'] for r in local['documents']] == ['RTC_FOREIGN', 'RTC_DOMESTIC']
            for row in local['documents']:
                value = row['municipal_result']
                candidate = value['result_subject_scope'] == 'ABROAD' and value['br_person_link'] == 'NO_BR_LINK'
                assert value['export_non_incidence_candidate'] is candidate and value['candidate_tribISSQN'] == ('3' if candidate else None)
            for flag in ['economic_operation_identity_authenticated', 'fiscal_document_identifiers_qualified',
                'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated',
                'full_operation_iss_rewritten', 'source_effective_date_proven', 'legal_applicability_approved', 'xml_generated',
                'document_obligation_complete', 'posting_authorized', 'production_ready']:
                assert local[flag] is False
        if 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' in stages:
            for key, value in case['independent_decimal'].items():
                row = stages['PARTIAL_EXPORT_GROSS_ALLOCATION'] if key == 'total_gross_brl' else stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']
                assert row[key] == value, (label, key)
        replayed = run(label + '-replay', [replay, target])
        assert replayed['replay_matches'] is True
        if kind == 'integrated': assert replayed['result'] == actual
        destination = C / (label + '-snapshot.json')
        subprocess.run([D, 'cp', container + ':' + target, str(destination)], cwd=ROOT, check=True, capture_output=True)
        assert read(destination)['result'] == actual
        record[kind + '_snapshot'] = destination.name
        record[kind + '_snapshot_sha256'] = sha(destination)
    records.append(record)
    print('PASS:' + case['name'] + ';both native runners/exact replays;document gate preserved', flush=True)
prior = read(OLD / 'export-iss96-cli-validation.json')
targets = []
for row in prior['historical_replays']:
    path = row['path']
    command = 'fiscal:decision:replay' if 'integrated-snapshot' in path or 'foreign-acquirer76' in path else 'fiscal:rtc:replay'
    targets.append((ROOT / path, path, command))
assert len(targets) == 113
for case in prior['cases']:
    for kind, command in [('rtc', 'fiscal:rtc:replay'), ('integrated', 'fiscal:decision:replay')]:
        path = OLD / case[kind + '_snapshot']
        remote = TEMP + '/history/' + path.name
        assert sha(path) == case[kind + '_snapshot_sha256']
        subprocess.run([D, 'cp', str(path), container + ':' + remote], cwd=ROOT, capture_output=True, check=True)
        targets.append((path, remote, command))
assert len(targets) == 155
historical = []
for i, (path, remote, command) in enumerate(targets):
    digest = sha(path)
    actual = run('historical-' + str(i), [command, remote])
    assert actual['replay_matches'] is True and sha(path) == digest
    if command == 'fiscal:decision:replay': assert actual['result'] == read(path)['result']
    historical.append({'path': str(path), 'sha256': digest, 'exact_replay': True})
    if (i + 1) % 25 == 0: print('PASS:historical exact replays ' + str(i + 1) + '/155', flush=True)
assessment = run('production-assess98', ['fiscal:rtc:production-assess', '--bundle-manifest=' + manifest['bundle'],
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation'])
previous = read(OLD / 'production-assess96.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 62
assert set(assessment['blockers']) == set(previous['blockers']) | {'SP_SOFTWARE_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY'}
save(C / 'sp98-cli-validation.json', {'status': 'SP98_NATIVE_CORPUS_VERIFIED_REGRESSION_PENDING',
    'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': manifest['bundle'], 'bundle_sha256': sha(F / manifest['bundle']),
    'cases': records, 'native_decisions': 60, 'exact_replays': 60, 'historical_replays': historical, 'historical_exact_replays': 155,
    'official_sources_valid': 62, 'production_blocker_count': len(assessment['blockers']),
    'document_obligation_complete': False, 'full_suite_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:60 native decisions/replays,155 historical replays,62 sources,121 production blockers', flush=True)
