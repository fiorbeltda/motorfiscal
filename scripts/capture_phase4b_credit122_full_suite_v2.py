"""Exclusive current122 regression; does not assert complete Phase4B acceptance."""
from pathlib import Path
from datetime import datetime, timezone
import json
import os
import re
import shutil
import subprocess
import time
from phase4b_credit122_capture_support import R, E, D, read, sha, save, core_inventory, canonical, compare_compatibility_result

F = E.parent / 'phase4b-credit122-full-suite-20261021-v2'
PREP = E / 'credit122-expanded-preparation-validation.json'
TESTLIST = E / 'price122-full-test-list-v1/stdout.log'
BASELINE = E / 'production-assess122-directed-v1/stdout.log'


def inventory():
    result = core_inventory()
    paths = [Path(__file__), R/'scripts/phase4b_credit122_capture_support.py',
             R/'scripts/capture_phase4b_credit122_expanded_cli.py', PREP, TESTLIST, BASELINE,
             E/'credit122-compatibility119-source-index-v1.json']
    paths.extend(R/'scripts'/name for name in [
        'capture_phase4b_credit122_historical_v2.py',
        'build_phase4b_credit122_price_scenarios.php',
        'build_phase4b_credit122_price_upstream.php',
        'audit_phase4b_credit122_price_preparation.py'])
    for folder in ['credit-cli-v2', 'question-fix-v2']:
        paths.extend(p for p in (E/folder).rglob('*') if p.is_file())
    paths.extend(p for p in (E/'expanded-cli-v1').glob('compat-*') if p.is_file())
    for path in paths:
        result[str(path)] = sha(path)
    return dict(sorted(result.items()))


def clean(path):
    return re.sub(r'\x1b\[[0-9;]*m', '', path.read_text(encoding='utf-8'))


assert not F.exists(), 'Exclusive regression; never restart a live attempt'
prep = read(PREP)
assert prep['directed_tests'] == 447 and prep['directed_assertions'] == 9942
assert len(prep['runtime_hashes']) == 94
for path, digest in prep['runtime_hashes'].items():
    assert sha(R/path) == digest, path
assert sha(TESTLIST) == '907e4531c3885c05c6a1056582fbfc4886c940625beae1658d03a272cdda1f68'
assert len(re.findall(r'^\s*-\s+\S', clean(TESTLIST), re.M)) == 9061
assert read(E/'credit-cli-v2/credit122-cli-validation.json')['native_decisions'] == 180
assert (E/'expanded-cli-v1/000-rtc-execution.json').exists(), 'Wait for all61 compatibility cases and first price execution'
compat_cases = read(E/'credit122-compatibility119-source-index-v1.json')['cases']
assert len(compat_cases) == 61
for index, case in enumerate(compat_cases):
    scenario = read(E/'expanded-cli-v1'/f'compat-{index:03d}-scenario.json')
    for runner in ['rtc', 'integrated']:
        label = f'compat-{index:03d}-{runner}'
        current = read(E/'expanded-cli-v1'/(label+'-snapshot.json'))
        source = case['runners'][runner]
        assert sha(source['snapshot']) == source['snapshot_sha256']
        original = read(Path(source['snapshot']))
        assert canonical(current['input']) == canonical(scenario)
        compare_compatibility_result(original['result'], current['result'], runner, old_input=original['input'], new_input=scenario)
        assert read(E/'expanded-cli-v1'/(label+'-replay-execution.json'))['exit_code'] == 0
        replay = read(E/'expanded-cli-v1'/(label+'-replay.stdout'))
        assert replay['replay_matches'] is True
        if runner == 'integrated':
            assert canonical(replay['result']) == canonical(current['result'])
