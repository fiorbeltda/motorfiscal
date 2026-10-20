"""Own101 native dual-runner corpus and immutable historical replays; evidence on D."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-integration-20261016')
OLD = Path(r'D:\ProjetoSaasExports\phase4b-sp98-integration-20261016\cli')
C = E / 'cli'
F = ROOT / 'resources/fiscal'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/phase4b-catalog101-cli'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(p, value):
    with p.open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)
C.mkdir(exist_ok=False)
subprocess.run([D, 'compose', 'exec', '-T', 'app', 'mkdir', '-p', TEMP + '/history'], cwd=ROOT, check=True)
container = subprocess.run([D, 'compose', 'ps', '-q', 'app'], cwd=ROOT, capture_output=True, text=True, check=True).stdout.strip()
assert container
manifest = read(E / 'catalog101-case-manifest-v2.json')
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
        expected = 'VALID_WITH_WARNINGS' if case['name'] == 'regular-saas-positive' and kind == 'integrated' else case['status']
        assert actual['status'] == expected, (label, actual['status'], actual['reason_codes'])
        if case['reason'] is not None: assert case['reason'] in actual['reason_codes'], (label, actual['reason_codes'])
        rtc = actual if kind == 'rtc' else {r['type']: r['payload'] for r in actual['trace']}['CONSUMPTION_REFORM']
        references = [r for r in rtc['trace'] if r['stage'] == 'CCLASSTRIB_REFERENCE']
        for reference in references:
            assert reference['status'] == 'REFERENCE_ROW_AVAILABLE' and reference['document_model'] == 'NFSE'
            for flag in ['decision_authority', 'tax_treatment_selected', 'rate_determined', 'document_indicator_emitted', 'production_ready']:
                assert reference[flag] is False
        if case['name'] == 'regular-saas-positive':
            assert len(references) == 1
            assert rtc['decision']['classification']['source_kind'] == 'ENGINE_DECISION_RULE'
            assert rtc['decision']['ibs']['nominal_amount'] == '1.00' and rtc['decision']['cbs']['nominal_amount'] == '9.00'
        replayed = run(label + '-replay', [replay, target])
        assert replayed['replay_matches'] is True
        if kind == 'integrated': assert replayed['result'] == actual
        destination = C / (label + '-snapshot.json')
        subprocess.run([D, 'cp', container + ':' + target, str(destination)], cwd=ROOT, check=True, capture_output=True)
        assert read(destination)['result'] == actual
        record[kind + '_snapshot'] = destination.name
        record[kind + '_snapshot_sha256'] = sha(destination)
    records.append(record)
    print('PASS:' + case['name'] + ';both native runners/exact replays', flush=True)
prior = read(OLD / 'sp98-cli-validation.json')
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
assert len(targets) == 215
historical = []
for i, (path, command) in enumerate(targets):
    remote = TEMP + '/history/' + str(i) + '-snapshot.json'
    digest = sha(path)
    subprocess.run([D, 'cp', str(path), container + ':' + remote], cwd=ROOT, capture_output=True, check=True)
    actual = run('historical-' + str(i), [command, remote])
    assert actual['replay_matches'] is True and sha(path) == digest
    if command == 'fiscal:decision:replay': assert actual['result'] == read(path)['result']
    historical.append({'path': str(path), 'sha256': digest, 'exact_replay': True})
    if (i + 1) % 25 == 0: print('PASS:historical ' + str(i + 1) + '/215', flush=True)
assessment = run('production-assess101', ['fiscal:rtc:production-assess', '--bundle-manifest=' + manifest['bundle'],
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation'])
previous = read(OLD / 'production-assess98.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 66
assert set(assessment['blockers']) == set(previous['blockers']) | {'CCLASSTRIB_REFERENCE_LOCAL_ONLY_NOT_CLASSIFICATION_APPROVAL'}
save(C / 'catalog101-cli-validation.json', {'status': 'CATALOG101_NATIVE_CORPUS_VERIFIED_REGRESSION_PENDING',
    'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': manifest['bundle'], 'bundle_sha256': sha(F / manifest['bundle']),
    'cases': records, 'native_decisions': 122, 'exact_replays': 122, 'historical_replays': historical, 'historical_exact_replays': 215,
    'official_sources_valid': 66, 'production_blocker_count': len(assessment['blockers']),
    'document_obligation_complete': False, 'full_suite_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:122 native decisions/replays,215 historical,66 sources,122 production blockers', flush=True)
