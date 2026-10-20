"""Audit every native capture and revalidate replays after final formatting with explicitly bound controls."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
P = ROOT / 'storage/app/phase4b-advance88-preparation-20261015'
E = ROOT / 'storage/app/phase4b-advance88-cli-20261015'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as f: json.dump(v, f, ensure_ascii=False, indent=2)
def run(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    started = datetime.now(timezone.utc).isoformat()
    result = subprocess.run(command, cwd=ROOT, capture_output=True)
    for channel in ['stdout', 'stderr']:
        with (E / (label + '.' + channel)).open('xb') as f: f.write(getattr(result, channel))
    save(E / (label + '-execution.json'), {'command': command, 'exit_code': result.returncode,
        'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(),
        'stdout_sha256': sha(E / (label + '.stdout')), 'stderr_sha256': sha(E / (label + '.stderr'))})
    assert result.returncode == 0, (label, result.stdout.decode()[-1000:], result.stderr.decode())
    return json.loads(result.stdout)

target = E / 'advance88-cli-corpus-validation.json'
assert not target.exists()
manifest = read(P / 'advance88-case-manifest-v2.json'); bundle_path = manifest['bundle']
bundle = read(ROOT / 'resources/fiscal' / bundle_path)
records = []
for case in manifest['cases']:
    record = {**case, 'scenario_sha256': sha(ROOT / case['path'])}
    for runner, replay_command in [('rtc', 'fiscal:rtc:replay'), ('integrated', 'fiscal:decision:replay')]:
        label = case['case'] + '-' + runner
        snapshot = E / (label + '-snapshot.json')
        result = read(snapshot)['result']
        assert read(E / (label + '-execution.json'))['exit_code'] == 0
        assert read(E / (label + '.stdout')) == result
        assert read(E / (label + '-replay.stdout'))['replay_matches'] is True
        assert result['status'] == case['status'] and case['reason'] in result['reason_codes']
        replay = run('final-' + label + '-replay', [replay_command, snapshot.relative_to(ROOT).as_posix()])
        assert replay['replay_matches'] is True
        if runner == 'integrated': assert replay['result'] == result
        record[runner + '_snapshot'] = snapshot.relative_to(ROOT).as_posix()
        record[runner + '_snapshot_sha256'] = sha(snapshot)
        record[runner + '_exact_replay'] = True
    records.append(record)
    print('PASS:final exact replays:' + case['case'], flush=True)
old_corpus = read(ROOT / 'storage/app/phase4b-temporal86-cli-20261015/temporal86-cli-corpus-validation.json')
old_case = next(c for c in old_corpus['cases'] if c['case'] == 'advance-candidate-not-booked')
targets = [(old_case['rtc_snapshot'], 'fiscal:rtc:replay'), (old_case['integrated_snapshot'], 'fiscal:decision:replay')]
targets += [(r['path'], 'fiscal:decision:replay' if 'foreign-acquirer76' in r['path'] else 'fiscal:rtc:replay') for r in old_corpus['prior_compatibility']]
prior = []
for index, (path, command) in enumerate(targets):
    digest = sha(ROOT / path)
    assert run('final-historical-' + str(index), [command, path])['replay_matches'] is True
    assert sha(ROOT / path) == digest
    prior.append({'path': path, 'sha256': digest, 'exact_replay': True})
assessment = run('production-assessment88-bound', ['fiscal:rtc:production-assess', '--bundle-manifest=' + bundle_path,
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation'])
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 59
old = read(ROOT / 'storage/app/phase4b-temporal86-cli-20261015/production-assessment.stdout')
assert set(assessment['blockers']) == set(old['blockers'])
save(target, {'status': 'ADVANCE88_NATIVE_CORPUS_VERIFIED_FULL_REGRESSION_PENDING',
    'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': bundle_path,
    'bundle_sha256': sha(ROOT / 'resources/fiscal' / bundle_path), 'cases': records, 'prior_compatibility': prior,
    'native_decisions': 48, 'exact_replays': 48, 'final_replays_after_formatting': 48,
    'official_sources_valid': 59, 'production_blocker_count': len(assessment['blockers']),
    'full_suite_complete': False, 'document_settlement_complete': False, 'registration_proven': False,
    'phase4b_complete': False, 'production_ready': False,
    'preserved_first_assessment_note': 'First native capture used CLI default controls14, not controls88; its outputs preserved and excluded from acceptance. Explicit own controls used here.'})
print('PASS:48 native decisions/final replays,5 historical,59 sources,119 unchanged blockers;full suite pending')
