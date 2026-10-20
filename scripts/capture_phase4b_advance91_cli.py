"""Capture recorded-advance91 native decisions, exact replays and every predecessor88 replay."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
P = ROOT / 'storage/app/phase4b-advance91-integration-20261015'
E = ROOT / 'storage/app/phase4b-advance91-cli-20261015'
F = ROOT / 'resources/fiscal'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def relative(p): return p.relative_to(ROOT).as_posix()
def save(p, value):
    with p.open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)
def run(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    started = datetime.now(timezone.utc).isoformat()
    result = subprocess.run(command, cwd=ROOT, capture_output=True)
    for channel in ['stdout', 'stderr']:
        with (E / (label + '.' + channel)).open('xb') as stream: stream.write(getattr(result, channel))
    save(E / (label + '-execution.json'), {'command': command, 'exit_code': result.returncode, 'started_at': started,
        'finished_at': datetime.now(timezone.utc).isoformat(), 'stdout_sha256': sha(E / (label + '.stdout')),
        'stderr_sha256': sha(E / (label + '.stderr'))})
    assert result.returncode == 0, (label, result.stdout.decode()[-1000:], result.stderr.decode())
    return json.loads(result.stdout)

E.mkdir(exist_ok=False)
manifest = read(P / 'advance91-case-manifest-v2.json')
bundle_path = manifest['bundle']; bundle = read(F / bundle_path)
records = []
for case in manifest['cases']:
    record = {**case, 'scenario_sha256': sha(ROOT / case['path'])}
    for runner, command, replay_command in [('rtc', 'fiscal:rtc:run', 'fiscal:rtc:replay'),
        ('integrated', 'fiscal:scenario:run', 'fiscal:decision:replay')]:
        label = case['case'] + '-' + runner; snapshot = E / (label + '-snapshot.json')
        result = run(label, [command, case['path'], '--bundle-manifest=' + bundle_path, '--snapshot=' + relative(snapshot)])
        assert result['status'] == case['status'] and case['reason'] in result['reason_codes'], (label, result['reason_codes'])
        assert read(snapshot)['result'] == result
        if runner == 'rtc' and case['expected_cbs_balance'] is not None:
            trace = {row['stage']: row for row in result['trace']}
            local = trace['RECORDED_ADVANCE_RECONCILIATION']
            assert local['status'] == 'LOCAL_CAPTURED_ADVANCE_FINAL_RECONCILIATION_ONLY'
            assert local['taxes']['CBS']['arithmetic_balance'] == case['expected_cbs_balance']
            assert local['prior_registration_locally_bound'] is True
            for flag in ['posting_authorized', 'prior_advance_debits_proven', 'actual_registration_consumed',
                'credit_appropriation_authorized', 'extinction_proven', 'government_evidence_authenticated', 'production_ready']:
                assert local[flag] is False
            for tax in ['IBS_STATE', 'IBS_MUNICIPAL', 'CBS']:
                assert local['taxes'][tax]['cash_due'] is None and local['taxes'][tax]['credit_granted'] == '0.00'
            basis = {'document-issue-presumption': 'DOCUMENT_ISSUE_PRESUMPTION',
                'document-expected-date-presumption': 'DOCUMENT_EXPECTED_DATE_PRESUMPTION'}.get(case['case'], 'ACTUAL_SUPPLY_EVIDENCE')
            assert local['qualified_final_date_basis'] == basis
        if runner == 'integrated' and case['expected_cbs_balance'] is not None:
            trace = {row['type']: row['payload'] for row in result['trace']}
            assert trace['ISS_CALCULATION']['tax_amount'] == '20.00'
        replay = run(label + '-replay', [replay_command, relative(snapshot)])
        assert replay['replay_matches'] is True
        if runner == 'integrated': assert replay['result'] == result
        record[runner + '_snapshot'] = relative(snapshot); record[runner + '_snapshot_sha256'] = sha(snapshot)
        record[runner + '_exact_replay'] = True
    records.append(record)
    print('PASS:' + case['case'] + ';native dual runners/exact replays', flush=True)
prior = []
old = read(ROOT / 'storage/app/phase4b-advance88-cli-20261015/advance88-cli-corpus-validation.json')
targets = []
for case in old['cases']:
    targets += [(case['rtc_snapshot'], 'fiscal:rtc:replay'), (case['integrated_snapshot'], 'fiscal:decision:replay')]
for row in old['prior_compatibility']:
    targets.append((row['path'], 'fiscal:decision:replay' if 'integrated-snapshot' in row['path'] or 'foreign-acquirer76' in row['path'] else 'fiscal:rtc:replay'))
for index, (path, command) in enumerate(targets):
    digest = sha(ROOT / path)
    assert run('historical-' + str(index), [command, path])['replay_matches'] is True
    assert sha(ROOT / path) == digest
    prior.append({'path': path, 'sha256': digest, 'exact_replay': True})
print('PASS:53 predecessor/historical exact replays under saved-result-body integrity guard', flush=True)
assessment = run('production-assessment91-bound', ['fiscal:rtc:production-assess', '--bundle-manifest=' + bundle_path,
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation'])
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 59
old_assessment = read(ROOT / 'storage/app/phase4b-advance88-development-20261015/advance88-final-production-assess.log')
assert set(assessment['blockers']) == set(old_assessment['blockers']) and len(assessment['blockers']) == 119
save(E / 'advance91-cli-corpus-validation.json', {'status': 'ADVANCE91_NATIVE_CORPUS_VERIFIED_FULL_REGRESSION_PENDING',
    'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': bundle_path, 'bundle_sha256': sha(F / bundle_path),
    'cases': records, 'prior_compatibility': prior, 'native_decisions': 60, 'exact_replays': 60, 'prior_exact_replays': 53,
    'official_sources_valid': 59, 'production_blocker_count': 119, 'full_suite_complete': False,
    'prior_advance_debits_proven': False, 'actual_registration_consumed': False,
    'document_settlement_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:30 scenarios/60 native decisions and replays/53 historical;59 sources;119 unchanged blockers')
