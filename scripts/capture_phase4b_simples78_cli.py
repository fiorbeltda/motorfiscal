"""Exercise H1 election through RTC and integrated ISS runners, preserving future-rate refusal."""
from pathlib import Path
from copy import deepcopy
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
P = ROOT / 'storage/app/phase4b-simples78-integration-preparation-20261015'
E = ROOT / 'storage/app/phase4b-simples78-cli-20261015'
M = 'bundles/consumption-phase4b-2026.10.15-78-draft1.json'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def save(name, value):
    with (E / name).open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)


def relative(path):
    return path.relative_to(ROOT).as_posix()


def run(label, args, expected_exit=0):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    started = datetime.now(timezone.utc).isoformat()
    result = subprocess.run(command, cwd=ROOT, capture_output=True)
    for channel in ['stdout', 'stderr']:
        with (E / (label + '.' + channel)).open('xb') as stream:
            stream.write(getattr(result, channel))
    save(label + '-execution.json', {'command': command, 'exit_code': result.returncode,
        'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(),
        'stdout_sha256': sha(E / (label + '.stdout')), 'stderr_sha256': sha(E / (label + '.stderr'))})
    assert result.returncode == expected_exit, label + ':' + result.stdout.decode('utf-8', errors='replace') + result.stderr.decode('utf-8', errors='replace')
    return json.loads(result.stdout)


E.mkdir(exist_ok=False)
bundle = read(F / M)
assert sha(F / M) == read(P / 'simples78-bundle-installation.json')['bundle_sha256']
source = read(F / 'scenarios/consumption-phase4b-2026.09.28-1/simples-regular-h1-2027-confirmed.json')
component = {case['case']: case for case in read(P / 'component-cases.json')}
names = ['standard-last-filing-day', 'first-effective-day', 'last-effective-day',
         'registered-last-standard-day', 'registration-2026-10-31', 'registration-2026-12-31',
         'outside-filing-2026-10-31', 'registration-option-not-same-day',
         'missing-provider.cnpj_registered_on', 'activity-date-not-registration-date',
         'status-CANCELLED', 'expired-status-after-cancellation', 'mei-excluded',
         'missing-rtc.simples_option_route_evidence_id']
