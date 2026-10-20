"""Own96 dual-runner CLI corpus plus exact historical replays; all new evidence stays on D."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-integration-20261016')
C = E / 'cli'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/phase4b-export-iss96-cli'
F = ROOT / 'resources/fiscal'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)

C.mkdir(exist_ok=False)
subprocess.run([D, 'compose', 'exec', '-T', 'app', 'mkdir', TEMP], cwd=ROOT, check=True)
container = subprocess.run([D, 'compose', 'ps', '-q', 'app'], cwd=ROOT, capture_output=True, text=True, check=True).stdout.strip()
assert container
manifest = read(E / 'export-iss96-case-manifest-v2.json')
bundle = read(F / manifest['bundle'])

def run(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    started = datetime.now(timezone.utc).isoformat()
    proc = subprocess.run(command, cwd=ROOT, capture_output=True)
    record = {'command': command, 'exit_code': proc.returncode, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat()}
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
            trace = {row['type']: row['payload'] for row in actual['trace']}
            rtc = trace['CONSUMPTION_REFORM']
            expected_iss = '20.00' if case['name'] in ['brl-control', 'future-cbs-not-guessed'] else ('103.98' if case['name'] == 'usd-weekend' else '103.59')
            assert trace['ISS_CALCULATION']['tax_amount'] == expected_iss
        stages = {row['stage']: row for row in rtc['trace']}
        local = stages.get('PARTIAL_EXPORT_ISS_RESULTS')
        assert (local is not None and local['status'] == 'LOCAL_CURRENT_EXPORT_ISS_RESULTS_BOUND') == case['expected_bound']
        if case['expected_bound']:
            assert local['archive_snapshot_replayed'] is False
            for flag in ['economic_operation_identity_authenticated', 'fiscal_document_identifiers_qualified',
                'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated',
                'full_operation_iss_rewritten', 'source_effective_date_proven', 'legal_applicability_approved',
                'xml_generated', 'document_obligation_complete', 'posting_authorized', 'production_ready']:
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
    print('PASS:' + case['name'] + ';dual native runners/exact replays;document gate preserved', flush=True)

prior = read(ROOT / 'storage/app/phase4b-advance91-cli-20261015/advance91-cli-corpus-validation.json')
targets = []
for case in prior['cases']:
    targets += [(case['rtc_snapshot'], 'fiscal:rtc:replay'), (case['integrated_snapshot'], 'fiscal:decision:replay')]
for row in prior['prior_compatibility']:
    path = row['path']
    targets.append((path, 'fiscal:decision:replay' if 'integrated-snapshot' in path or 'foreign-acquirer76' in path else 'fiscal:rtc:replay'))
assert len(targets) == 113
historical = []
for i, (path, command) in enumerate(targets):
    digest = sha(ROOT / path)
    result = run('historical-' + str(i), [command, path])
    assert result['replay_matches'] is True and sha(ROOT / path) == digest
    if command == 'fiscal:decision:replay': assert result['result'] == read(ROOT / path)['result']
    historical.append({'path': path, 'sha256': digest, 'exact_replay': True})
    if (i + 1) % 25 == 0: print('PASS:historical exact replays ' + str(i + 1) + '/113', flush=True)
assessment = run('production-assess96', ['fiscal:rtc:production-assess', '--bundle-manifest=' + manifest['bundle'],
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation'])
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 60
previous = read(ROOT / 'storage/app/phase4b-advance91-cli-20261015/production-assessment91-bound.stdout')
assert set(assessment['blockers']) == set(previous['blockers']) | {'ISS_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY'}
save(C / 'export-iss96-cli-validation.json', {'status': 'EXPORT_ISS96_NATIVE_CORPUS_VERIFIED_REGRESSION_PENDING',
    'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': manifest['bundle'], 'bundle_sha256': sha(F / manifest['bundle']),
    'cases': records, 'native_decisions': 42, 'exact_replays': 42, 'historical_replays': historical,
    'historical_exact_replays': 113, 'official_sources_valid': 60, 'production_blocker_count': len(assessment['blockers']),
    'document_obligation_complete': False, 'full_suite_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:42 native decisions/replays,113 historical replays,60 sources,120 production blockers', flush=True)
