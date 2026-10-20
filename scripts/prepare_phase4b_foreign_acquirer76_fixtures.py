"""Promote proven own CLI inputs; no historical snapshots or official sources changed."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-foreign-acquirer76-cli-20261015'
F = ROOT / 'resources/fiscal/scenarios/dps-foreign-acquirer-2026.10.15-76-draft1'
report = json.loads((E / 'foreign-acquirer-cli-validation.json').read_text(encoding='utf-8'))
assert len(report['cases']) == 3 and report['production_ready'] is False
F.mkdir(exist_ok=False)
records = []
for case in report['cases']:
    for key in ['scenario', 'identity']:
        source = ROOT / case[key]
        target = F / (case['case'] + '-' + key + '.json')
        with target.open('xb') as stream:
            stream.write(source.read_bytes())
        digest = hashlib.sha256(target.read_bytes()).hexdigest()
        assert digest == hashlib.sha256(source.read_bytes()).hexdigest()
        records.append({'path': target.relative_to(ROOT).as_posix(), 'sha256': digest})
with (E / 'permanent-fixture-installation.json').open('x', encoding='utf-8') as stream:
    json.dump({'files': records, 'production_ready': False}, stream, indent=2)
print('PASS:six own CLI inputs installed by exact source hash')
