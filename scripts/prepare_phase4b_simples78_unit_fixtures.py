"""Preserve native component cases in checkout evidence for permanent PHP tests."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-simples78-integration-preparation-20261015'
S = Path(r'D:\ProjetoSaasExports\phase4b-simples78-component-preparation-20261015')
assert (E / 'before-integration-archive.json').exists()
validation = json.loads((S / 'staged-component-development-validation-v4.json').read_text(encoding='utf-8'))
records = []
for name, target in [('native-cases.json', 'component-cases.json'), ('candidate-policy.json', 'component-policy.json')]:
    raw = (S / name).read_bytes()
    digest = hashlib.sha256(raw).hexdigest()
    assert digest == validation['input_hashes'][name]
    with (E / target).open('xb') as stream:
        stream.write(raw)
    assert hashlib.sha256((E / target).read_bytes()).hexdigest() == digest
    records.append({'path': (E / target).relative_to(ROOT).as_posix(), 'sha256': digest})
with (E / 'component-fixture-installation.json').open('x', encoding='utf-8') as stream:
    json.dump({'files': records, 'bundle_active': False, 'production_ready': False}, stream, indent=2)
print('PASS:two exact staged fixtures preserved for permanent unit tests')
