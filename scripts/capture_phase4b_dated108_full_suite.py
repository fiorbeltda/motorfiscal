"""Exclusive full108 regression; terminal corpus required, logs on D, inputs frozen."""
from datetime import datetime, timezone
import re
import shutil
import subprocess
import time
from verify_phase4b_dated108_evidence import ROOT, E, C, F, D, read, sha, save, clean, verify, inputs

assert not F.exists(), 'Exclusive attempt; do not restart live regression'
assert shutil.disk_usage(ROOT).free > 256 * 1024 * 1024
corpus, bundle, runtime, old_inputs = verify()
top = subprocess.run([D, 'compose', 'top', 'app'], cwd=ROOT, capture_output=True, text=True, check=True).stdout
assert 'vendor/bin/pest' not in top, 'A suite is already live'
before = inputs(old_inputs, bundle)
assert shutil.disk_usage(ROOT).free > 256 * 1024 * 1024
F.mkdir()
started = datetime.now(timezone.utc).isoformat()
command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'vendor/bin/pest',
    '--colors=never', '--do-not-cache-result', '--cache-directory=/tmp/phase4b-dated108-pest-cache']
save(F / 'preflight.json', {'created_at': started, 'input_hashes': before, 'runtime_hashes': runtime,
    'corpus_sha256': sha(C / 'dated108-cli-validation.json'), 'new_tests': 671, 'expected_total_tests': 7200,
    'independent_upstream_captured104_controls': corpus['independent_upstream_captured104_controls'],
    'bundle': corpus['bundle'], 'bundle_sha256': corpus['bundle_sha256'], 'full_suite_complete': False, 'production_ready': False})
begin = time.monotonic()
with (F / 'full-suite.log').open('xb') as log:
    proc = subprocess.Popen(command, cwd=ROOT, stdout=log, stderr=subprocess.STDOUT)
    save(F / 'process-start.json', {'host_docker_pid': proc.pid, 'started_at': started, 'command': command})
    print('LIVE:full108;host Docker PID ' + str(proc.pid) + ';frozen inputs ' + str(len(before)), flush=True)
    code = proc.wait()
after = inputs(old_inputs, bundle)
changed = [p for p in before if before[p] != after.get(p)]
added = sorted(set(after) - set(before))
removed = sorted(set(before) - set(after))
counts = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', clean(F / 'full-suite.log'))
suite = {'exit_code': code, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(),
    'seconds': time.monotonic() - begin, 'log_sha256': sha(F / 'full-suite.log'),
    'tests': int(counts[1]) if counts else None, 'assertions': int(counts[2]) if counts else None,
    'input_files_changed': changed, 'input_files_added': added, 'input_files_removed': removed,
    'full_suite_complete': code == 0 and counts is not None and not changed and not added and not removed}
save(F / 'full-suite.json', suite)
print(str(suite), flush=True)
assert suite['full_suite_complete'] and suite['tests'] == 7200

def final(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
    record = {'command': command, 'started_at': datetime.now(timezone.utc).isoformat()}
    proc = subprocess.run(command, cwd=ROOT, capture_output=True)
    record.update(exit_code=proc.returncode, finished_at=datetime.now(timezone.utc).isoformat())
    for channel in ['stdout', 'stderr']:
        with (F / (label + '.' + channel)).open('xb') as stream:
            stream.write(getattr(proc, channel))
        record[channel + '_sha256'] = sha(F / (label + '.' + channel))
    save(F / (label + '.json'), record)
    assert proc.returncode == 0
    return proc.stdout.decode()

assert '[OK] No errors' in final('final-phpstan', ['vendor/bin/phpstan', 'analyse', '--no-progress'])
assert re.search(r'PASS.*572 files', final('final-pint', ['vendor/bin/pint', '--test']))
final('final-production-assess108', ['artisan', 'fiscal:rtc:production-assess', '--bundle-manifest=' + corpus['bundle'],
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation'])
assessment = read(F / 'final-production-assess108.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 76
assert set(assessment['blockers']) == set(read(C / 'production-assess108.stdout')['blockers']) and len(assessment['blockers']) == 124
assert inputs(old_inputs, bundle) == before
save(F / 'dated108-regression-validation.json', {'status': 'DATED108_LOCAL_INTEGRATION_REGRESSION_VERIFIED',
    'created_at': datetime.now(timezone.utc).isoformat(), 'full_suite': suite, 'full_suite_complete': True,
    'bundle': corpus['bundle'], 'bundle_sha256': corpus['bundle_sha256'], 'runtime_hashes': runtime,
    'unchanged_input_files': len(before), 'native_decisions': 116, 'exact_replays': 116, 'historical_exact_replays': 427,
    'independent_upstream_captured104_controls': corpus['independent_upstream_captured104_controls'],
    'official_sources_valid': 76, 'production_blocker_count': 124, 'document_obligation_complete': False,
    'fiscal_identifiers_qualified': False, 'government_evidence_authenticated': False,
    'xml_generated': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:full108 and final quality;independent acceptance required', flush=True)
