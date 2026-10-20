"""Full regression of installed documentary-memory utility, without changing engine91 or its acceptance."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import shutil
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
P = Path(r'D:\ProjetoSaasExports\phase4b-document94-base-reconciliation-20261016')
E = Path(r'D:\ProjetoSaasExports\phase4b-document94-component-integration-20261016')
PRIOR = Path(r'D:\ProjetoSaasExports\phase4b-advance91-full-suite-20261015\preflight.json')
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def save(name, data):
    with (E / name).open('x', encoding='utf-8') as stream:
        json.dump(data, stream, ensure_ascii=False, indent=2)

assert not E.exists(), 'Exclusive capture; never overwrite a prior regression'
assert shutil.disk_usage(ROOT).free > 256 * 1024 * 1024
assert read(P / 'document94-preparation-validation.json')['frozen91_input_files_unchanged'] == 5353
prior = read(PRIOR)['input_hashes']
new_files = ['app/Modules/Fiscal/NFSe/DocumentBaseReconciliation.php',
             'tests/Fiscal/Unit/DocumentBaseReconciliation94Test.php', 'tests/Fixtures/document-base94-cases.json']
for p, digest in prior.items():
    assert sha(ROOT / p) == digest, p
for label in ['integrated94-directed-v1', 'integrated94-phpstan-v1', 'integrated94-pint-v1']:
    record = read(P / (label + '.json'))
    assert record['exit_code'] == 0
    for channel in ['stdout', 'stderr']:
        assert sha(P / (label + '.' + channel)) == record[channel + '_sha256']
assert '58 passed (686 assertions)' in (P / 'integrated94-directed-v1.stdout').read_text()
assert read(ROOT / new_files[2]) == read(P / 'cases.json')
original = (P / 'DocumentBaseReconciliation94-formatted.php').read_text().replace('namespace Phase4BStaging94;', 'namespace App\\Modules\\Fiscal\\NFSe;').replace('final readonly class DocumentBaseReconciliation94', 'final readonly class DocumentBaseReconciliation')
assert (ROOT / new_files[0]).read_text() == original
E.mkdir()

def inputs():
    paths = {ROOT / p for p in prior}
    for directory, extensions in [('app', {'.php'}), ('tests', {'.php', '.json', '.xml', '.xsd'}), ('resources/fiscal', {'.json', '.xml', '.xsd'})]:
        paths.update(p for p in (ROOT / directory).rglob('*') if p.is_file() and p.suffix in extensions)
    paths.update([P / 'document94-preparation-validation.json', P / 'cases.json', P / 'DocumentBaseReconciliation94-formatted.php'])
    return {str(p): sha(p) for p in sorted(paths)}

before = inputs()
started = datetime.now(timezone.utc).isoformat()
command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'vendor/bin/pest',
           '--colors=never', '--do-not-cache-result', '--cache-directory=/tmp/phase4b-document94-pest-cache']
save('preflight.json', {'started_at': started, 'command': command, 'input_hashes': before,
                      'previous_frozen91_inputs': len(prior), 'new_files': {p: sha(ROOT / p) for p in new_files},
                      'preparation_sha256': sha(P / 'document94-preparation-validation.json'),
                      'c_free_bytes': shutil.disk_usage(ROOT).free, 'production_ready': False})
begin = time.monotonic()
with (E / 'full-suite.log').open('xb') as stream:
    process = subprocess.Popen(command, cwd=ROOT, stdout=stream, stderr=subprocess.STDOUT)
    save('process-start.json', {'host_docker_pid': process.pid, 'started_at': started, 'command': command})
    print('LIVE:document94 full regression; host Docker PID ' + str(process.pid), flush=True)
    code = process.wait()
after = inputs()
changed = [p for p in before if before[p] != after.get(p)]
added = sorted(set(after) - set(before))
removed = sorted(set(before) - set(after))
text = (E / 'full-suite.log').read_text(encoding='utf-8', errors='replace')
counts = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', text)
suite = {'exit_code': code, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(),
         'seconds': time.monotonic() - begin, 'log_sha256': sha(E / 'full-suite.log'),
         'input_files_changed': changed, 'input_files_added': added, 'input_files_removed': removed,
         'tests': int(counts[1]) if counts else None, 'assertions': int(counts[2]) if counts else None,
         'full_suite_complete': code == 0 and counts is not None and not changed and not added and not removed}
save('full-suite.json', suite)
print(json.dumps(suite), flush=True)
assert suite['full_suite_complete'] and suite['tests'] == 2793

def quality(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
    result = subprocess.run(command, cwd=ROOT, capture_output=True)
    record = {'command': command, 'exit_code': result.returncode, 'finished_at': datetime.now(timezone.utc).isoformat()}
    for channel in ['stdout', 'stderr']:
        raw = getattr(result, channel)
        with (E / (label + '.' + channel)).open('xb') as stream:
            stream.write(raw)
        record[channel + '_sha256'] = hashlib.sha256(raw).hexdigest()
    save(label + '.json', record)
    assert result.returncode == 0, label
    return result.stdout.decode()

assert '[OK] No errors' in quality('final-phpstan', ['vendor/bin/phpstan', 'analyse', '--no-progress'])
style = quality('final-pint', ['vendor/bin/pint', '--test'])
assert 'PASS' in style and '544 files' in style
bundle = read(ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.15-91-draft1.json')
assessment = json.loads(quality('final-production-assess91', ['artisan', 'fiscal:rtc:production-assess',
    '--bundle-manifest=bundles/consumption-phase4b-2026.10.15-91-draft1.json',
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation']))
prior_assessment = read(ROOT / 'storage/app/phase4b-advance91-cli-20261015/production-assessment91-bound.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert set(assessment['blockers']) == set(prior_assessment['blockers']) and len(assessment['blockers']) == 119
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 59
assert inputs() == before, 'Inputs changed during final quality checks'
report = {'status': 'DOCUMENT94_UTILITY_INSTALLED_AND_FULL_REGRESSION_VERIFIED',
          'created_at': datetime.now(timezone.utc).isoformat(), 'scope': 'DECLARED_BASE_MEMORY_COMPARISON_UTILITY_NOT_ENGINE_DOCUMENT_OBLIGATION',
          'full_suite': suite, 'full_suite_complete': True, 'inputs_unchanged': len(before),
          'old91_inputs_unchanged': len(prior), 'new_files': {p: sha(ROOT / p) for p in new_files},
          'runtime_component_installed': True, 'engine91_changed': False, 'engine_opt_in_implemented': False,
          'paired_export_projection_complete': False, 'document_obligation_complete': False,
          'source_effective_date_proven': False, 'xml_schema_validated': False,
          'phase4b_complete': False, 'production_ready': False,
          'evidence_hashes': {p.name: sha(p) for p in E.iterdir() if p.is_file()}}
save('document94-component-integration-validation.json', report)
print(json.dumps(report), flush=True)
