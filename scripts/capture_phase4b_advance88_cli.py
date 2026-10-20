"""Capture native advance decisions and exact replays without approving registration or documents."""
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
def relative(p): return p.relative_to(ROOT).as_posix()
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

E.mkdir(exist_ok=False)
manifest = read(P / 'advance88-case-manifest-v2.json')
bundle = manifest['bundle']
records = []
for case in manifest['cases']:
    record = {**case, 'scenario_sha256': sha(ROOT / case['path'])}
    for runner, command, replay_command in [('rtc', 'fiscal:rtc:run', 'fiscal:rtc:replay'),
            ('integrated', 'fiscal:scenario:run', 'fiscal:decision:replay')]:
        label = case['case'] + '-' + runner
        snapshot = E / (label + '-snapshot.json')
        result = run(label, [command, case['path'], '--bundle-manifest=' + bundle, '--snapshot=' + relative(snapshot)])
        assert result['status'] == case['status'] and case['reason'] in result['reason_codes'], (label, result['reason_codes'])
        assert read(snapshot)['result'] == result
        if runner == 'rtc':
            trace = {r['stage']: r for r in result['trace']}
            assert trace['ADVANCE_ORIGIN']['production_ready'] is False
            if case['expected_nominal'] is not None:
                nominal = trace['ADVANCE_ORIGIN_NOMINAL']; expected = case['expected_nominal']
                assert nominal['status'] == 'LOCAL_ADVANCE_NOMINAL_ONLY'
                for field in ['event_date', 'rate_date', 'base_amount']:
                    assert nominal['origin']['event'][field] == expected[field]
                for tax, amount in expected['taxes'].items():
                    assert nominal['taxes'][tax]['nominal_amount'] == amount and nominal['taxes'][tax]['cash_due'] is None
                for field in ['registration_proven', 'extinction_proven', 'cash_relief_qualified', 'posting_authorized', 'credit_appropriation_authorized', 'production_ready']:
                    assert nominal[field] is False
        elif case['expected_nominal'] is not None:
            trace = {r['type']: r['payload'] for r in result['trace']}
            assert trace['ISS_CALCULATION']['tax_amount'] == '20.00'
        replay = run(label + '-replay', [replay_command, relative(snapshot)])
        assert replay['replay_matches'] is True
        if runner == 'integrated': assert replay['result'] == result
        record[runner + '_snapshot'] = relative(snapshot)
        record[runner + '_snapshot_sha256'] = sha(snapshot)
        record[runner + '_exact_replay'] = True
    records.append(record)
    print('PASS:' + case['case'] + ';both native runners and exact replays', flush=True)
prior = []
old_corpus = read(ROOT / 'storage/app/phase4b-temporal86-cli-20261015/temporal86-cli-corpus-validation.json')
old_case = next(c for c in old_corpus['cases'] if c['case'] == 'advance-candidate-not-booked')
targets = [(old_case['rtc_snapshot'], 'fiscal:rtc:replay'), (old_case['integrated_snapshot'], 'fiscal:decision:replay')]
for item in old_corpus['prior_compatibility']:
    targets.append((item['path'], 'fiscal:decision:replay' if 'foreign-acquirer76' in item['path'] else 'fiscal:rtc:replay'))
for index, (path, command) in enumerate(targets):
    digest = sha(ROOT / path)
    assert run('historical-' + str(index), [command, path])['replay_matches'] is True
    assert sha(ROOT / path) == digest
    prior.append({'path': path, 'sha256': digest, 'exact_replay': True})
assessment = run('production-assessment', ['fiscal:rtc:production-assess', '--bundle-manifest=' + bundle, '--target=local-validation'])
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 59
old_assessment = read(ROOT / 'storage/app/phase4b-temporal86-cli-20261015/production-assessment.stdout')
assert set(assessment['blockers']) == set(old_assessment['blockers'])
save(E / 'advance88-cli-corpus-validation.json', {'status': 'ADVANCE88_NATIVE_CORPUS_VERIFIED_FULL_REGRESSION_PENDING',
    'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': bundle, 'bundle_sha256': sha(ROOT / 'resources/fiscal' / bundle),
    'cases': records, 'prior_compatibility': prior, 'native_decisions': len(records) * 2, 'exact_replays': len(records) * 2,
    'official_sources_valid': 59, 'production_blocker_count': len(assessment['blockers']), 'registration_proven': False,
    'full_suite_complete': False, 'document_settlement_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:48 native decisions/48 exact replays/5 historical;59 sources;production blockers unchanged')
