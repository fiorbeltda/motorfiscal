"""Preserve first proposal and prepare guarded successor without altering live CLI."""
from pathlib import Path
import hashlib
import json
import difflib

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009'
target = ROOT / 'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php'

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

old = E / 'planned-monthly-command.php'
new = E / 'planned-monthly-command-v2.php'
original = old.read_text(encoding='utf-8')
assert original.count('if (isset($settlement)) {') == 1
reviewed = original.replace('if (isset($settlement)) {', 'if (isset($settlement, $settlementRequest)) {')
with new.open('x', encoding='utf-8', newline='\n') as stream:
    stream.write(reviewed)
before = E / 'before-settlement-selection-monthly-command.php'
assert sha(before) == sha(target)
patch = E / 'planned-monthly-command-v2.patch'
with patch.open('x', encoding='utf-8', newline='\n') as stream:
    stream.writelines(difflib.unified_diff(before.read_text(encoding='utf-8').splitlines(True), reviewed.splitlines(True),
        fromfile='a/app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php',
        tofile='b/app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php'))
record = {'status': 'REVIEWED_PREPARATION_NOT_APPLIED', 'live_command_changed': False,
    'before_sha256': sha(before), 'first_proposal_sha256': sha(old), 'reviewed_proposal_sha256': sha(new),
    'patch_sha256': sha(patch), 'planned_test_sha256': sha(E / 'planned-command-test.php'),
    'static_analysis_first_attempt': json.loads((E / 'planned-command-phpstan.json').read_text(encoding='utf-8')),
    'review': {'report_request_guard_added': True, 'new_option_requires_installed_command_for_larastan': True,
        'wrapper_stdout_encoding_failure_preserved': True, 'integration_tests_executed': False},
    'production_ready': False}
with (E / 'reviewed-source-preparation.json').open('x', encoding='utf-8') as stream:
    json.dump(record, stream, indent=2)
print('PASS: guarded proposal and 13 integration cases prepared; live CLI unchanged')
