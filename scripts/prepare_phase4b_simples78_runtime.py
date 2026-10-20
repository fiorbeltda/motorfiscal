"""Archive the accepted76 runtime before the own source-bound Simples78 integration."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-simples78-integration-preparation-20261015'
A = ROOT / 'storage/app/phase4b-foreign-acquirer76-development-20261015'
S = Path(r'D:\ProjetoSaasExports\phase4b-simples78-component-preparation-20261015')


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


accepted = read(A / 'foreign-acquirer76-development-validation.json')
assert accepted['full_suite_complete'] is True and accepted['production_ready'] is False
assert accepted['full_suite']['tests'] == 2370 and accepted['full_suite']['assertions'] == 46214
assert sha(A / 'full-suite.log') == accepted['full_suite']['log_sha256']
for path, digest in accepted['runtime_hashes'].items():
    assert sha(ROOT / path) == digest
preflight = read(A / 'preflight.json')
staged = read(S / 'staged-component-development-validation-v4.json')
assert staged['status'] == 'SIMPLES78_STAGED_COMPONENT_DEVELOPMENT_VERIFIED_NOT_INSTALLED'
assert staged['cases'] == 48 and staged['assertions'] == 240
for file, digest in staged['input_hashes'].items():
    assert sha(S / file) == digest
paths = ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
         'app/Modules/Fiscal/ConsumptionReform/TransitionCatalog.php',
         'resources/fiscal/bundles/consumption-phase4b-2026.10.15-76-draft1.json',
         'resources/fiscal/catalogs/rtc-transition-2026.10.01-2.json',
         'resources/fiscal/facts/consumption-phase4b-2026.10.14-73-draft1.json',
         'resources/fiscal/questions/consumption-integrated-2026.10.14-73-draft1.json']
assert not (ROOT / 'app/Modules/Fiscal/ConsumptionReform/SimplesRegularElectionQualifier78.php').exists()
for path in paths:
    assert sha(ROOT / path) == preflight['input_hashes'][path], path
E.mkdir(exist_ok=False)
records = []
for index, path in enumerate(paths):
    source = ROOT / path
    name = 'before-' + str(index) + '-' + source.name
    with (E / name).open('xb') as stream:
        stream.write(source.read_bytes())
    assert sha(E / name) == sha(source)
    records.append({'path': path, 'archive': name, 'sha256': sha(source)})
with (E / 'before-integration-archive.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'ACCEPTED76_PATHS_ARCHIVED_BEFORE_SIMPLES78_INTEGRATION',
               'accepted76_sha256': sha(A / 'foreign-acquirer76-development-validation.json'),
               'staged_component_validation_sha256': sha(S / 'staged-component-development-validation-v4.json'),
               'files': records, 'production_ready': False}, stream, ensure_ascii=False, indent=2)
print('PASS:six accepted76 inputs archived;staged78 inputs match development validation;no runtime changed')
