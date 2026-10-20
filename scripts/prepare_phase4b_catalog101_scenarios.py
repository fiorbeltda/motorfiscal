"""Own101 scenarios retain original business facts,only rebinding the new bundle/knowledge epoch."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-integration-20261016')
P = Path(r'D:\ProjetoSaasExports\phase4b-catalog100-reference-preparation-20261016')
V = '2026.10.16-101-draft1'
BUNDLE = 'bundles/consumption-phase4b-' + V + '.json'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
bundle = read(F / BUNDLE)
folder = F / 'scenarios' / ('catalog-reference-' + V)
folder.mkdir(exist_ok=False)
rows = []
for r in read(Path(r'D:\ProjetoSaasExports\phase4b-sp98-integration-20261016\sp98-case-manifest.json'))['cases']:
    rows.append({'name': 'export-' + r['name'], 'path': r['path'], 'status': 'ACTION_REQUIRED', 'reason': r['reason']})
for r in read(ROOT / 'storage/app/phase4b-advance91-cli-20261015/advance91-cli-corpus-validation.json')['cases']:
    rows.append({'name': 'advance-' + r['case'], 'path': r['path'], 'status': r['status'], 'reason': r['reason']})
rows.append({'name': 'regular-saas-positive', 'path': 'resources/fiscal/scenarios/consumption-phase4b/regular-saas-2026.json',
    'status': 'VALID', 'reason': None})
assert len(rows) == 61
known = datetime.now(timezone.utc).isoformat()
cases = []
for row in rows:
    source = ROOT / row['path']
    doc = read(source)
    facts_hash = hashlib.sha256(json.dumps(doc['facts'], sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
    doc.update(scenario_id='CATALOG101-' + row['name'], knowledge_at=known,
        rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'])
    target = folder / (row['name'] + '.json')
    save(target, doc)
    cases.append({**row, 'path': target.relative_to(ROOT).as_posix(), 'original_path': row['path'],
        'original_sha256': sha(source), 'original_facts_sha256': facts_hash})
manifest = {'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': BUNDLE, 'cases': cases,
    'new_business_facts': False, 'full_suite_complete': False, 'production_ready': False}
save(E / 'catalog101-case-manifest.json', manifest)
save(ROOT / 'tests/Fixtures/catalog101-scenario-expectations.json', manifest)
fixture = ROOT / 'tests/Fixtures/cclass100-reference-cases.json'
assert not fixture.exists()
shutil.copy2(P / 'reference-cases.json', fixture)
print('PASS:61 own101 scenarios,business facts unchanged;reference100 fixture2981')
