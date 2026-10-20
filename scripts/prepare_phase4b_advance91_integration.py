"""Verify accepted88 and staged89/90, archive before edits and mechanically install the two components."""
from pathlib import Path
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-advance91-integration-20261015'
A = ROOT / 'storage/app/phase4b-advance88-development-20261015'
D = Path(r'D:\ProjetoSaasExports')
S89 = D / 'phase4b-advance89-record-binding-preparation-20261015'
S90 = D / 'phase4b-advance90-final-reconciliation-preparation-20261015'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
accepted = read(A / 'advance88-development-validation.json')
assert accepted['full_suite_complete'] and accepted['full_suite']['tests'] == 2678
assert accepted['full_suite']['assertions'] == 48287 and accepted['full_suite']['exit_code'] == 0
for path, digest in read(A / 'preflight.json')['input_hashes'].items(): assert sha(ROOT / path) == digest, path
for path, digest in accepted['runtime_hashes'].items(): assert sha(ROOT / path) == digest, path
p89 = read(S89 / 'staged89-record-binding-validation.json')
p90 = read(S90 / 'staged90-captured-reconciliation-validation-v2.json')
assert p89['native_cases_passed'] == 43 and p90['native_cases_passed'] == 28
assert p90['independent_decimal_oracle_cases'] == 14
assert p90['consumption_proposal_token_binds_final_context_and_final_calculation'] is True
for folder, proof, native_folder in [(S89, p89, 'probe-v5-formatted'), (S90, p90, 'probe-v4-final')]:
    execution = proof['native_execution']
    assert execution['exit_code'] == 0
    assert sha(folder / native_folder / 'native.stdout') == execution['stdout_sha256']
    assert sha(folder / native_folder / 'native.stderr') == execution['stderr_sha256']
    for path, digest in execution['source_hashes'].items():
        source = Path(path) if Path(path).is_absolute() else folder / path
        assert sha(source) == digest, path
    for label, gate in proof['quality_gates'].items():
        assert gate['exit_code'] == 0 and sha(folder / (label + '.log')) == gate['log_sha256']
targets = [ROOT / 'app/Modules/Fiscal/ConsumptionReform' / (name + '.php')
    for name in ['PriorAdvanceRecordSet89', 'CapturedAdvanceFinalReconciler90']]
assert not E.exists() and not any(p.exists() for p in targets)
E.mkdir()
archive = {}
for path in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/Infrastructure/Scenario/RtcScenarioRunService.php',
    'resources/monitoring/task-progress.json', 'resources/monitoring/objective-review.json']:
    target = E / 'before-integration' / path
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(ROOT / path, target)
    assert sha(target) == sha(ROOT / path)
    archive[path] = sha(target)
installed = {}
for folder, source_name, target in [(S89, 'PriorAdvanceRecordSet89-formatted-v2.php', targets[0]),
        (S90, 'CapturedAdvanceFinalReconciler90-formatted-v2.php', targets[1])]:
    source = folder / source_name
    shutil.copy2(source, E / source_name)
    code = source.read_text(encoding='utf-8')
    code = code.replace('namespace Staged89;', 'namespace App\\Modules\\Fiscal\\ConsumptionReform;')
    code = code.replace('namespace Staged90;', 'namespace App\\Modules\\Fiscal\\ConsumptionReform;')
    code = code.replace('use Staged89\\PriorAdvanceRecordSet89;', 'use App\\Modules\\Fiscal\\ConsumptionReform\\PriorAdvanceRecordSet89;')
    with target.open('x', encoding='utf-8') as stream: stream.write(code)
    installed[target.relative_to(ROOT).as_posix()] = sha(target)
for folder, name in [(S89, 'staged89-record-binding-validation.json'), (S90, 'staged90-captured-reconciliation-validation-v2.json')]:
    shutil.copy2(folder / name, E / name)
with (E / 'before-integration-archive.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'ACCEPTED88_ARCHIVED_VERIFIED89_90_COMPONENTS_INSTALLED_FOR91',
        'accepted88_sha256': sha(A / 'advance88-development-validation.json'), 'archived': archive,
        'installed': installed, 'staged89_validation_sha256': sha(S89 / 'staged89-record-binding-validation.json'),
        'staged90_validation_sha256': sha(S90 / 'staged90-captured-reconciliation-validation-v2.json'),
        'full_suite91_complete': False, 'production_ready': False}, stream, indent=2)
print('PASS:accepted88 fully checked/archived;verified89/90 installed mechanically;integration91 pending')
