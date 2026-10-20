"""Promote reviewed export prototypes only after actual full predecessor acceptance."""
from pathlib import Path
import hashlib, json
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
def read(path): return json.loads(path.read_text(encoding='utf-8'))
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
accepted = read(Q / 'combined-integration-development-validation.json')
assert accepted['full_suite_complete'] and accepted['full_suite']['tests'] == 2233
for item in accepted['implementation']: assert sha(ROOT / item['path']) == item['sha256']
prepared = read(E / 'partial-export-fx-preparation-validation.json')
for item in prepared['implementation'] + prepared['evidence']: assert sha(ROOT / item['path']) == item['sha256']
live = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php'
assert sha(live) == prepared['current_engine_sha256']
changes = [
    ('planned-partial-export-quote-v2.php', 'PartialExportFxQuoteConverter.php'),
    ('planned-partial-export-transaction.php', 'PartialExportFxTransactionQualifier.php'),
    ('planned-partial-export-engine.php', 'ConsumptionReformEngine.php'),
]
app_dir = live.parent
assert not (app_dir / changes[0][1]).exists() and not (app_dir / changes[1][1]).exists()
archive = E / 'before-runtime-export-install-engine.php'
assert not archive.exists() and not (E / 'export-fx-installation.json').exists()
with archive.open('xb') as stream: stream.write(live.read_bytes())
installed = []
for source, filename in changes:
    text = (E / source).read_text(encoding='utf-8')
    for before, after in [('PartialExportFxQuotePrototype73', 'PartialExportFxQuoteConverter'),
                          ('PartialExportFxTransactionPrototype73', 'PartialExportFxTransactionQualifier'),
                          ('PartialExportEnginePrototype73', 'ConsumptionReformEngine')]: text = text.replace(before, after)
    target = app_dir / filename
    if target == live: target.write_text(text, encoding='utf-8')
    else:
        with target.open('x', encoding='utf-8') as stream: stream.write(text)
    installed.append({'path': target.relative_to(ROOT).as_posix(), 'sha256': sha(target)})
with (E / 'export-fx-installation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'EXPORT_CONVERSION_INSTALLED_OWN_POLICY_AND_NATIVE_VALIDATION_PENDING',
               'implementation': installed, 'before_engine_sha256': sha(archive),
               'predecessor_acceptance_sha256': sha(Q / 'combined-integration-development-validation.json'),
               'scope73_complete': False, 'production_ready': False}, stream, indent=2)
print('PASS:reviewed export code promoted after full predecessor acceptance; policy and validation remain pending')