top = subprocess.run([D, 'compose', 'top', 'app'], cwd=R, capture_output=True, text=True, check=True).stdout
assert 'vendor/bin/pest' not in top, 'Another Pest suite is live'
assert shutil.disk_usage(R).free > 512*1024*1024
F.mkdir()
start = datetime.now(timezone.utc).isoformat()
save(F/'process-start.json', {'started_at': start, 'host_python_pid': os.getpid(), 'stage': 'preparing', 'collector_sha256': sha(Path(__file__))})
before = inventory()
save(F/'preflight.json', {'created_at': start, 'input_hashes': before, 'runtime_hashes': prep['runtime_hashes'],
                         'expected_tests': 9061, 'minimum_expected_assertions': 143868,
                         'directed_tests': 447, 'directed_assertions': 9942,
                         'bundle': prep['bundle'], 'bundle_sha256': prep['bundle_sha256'],
                         'full_suite_complete': False, 'phase4b_complete': False, 'production_ready': False})
command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'vendor/bin/pest',
           '--colors=never', '--do-not-cache-result', '--cache-directory=/tmp/phase4b-credit122-pest-cache']
begin = time.monotonic()
with (F/'full-suite.log').open('xb') as log:
    process = subprocess.Popen(command, cwd=R, stdout=log, stderr=subprocess.STDOUT)
    save(F/'pest-process-start.json', {'host_docker_pid': process.pid, 'started_at': datetime.now(timezone.utc).isoformat(), 'command': command})
    print('LIVE:full122;9061 tests;host Docker PID:'+str(process.pid), flush=True)
    code = process.wait()
after = inventory()
match = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', clean(F/'full-suite.log'))
changed = [p for p in before if before[p] != after.get(p)]
added, removed = sorted(set(after)-set(before)), sorted(set(before)-set(after))
tests, assertions = (int(match[1]), int(match[2])) if match else (None, None)
suite = {'exit_code': code, 'started_at': start, 'finished_at': datetime.now(timezone.utc).isoformat(),
         'seconds': time.monotonic()-begin, 'tests': tests, 'assertions': assertions,
         'log_sha256': sha(F/'full-suite.log'), 'input_files_changed': changed,
         'input_files_added': added, 'input_files_removed': removed,
         'full_suite_complete': code == 0 and tests == 9061 and assertions is not None and assertions >= 143868 and not changed and not added and not removed}
save(F/'full-suite.json', suite)
assert suite['full_suite_complete'], suite


def final(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
    receipt = {'command': command, 'started_at': datetime.now(timezone.utc).isoformat()}
    result = subprocess.run(command, cwd=R, capture_output=True)
    receipt.update(exit_code=result.returncode, finished_at=datetime.now(timezone.utc).isoformat())
    for stream in ['stdout', 'stderr']:
        with (F/(label+'.'+stream)).open('xb') as output:
            output.write(getattr(result, stream))
        receipt[stream+'_sha256'] = sha(F/(label+'.'+stream))
    save(F/(label+'.json'), receipt)
    assert result.returncode == 0, label
    return clean(F/(label+'.stdout'))


assert '[OK] No errors' in final('final-phpstan', ['vendor/bin/phpstan', 'analyse', '--no-progress'])
assert re.search(r'PASS.*606 files', final('final-pint', ['vendor/bin/pint', '--test']))
bundle = read(R/'resources/fiscal'/prep['bundle'])
assessment = json.loads(final('final-production-assess122', ['artisan', 'fiscal:rtc:production-assess',
    '--bundle-manifest='+prep['bundle'], '--controls=resources/fiscal/'+bundle['production_controls']['path'], '--target=local-validation']))
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 98
assert len(assessment['blockers']) == 128 and set(assessment['blockers']) == set(read(BASELINE)['blockers'])
assert inventory() == before
save(F/'credit122-regression-validation.json', {'created_at': datetime.now(timezone.utc).isoformat(),
    'status': 'CREDIT122_FULL_REGRESSION_AND_QUALITY_VERIFIED', 'full_suite': suite,
    'runtime_hashes': prep['runtime_hashes'], 'bundle': prep['bundle'], 'bundle_sha256': prep['bundle_sha256'],
    'frozen_inputs': len(before), 'input_inventory_unchanged': True, 'official_sources_valid': 98,
    'production_blocker_count': 128, 'native_and_historical_acceptance_separate': True,
    'full_suite_complete': True, 'development_acceptance_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:full122 regression and final quality;combined development audit still required', flush=True)
