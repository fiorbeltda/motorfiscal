"""Accept91 local binding/reconciliation only after terminal, unchanged full regression."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-advance91-full-suite-20261015')
C = ROOT / 'storage/app/phase4b-advance91-cli-20261015'
A = ROOT / 'storage/app/phase4b-advance91-development-20261015'
F = ROOT / 'resources/fiscal'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)

assert (E / 'full-suite.json').exists(), 'Regression not terminal; do not start final gates'
assert not A.exists() and not (E / 'advance91-development-validation.json').exists()
proof_path = C / 'advance91-integration-preflight.json'
proof = read(proof_path); preflight = read(E / 'preflight.json'); suite = read(E / 'full-suite.json')
assert sha(proof_path) == preflight['directed_preflight_sha256']
assert suite['exit_code'] == 0 and suite['full_suite_complete'] is True and suite['tests'] == 2735
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
assert sha(E / 'full-suite.log') == suite['log_sha256']
native = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', (E / 'full-suite.log').read_text(encoding='utf-8'))
assert native and int(native[1]) == 2735 and int(native[2]) == suite['assertions']

def unchanged():
    current = set()
    for directory, extensions in [('app', {'.php'}), ('tests', {'.php'}), ('resources/fiscal', {'.json', '.xml', '.xsd'})]:
        current.update(p.relative_to(ROOT).as_posix() for p in (ROOT / directory).rglob('*') if p.is_file() and p.suffix in extensions)
    current.update(['phpunit.xml', 'composer.lock', 'compose.yaml', *proof['evidence_input_hashes']])
    assert current == set(preflight['input_hashes']), 'Regression input inventory changed'
    for path, digest in preflight['input_hashes'].items(): assert sha(ROOT / path) == digest, path
    for path, digest in proof['runtime_hashes'].items(): assert sha(ROOT / path) == digest, path
    return len(current)
count = unchanged()
corpus = read(C / 'advance91-cli-corpus-validation.json')
assert sha(C / 'advance91-cli-corpus-validation.json') == proof['corpus_sha256']
assert len(corpus['cases']) == 30 and len(corpus['prior_compatibility']) == 53
assert corpus['exact_replays'] == corpus['native_decisions'] == 60
bundle = read(F / corpus['bundle'])
assert sha(F / corpus['bundle']) == corpus['bundle_sha256'] == proof['bundle_sha256']
assert len(bundle['official_sources']) == 59
for source in bundle['official_sources']: assert sha(F / 'sources/official' / source['file']) == source['sha256']
for case in corpus['cases']:
    assert sha(ROOT / case['path']) == case['scenario_sha256']
    for kind in ['rtc', 'integrated']:
        label = case['case'] + '-' + kind; snapshot = ROOT / case[kind + '_snapshot']
        assert sha(snapshot) == case[kind + '_snapshot_sha256']
        assert read(snapshot)['result'] == read(C / (label + '.stdout'))
        assert read(C / (label + '-replay.stdout'))['replay_matches'] is True
for index, prior in enumerate(corpus['prior_compatibility']):
    assert sha(ROOT / prior['path']) == prior['sha256']
    assert read(C / ('historical-' + str(index) + '.stdout'))['replay_matches'] is True

def run(label, args):
    log = E / (label + '.log'); record = E / (label + '.json')
    assert not log.exists() and not record.exists(), 'Preserve previous attempt'
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
    started = datetime.now(timezone.utc).isoformat()
    with log.open('xb') as stream: result = subprocess.run(command, cwd=ROOT, stdout=stream, stderr=subprocess.STDOUT)
    save(record, {'command': command, 'exit_code': result.returncode, 'started_at': started,
        'finished_at': datetime.now(timezone.utc).isoformat(), 'log_sha256': sha(log)})
    assert result.returncode == 0, label
    print(label + ':exit0', flush=True)
    return re.sub(r'\x1b\[[0-9;]*m', '', log.read_text(encoding='utf-8'))

assert '[OK] No errors' in run('advance91-final-phpstan-all', ['vendor/bin/phpstan', 'analyse', '--no-progress'])
style = run('advance91-final-pint-all', ['vendor/bin/pint', '--test'])
assert re.search(r'PASS.*542 files', style) and 'FAIL' not in style
assessment = json.loads(run('advance91-final-production-assess', ['artisan', 'fiscal:rtc:production-assess',
    '--bundle-manifest=' + corpus['bundle'], '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation']))
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 59
previous = read(ROOT / 'storage/app/phase4b-advance88-cli-20261015/production-assessment88-bound.stdout')
assert set(assessment['blockers']) == set(previous['blockers']) and len(assessment['blockers']) == 119
assert unchanged() == count
report = {'status': 'ADVANCE91_CAPTURED_RECORD_AND_FINAL_RECONCILIATION_DEVELOPMENT_VERIFIED',
    'scope': 'LOCAL_PRIOR_RECORD_BINDING_AND_NOMINAL_FINAL_RECONCILIATION_NOT_LEGAL_POSTING_OR_ATOMIC_CONSUMPTION',
    'created_at': datetime.now(timezone.utc).isoformat(), 'full_suite': suite, 'full_suite_complete': True,
    'runtime_hashes': proof['runtime_hashes'], 'evidence_input_hashes': proof['evidence_input_hashes'],
    'unchanged_input_file_count': count, 'directed_preflight_sha256': sha(proof_path),
    'corpus_sha256': sha(C / 'advance91-cli-corpus-validation.json'), 'bundle_sha256': sha(F / corpus['bundle']),
    'official_sources_valid': 59, 'production_blocker_count': 119,
    'final_quality': {label: read(E / (label + '.json')) for label in ['advance91-final-phpstan-all', 'advance91-final-pint-all', 'advance91-final-production-assess']},
    'scenarios': 30, 'native_decisions': 60, 'exact_replays': 60, 'prior_exact_replays': 53,
    'registration_proven': False, 'prior_advance_debits_proven': False, 'actual_registration_consumed': False,
    'document_settlement_complete': False, 'credit_appropriation_authorized': False,
    'phase4b_complete': False, 'production_ready': False}
save(E / 'advance91-development-validation.json', report)
A.mkdir(exist_ok=False)
files = ['advance91-development-validation.json', 'full-suite.log', 'full-suite.json', 'preflight.json', 'process-start.json']
files += [label + suffix for label in report['final_quality'] for suffix in ['.log', '.json']]
receipt = {}
for name in files:
    with (A / name).open('xb') as stream: stream.write((E / name).read_bytes())
    assert sha(A / name) == sha(E / name)
    receipt[name] = sha(A / name)
save(A / 'evidence-copy-receipt.json', receipt)
print('PASS:91 accepted only within declared local scope; original Phase4B and production incomplete')
