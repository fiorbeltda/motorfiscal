"""Promote six small proven synthetic inputs, not evidence snapshots, to own fixtures."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-schema74-cli-corpus-v2-20261014'
F = ROOT / 'resources/fiscal/scenarios/dps-local-2026.10.14-74-draft1'
report = json.loads((E / 'cli-corpus-validation.json').read_text(encoding='utf-8'))
assert report['status'] == 'LOCAL_SCHEMA74_CLI_CORPUS_VERIFIED'
assert report['production_ready'] is False and len(report['cases']) == 3
F.mkdir(exist_ok=False)
records = []
for case in report['cases']:
    assert len(case['xml']) == 3 and len(case['refusals']) == 4
    for kind, source_name in [('scenario', case['name'] + '-scenario.json'),
                              ('identity', case['name'] + '-identity-1.json')]:
        source = E / source_name
        target = F / (case['name'] + '-' + kind + '.json')
        with target.open('xb') as stream:
            stream.write(source.read_bytes())
        digest = hashlib.sha256(target.read_bytes()).hexdigest()
        assert digest == hashlib.sha256(source.read_bytes()).hexdigest()
        records.append({'source': source.relative_to(ROOT).as_posix(),
                        'target': target.relative_to(ROOT).as_posix(), 'sha256': digest})
with (E / 'permanent-fixture-installation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'SIX_SYNTHETIC_SCHEMA74_FIXTURES_INSTALLED', 'files': records,
               'production_ready': False}, stream, indent=2)
print('PASS:six own synthetic inputs installed with exact source hashes')