records = []
for name in [*names, 'second-semester-unchanged']:
    scenario = deepcopy(source)
    scenario.update(scenario_id='SYNTHETIC-FORECAST-SIMPLES78-' + name,
                    rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'])
    if name == 'second-semester-unchanged':
        scenario.update(competence_date='2027-10-10', knowledge_at='2027-10-10T10:00:00-03:00')
        scenario['facts']['rtc.simples_option_filed_on'] = '2027-03-20'
        expected_reason, expected_status, qualified = 'RTC_RATE_NOT_PROVEN:CBS', 'ACTION_REQUIRED', False
    else:
        case = component[name]
        for key in set(case['facts']) | {'provider.cnpj_registered_on'}:
            scenario['facts'].pop(key, None)
        scenario['facts'].update(case['facts'])
        scenario.update(competence_date=case['on'], knowledge_at=case['known_at'])
        qualified = case['expected_status'] == 'LOCAL_ELECTION_QUALIFIED'
        expected_reason = 'RTC_RATE_NOT_PROVEN:CBS' if qualified else case['expected_reason']
        expected_status = 'ACTION_REQUIRED' if qualified else case['expected_status']
        if name == 'mei-excluded':
            scenario['facts']['provider.tax_regime'] = 'MEI'
    scenario['facts']['rtc.operation_date'] = scenario['competence_date']
    scenario['facts']['provider.simples_iss_fixed_monthly'] = False
    save(name + '-scenario.json', scenario)
    path = relative(E / (name + '-scenario.json'))
    rtc_snapshot, full_snapshot = E / (name + '-rtc-snapshot.json'), E / (name + '-integrated-snapshot.json')
    expected_exit = 1 if expected_status == 'INVALID' else 0
    rtc = run(name + '-rtc', ['fiscal:rtc:run', path, '--bundle-manifest=' + M,
        '--snapshot=' + relative(rtc_snapshot)], expected_exit)
    assert rtc['status'] == expected_status and expected_reason in rtc['reason_codes'], (name, rtc)
    trace = {row['stage']: row for row in rtc['trace']}
    if qualified:
        assert trace['SIMPLES_REGULAR_ELECTION_194']['status'] == 'LOCAL_ELECTION_QUALIFIED'
        assert trace['SIMPLES_REGULAR_ELECTION_194']['government_receipt_authenticated'] is False
        assert trace['SIMPLES_REGULAR_OPTION']['scope'] == '2027_FIRST_SEMESTER'
    elif name == 'second-semester-unchanged':
        assert 'SIMPLES_REGULAR_ELECTION_194' not in trace
        assert trace['SIMPLES_REGULAR_OPTION']['scope'] == '2027_SECOND_SEMESTER'
    assert run(name + '-rtc-replay', ['fiscal:rtc:replay', relative(rtc_snapshot)])['replay_matches']
    integrated = run(name + '-integrated', ['fiscal:scenario:run', path, '--bundle-manifest=' + M,
        '--snapshot=' + relative(full_snapshot)], expected_exit)
    assert integrated['status'] == expected_status and expected_reason in integrated['reason_codes'], (name, integrated)
    events = {event['type']: event['payload'] for event in integrated['trace']}
    assert events['CONSUMPTION_REFORM']['reason_codes'] == rtc['reason_codes']
    if qualified:
        assert events['ISS_CALCULATION']['tax_amount'] == '20.00'
        assert events['CONSUMPTION_REFORM']['trace'] == rtc['trace']
    replay = run(name + '-integrated-replay', ['fiscal:decision:replay', relative(full_snapshot)])
    assert replay['replay_matches'] and replay['result'] == integrated
    records.append({'case': name, 'scenario': path, 'scenario_sha256': sha(E / (name + '-scenario.json')),
        'expected_status': expected_status, 'expected_reason': expected_reason, 'election_locally_qualified': qualified,
        'rtc_snapshot': relative(rtc_snapshot), 'rtc_snapshot_sha256': sha(rtc_snapshot),
        'integrated_snapshot': relative(full_snapshot), 'integrated_snapshot_sha256': sha(full_snapshot),
        'rtc_exact_replay': True, 'integrated_exact_replay': True,
        'future_cbs_rate_not_invented': True, 'production_ready': False})
    print('PASS:' + name + ';RTC+ISS runners and both exact replays;' + expected_reason, flush=True)
prior = []
for label, path, command in [
    ('prior-foreign76', 'storage/app/phase4b-foreign-acquirer76-cli-20261015/nif-snapshot.json', 'fiscal:decision:replay'),
    ('prior-export73', 'storage/app/phase4b-partial-export-fx73-preparation-20261014/usd-direct-export-snapshot.json', 'fiscal:rtc:replay'),
]:
    digest = sha(ROOT / path)
    assert run(label, [command, path])['replay_matches']
    assert sha(ROOT / path) == digest
    prior.append({'path': path, 'sha256': digest, 'exact_replay': True})
assessment = run('production-assessment', ['fiscal:rtc:production-assess', '--bundle-manifest=' + M,
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation'])
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 55
save('simples78-cli-corpus-validation.json', {
    'status': 'SIMPLES78_RTC_AND_ISS_INTEGRATION_CORPUS_VERIFIED_FULL_ACCEPTANCE_PENDING',
    'bundle': M, 'bundle_sha256': sha(F / M), 'cases': records, 'prior_compatibility': prior,
    'official_sources_valid': 55, 'production_blocker_count': len(assessment['blockers']),
    'full_suite_complete': False, 'future_cbs_rate_qualified': False,
    'government_receipt_authenticated': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:15 scenarios/30 native decisions/30 exact replays plus2 prior replays;CBS parameter and production gates retained')
