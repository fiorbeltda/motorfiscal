"""Archive accepted paths before the opt-in international candidate integration."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-foreign-acquirer76-preparation-20261015'
accepted_path = ROOT / 'storage/app/phase4b-schema74-development-20261014/schema74-development-validation.json'
accepted = json.loads(accepted_path.read_text(encoding='utf-8'))
assert accepted['full_suite_complete'] and accepted['production_ready'] is False
for path, digest in accepted['runtime_hashes'].items():
    assert hashlib.sha256((ROOT / path).read_bytes()).hexdigest() == digest, path
E.mkdir(exist_ok=False)
paths = ['app/Modules/Fiscal/NFSe/DpsXmlProjector.php',
         'app/Console/Commands/ProjectDpsXmlCommand.php',
         'app/Modules/Fiscal/NFSe/DpsRestrictedSchemaSelection.php',
         'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
         'app/Modules/Fiscal/NFSe/DpsForeignAcquirerCandidate.php',
         'app/Modules/Fiscal/NFSe/DpsForeignPartyProjector.php']
records = []
for path in paths:
    source = ROOT / path
    target = E / ('before-' + source.name)
    with target.open('xb') as stream:
        stream.write(source.read_bytes())
    digest = hashlib.sha256(target.read_bytes()).hexdigest()
    assert digest == hashlib.sha256(source.read_bytes()).hexdigest()
    records.append({'path': path, 'archive': target.name, 'sha256': digest})
with (E / 'before-integration-archive.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'ACCEPTED74_PATHS_ARCHIVED_BEFORE_FOREIGN_CANDIDATE76',
        'accepted74_sha256': hashlib.sha256(accepted_path.read_bytes()).hexdigest(),
        'files': records, 'new_candidate76_already_drafted_not_wired': True,
        'production_ready': False}, stream, indent=2)
print('PASS:six files archived and accepted74 path hashes verified before integration')
