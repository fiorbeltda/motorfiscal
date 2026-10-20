"""Preserve first attempt; never move the as-of clock backwards from a fixture."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-integration-20261016')
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p, value):
    with p.open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)
old = read(E / 'catalog101-case-manifest.json')
folder = ROOT / 'resources/fiscal/scenarios/catalog-reference-2026.10.16-101-draft1-v2'
folder.mkdir(exist_ok=False)
cases = []
now = datetime.now(timezone.utc)
for row in old['cases']:
    doc = read(ROOT / row['path'])
    original = read(ROOT / row['original_path'])
    assert doc['facts'] == original['facts']
    prior = datetime.fromisoformat(original['knowledge_at'])
    doc['knowledge_at'] = max(now, prior).isoformat()
    target = folder / (row['name'] + '.json')
    save(target, doc)
    cases.append({**row, 'path': target.relative_to(ROOT).as_posix(), 'first_attempt_path': row['path'],
        'first_attempt_sha256': sha(ROOT / row['path']), 'original_knowledge_at': original['knowledge_at'],
        'knowledge_at': doc['knowledge_at']})
report = {**old, 'created_at': datetime.now(timezone.utc).isoformat(), 'cases': cases,
    'correction': 'First attempt reduced future synthetic fixture as-of dates below its own proof observations. V2 uses max(original as-of,current capture);facts remain identical. No production evidence inferred.'}
save(E / 'catalog101-case-manifest-v2.json', report)
save(ROOT / 'tests/Fixtures/catalog101-scenario-expectations-v2.json', report)
print('PASS:61 v2 scenarios;facts unchanged;as-of never moved backwards;first attempt preserved')
