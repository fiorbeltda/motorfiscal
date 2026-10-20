"""Archive the accepted command before adding an opt-in restricted-schema contract."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-local-schema74-integration-20261014'
prior = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014/export-integration-development-validation.json'
accepted = json.loads(prior.read_text(encoding='utf-8'))
assert accepted['full_suite_complete'] and accepted['full_suite'] == {'tests': 2266, 'assertions': 45742}
E.mkdir(exist_ok=False)
command = ROOT / 'app/Console/Commands/ProjectDpsXmlCommand.php'
raw = command.read_bytes()
with (E / 'before-project-dps-command.php').open('xb') as stream:
    stream.write(raw)
with (E / 'pre-change-archive.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'ACCEPTED73_COMMAND_ARCHIVED_BEFORE_OPT_IN_SCHEMA74',
               'command_path': command.relative_to(ROOT).as_posix(),
               'command_sha256': hashlib.sha256(raw).hexdigest(),
               'prior_acceptance_path': prior.relative_to(ROOT).as_posix(),
               'prior_acceptance_sha256': hashlib.sha256(prior.read_bytes()).hexdigest(),
               'production_ready': False}, stream, indent=2)
print('PASS:accepted73 terminal result required; prior command archived exclusively')
