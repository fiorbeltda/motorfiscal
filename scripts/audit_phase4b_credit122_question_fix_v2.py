"""Directed acceptance of the question repair, not full122 or Phase4B acceptance."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re

repo = Path(__file__).resolve().parents[1]
base = Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021')
root = base / 'question-fix-v2'


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


checks = []
for label, markers in [
    ('question122-directed-v2', ['197 passed (698 assertions)']),
    ('question122-phpstan-v1', ['[OK] No errors']),
    ('question122-pint-test-v1', ['605 files']),
]:
    directory = base / label
    receipt = read(directory / 'execution.json')
    assert receipt['exit_code'] == 0, label
    for channel in ['stdout', 'stderr']:
        assert sha(directory / (channel + '.log')) == receipt[channel + '_sha256']
    output = (directory / 'stdout.log').read_text(encoding='utf-8')
    output = re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', output)
    assert all(marker in output for marker in markers), label
    checks.append({'label': label, 'receipt_sha256': sha(directory / 'execution.json')})
before = read(root / 'before-question-fix-validation.json')
previous = read(base / 'historical-replay-v1/preflight.json')['input_hashes']
scopes = [repo / name for name in ['app', 'tests', 'resources/fiscal']]
paths = {path for scope in scopes for path in scope.rglob('*') if path.is_file()}
old = {Path(path): digest for path, digest in previous.items()
       if any(Path(path).is_relative_to(scope) for scope in scopes)}
assert paths == set(old), 'Code/test/resource membership changed unexpectedly'
changed = {str(path.relative_to(repo)).replace('\\', '/') for path in paths if sha(path) != old[path]}
allowed = {row['source'] for row in before['archives']}
assert changed == allowed, sorted(changed)
for row in before['archives']:
    assert sha(Path(row['archive'])) == row['sha256'] == old[repo / row['source']]
runtime = {relative: sha(repo / relative) for relative in before['runtime_hashes']}
runtime_changed = {relative for relative, digest in runtime.items() if digest != before['runtime_hashes'][relative]}
assert runtime_changed == {
    'app/Modules/Fiscal/ConsumptionReform/CreditInput122.php',
    'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
}
for witness in read(base / 'question-gap-v1/question-gap-observation.json')['witnesses']:
    assert sha(Path(witness['snapshot'])) == witness['snapshot_sha256']
bundle = repo / 'resources/fiscal/bundles/consumption-phase4b-2026.10.21-122-draft1.json'
assert sha(bundle) == 'adff95ceb041f3733bc886a39b7a766f3a5452f69982490d9fbae3dad3a50632'
report = {
    'created_at': datetime.now(timezone.utc).isoformat(),
    'status': 'CREDIT122_CORRECTION_QUESTIONS_DIRECTED_AND_QUALITY_VERIFIED',
    'tests': 197, 'assertions': 698, 'phpstan_errors': 0, 'pint_files': 605,
    'checked_code_test_resource_files': len(paths), 'changed_files': sorted(changed),
    'resource_membership_and_all_resource_bytes_unchanged': True,
    'runtime_hashes': runtime, 'checks': checks,
    'log_text_normalization': 'ANSI presentation sequences removed after raw log SHA verification',
    'original_question_gap_witnesses_unchanged': True,
    'historical1441_receipt_applies_to_pre_question_fix_runtime_only': True,
    'fresh_native_capture_complete': False, 'full122_acceptance': False,
    'phase4b_complete': False, 'production_ready': False,
}
with (root / 'question-fix-directed-validation.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print('PASS:197 tests/698 assertions,PHPStan0,Pint605;all resource bytes unchanged;exactly4 code/test changes')
