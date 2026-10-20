"""Verify candidate101 gates, freeze inputs, run one exclusive complete regression."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import shutil
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-integration-20261016')
C = E / 'cli'
F = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-full-suite-20261016')
OLD = Path(r'D:\ProjetoSaasExports\phase4b-sp98-full-suite-20261016')
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(name, value):
    with (F / name).open('x', encoding='utf-8') as stream: json.dump(value, stream, indent=2)
def clean(p): return re.sub(r'\x1b\[[0-9;]*m', '', p.read_text(encoding='utf-8', errors='replace'))

assert not F.exists(), 'Exclusive attempt; never restart a live regression'
assert shutil.disk_usage(ROOT).free > 256 * 1024 * 1024
corpus_path = C / 'catalog101-cli-validation.json'
corpus = read(corpus_path)
assert corpus['status'] == 'CATALOG101_NATIVE_CORPUS_VERIFIED_REGRESSION_PENDING'
assert corpus['native_decisions'] == corpus['exact_replays'] == 122
assert corpus['historical_exact_replays'] == 215
assert len(corpus['cases']) == 61 and len(corpus['historical_replays']) == 215
assert corpus['official_sources_valid'] == 66 and corpus['production_blocker_count'] == 122
for path in C.glob('*-execution.json'):
    gate = read(path)
    assert gate['exit_code'] == 0
    label = path.name.removesuffix('-execution.json')
    for channel in ['stdout', 'stderr']: assert sha(C / (label + '.' + channel)) == gate[channel + '_sha256']
for case in corpus['cases']:
    assert sha(ROOT / case['path']) == case['scenario_sha256']
    assert sha(ROOT / case['original_path']) == case['original_sha256']
    current = read(ROOT / case['path'])
    original = read(ROOT / case['original_path'])
    assert current['facts'] == original['facts']
    assert datetime.fromisoformat(current['knowledge_at']) >= datetime.fromisoformat(original['knowledge_at'])
    for kind in ['rtc', 'integrated']:
        label = case['name'] + '-' + kind
        assert sha(C / case[kind + '_snapshot']) == case[kind + '_snapshot_sha256']
        assert read(C / case[kind + '_snapshot'])['result'] == read(C / (label + '.stdout'))
        assert read(C / (label + '-replay.stdout'))['replay_matches'] is True
for prior in corpus['historical_replays']: assert sha(Path(prior['path'])) == prior['sha256'] and prior['exact_replay'] is True
for label in ['directed-v5', 'phpstan-v2', 'pint-v2']:
    gate = read(E / (label + '.json'))
    assert gate['exit_code'] == 0 and gate['scope_input_hashes_at_start'] == gate['scope_input_hashes_at_end']
    for channel in ['stdout', 'stderr']: assert sha(E / (label + '.' + channel)) == gate[channel + '_sha256']
    for path, digest in gate['scope_input_hashes_at_start'].items(): assert sha(ROOT / path) == digest, path
assert '3186 passed (40482 assertions)' in clean(E / 'directed-v5.stdout')
assert '[OK] No errors' in clean(E / 'phpstan-v2.stdout')
assert re.search(r'PASS.*558 files', clean(E / 'pint-v2.stdout'))
bundle_path = ROOT / 'resources/fiscal' / corpus['bundle']
bundle = read(bundle_path)
assert sha(bundle_path) == corpus['bundle_sha256']
for source in bundle['official_sources']: assert sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256']
archive = read(E / 'before-integration-archive.json')
for path, digest in archive['archived'].items(): assert sha(E / 'before-integration' / path) == digest
old_inputs = read(OLD / 'preflight.json')['input_hashes']
changed_old = [p for p, digest in old_inputs.items() if sha(Path(p)) != digest]
authorized = {str(ROOT / p) for p in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php']}
assert set(changed_old) == authorized, changed_old
def inputs():
    paths = {Path(p) for p in old_inputs}
    for directory, extensions in [('app', {'.php'}), ('tests', {'.php', '.json', '.xml', '.xsd'}),
        ('resources/fiscal', {'.json', '.xml', '.xsd'}), ('scripts', {'.php', '.py', '.ps1'})]:
        paths.update(p for p in (ROOT / directory).rglob('*') if p.is_file() and p.suffix in extensions)
    paths.update(ROOT / 'resources/fiscal/sources/official' / s['file'] for s in bundle['official_sources'])
    paths.update(p for p in E.rglob('*') if p.is_file())
    return {str(p): sha(p) for p in sorted(paths)}
F.mkdir()
before = inputs()
runtime = read(ROOT / 'storage/app/phase4b-sp98-development-20261016/sp98-development-validation.json')['runtime_hashes']
for path in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/ConsumptionReform/CClassTribReference100.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php']: runtime[path] = sha(ROOT / path)
assert len(runtime) == 78
started = datetime.now(timezone.utc).isoformat()
command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'vendor/bin/pest', '--colors=never',
    '--do-not-cache-result', '--cache-directory=/tmp/phase4b-catalog101-pest-cache']
save('preflight.json', {'created_at': started, 'input_hashes': before, 'runtime_hashes': runtime, 'corpus_sha256': sha(corpus_path),
    'authorized_predecessor_changes': changed_old, 'new_tests': 3123, 'expected_total_tests': 6296,
    'bundle': corpus['bundle'], 'bundle_sha256': corpus['bundle_sha256'], 'full_suite_complete': False, 'production_ready': False})
begin = time.monotonic()
with (F / 'full-suite.log').open('xb') as log:
    proc = subprocess.Popen(command, cwd=ROOT, stdout=log, stderr=subprocess.STDOUT)
    save('process-start.json', {'host_docker_pid': proc.pid, 'started_at': started, 'command': command})
    print('LIVE:full101 regression;host Docker PID ' + str(proc.pid) + ';frozen inputs ' + str(len(before)), flush=True)
    code = proc.wait()
after = inputs()
changed = [p for p in before if before[p] != after.get(p)]
added = sorted(set(after) - set(before)); removed = sorted(set(before) - set(after))
counts = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', clean(F / 'full-suite.log'))
suite = {'exit_code': code, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(),
    'seconds': time.monotonic() - begin, 'log_sha256': sha(F / 'full-suite.log'), 'tests': int(counts[1]) if counts else None,
    'assertions': int(counts[2]) if counts else None, 'input_files_changed': changed, 'input_files_added': added, 'input_files_removed': removed,
    'full_suite_complete': code == 0 and counts is not None and not changed and not added and not removed}
save('full-suite.json', suite)
print(json.dumps(suite), flush=True)
assert suite['full_suite_complete'] and suite['tests'] == 6296
def final(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
    record = {'command': command, 'started_at': datetime.now(timezone.utc).isoformat()}
    result = subprocess.run(command, cwd=ROOT, capture_output=True)
    record.update(exit_code=result.returncode, finished_at=datetime.now(timezone.utc).isoformat())
    for channel in ['stdout', 'stderr']:
        with (F / (label + '.' + channel)).open('xb') as stream: stream.write(getattr(result, channel))
        record[channel + '_sha256'] = sha(F / (label + '.' + channel))
    save(label + '.json', record)
    assert result.returncode == 0, label
    return result.stdout.decode()
assert '[OK] No errors' in final('final-phpstan', ['vendor/bin/phpstan', 'analyse', '--no-progress'])
assert re.search(r'PASS.*558 files', final('final-pint', ['vendor/bin/pint', '--test']))
assessment = json.loads(final('final-production-assess101', ['artisan', 'fiscal:rtc:production-assess', '--bundle-manifest=' + corpus['bundle'],
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation']))
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 66
assert set(assessment['blockers']) == set(read(C / 'production-assess101.stdout')['blockers']) and len(assessment['blockers']) == 122
assert inputs() == before
save('catalog101-regression-validation.json', {'status': 'CATALOG101_REFERENCE_INTEGRATION_REGRESSION_VERIFIED',
    'created_at': datetime.now(timezone.utc).isoformat(), 'full_suite': suite, 'full_suite_complete': True,
    'bundle': corpus['bundle'], 'bundle_sha256': corpus['bundle_sha256'], 'runtime_hashes': runtime,
    'unchanged_input_files': len(before), 'native_decisions': 122, 'exact_replays': 122, 'historical_exact_replays': 215,
    'official_sources_valid': 66, 'production_blocker_count': 122, 'document_obligation_complete': False,
    'tax_treatment_selected_by_reference': False, 'document_indicator_emitted': False,
    'xml_generated': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:full101 regression/final quality/immutable inputs;independent acceptance still required', flush=True)
