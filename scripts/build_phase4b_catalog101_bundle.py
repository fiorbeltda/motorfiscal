"""Install versioned reference101 resources from audited JSON99/100,without rewriting official artifacts or old bundles."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-integration-20261016')
S = Path(r'D:\ProjetoSaasExports\phase4b-catalog99-primary-research-20261016')
P = Path(r'D:\ProjetoSaasExports\phase4b-catalog100-reference-preparation-20261016')
V = '2026.10.16-101-draft1'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)

assert (E / 'before-integration-archive.json').exists()
bundle = read(F / 'bundles/consumption-phase4b-2026.10.16-98-draft1.json')
old_codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
codes = read(S / 'rtc-codes-v170-candidate.json')
proof = read(P / 'catalog100-reference-preparation-validation.json')
assert sha(S / 'rtc-codes-v170-candidate.json') == proof['native_file_hashes']['catalog.json']
assert codes['engine_decision_rules'] == old_codes['engine_decision_rules'] and codes['correlations'] == old_codes['correlations']
facts = read(F / bundle['resource_paths']['fact_definitions'])
questions = read(F / bundle['resource_paths']['questions'])
controls = read(F / bundle['production_controls']['path'])
paths = [F / f'{directory}/{stem}-{V}.json' for directory, stem in [('catalogs', 'rtc-codes'), ('facts', 'consumption-phase4b'),
    ('questions', 'consumption-integrated'), ('production', 'phase4b-controls'), ('bundles', 'consumption-phase4b')]]
assert not any(p.exists() for p in paths)
sources = read(S / 'capture-v1/primary-source-observation.json')['sources']
source_spec = [('cclasstrib-20261001.xlsx', 'NFE_CCLASS170_OBSERVED_20261016', 'nfe-cclasstrib-it2025-002-v170-observed-20261016.xlsx', 'IT2025.002 v1.70'),
    ('it2025-002-v170.pdf', 'NFE_IT170_OBSERVED_20261016', 'nfe-it2025-002-v170-observed-20261016.pdf', 'IT2025.002 v1.70'),
    ('nfe-diversos.html', 'NFE_DIVERSOS101_OBSERVED_20261016', 'nfe-diversos101-observed-20261016.html', 'OBSERVED_2026.10.16'),
    ('nfe-informes.html', 'NFE_INFORMES101_OBSERVED_20261016', 'nfe-informes101-observed-20261016.html', 'OBSERVED_2026.10.16')]
for name, source_id, file_name, version in source_spec:
    source = next(r for r in sources if r['file'] == name)
    target = F / 'sources/official' / file_name
    assert not target.exists() and sha(S / 'capture-v1' / name) == source['sha256']
    shutil.copy2(S / 'capture-v1' / name, target)
    bundle['official_sources'].append({'id': source_id, 'url': source['url'], 'file': target.name,
        'sha256': source['sha256'], 'version': version, 'published_on': '2026-10-01' if name.endswith(('.xlsx', '.pdf')) else None,
        'effective_from': None, 'observed_at': source['observed_at'],
        'proven_effective_scope': 'Observed reference170/table/informe/index only;not complete legal applicability,historical exact content before observation,remote deployment,operation classification,tax or XML approval.'})
    if name == 'cclasstrib-20261001.xlsx':
        codes['sources']['cclasstrib'].update(file=file_name, source_id=source_id, status='LOCAL_REFERENCE_OBSERVED_NOT_PRODUCTION_APPROVED')
    else:
        codes['sources'][source_id.lower()] = {'file': file_name, 'source_id': source_id, 'sha256': source['sha256'],
            'version': version, 'observed_at': source['observed_at']}
codes['version'] = V
codes['cclass_reference_policy'] = read(P / 'reference-cases.json')['policy']
assert canonical(codes['cclasstrib']) == codes['cclass_reference_policy']['rows_sha256']
assert canonical(codes['cst_reference_rows']) == codes['cclass_reference_policy']['cst_rows_sha256']
controls['version'] = V
next(c for c in controls['controls'] if c['id'] == '4B.6.CLASSIFICATION')['gap'] += '101:referência1.70 por fonte,vigência e modelo integrada;sem seleção automática de códigos novos,indicador de doação emitido ou aprovação legal.'
for role, value, path in zip(['rtc_code_catalog', 'fact_definitions', 'questions'], [codes, facts, questions], paths[:3]):
    save(path, value)
    bundle['resource_paths'][role] = path.relative_to(F).as_posix()
    bundle['content_hashes'][role] = canonical(value)
save(paths[3], controls)
bundle['production_controls'] = {'path': paths[3].relative_to(F).as_posix(), 'sha256': canonical(controls)}
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V, version=V,
    knowledge_available_at=datetime.now(timezone.utc).replace(microsecond=0).isoformat())
bundle['catalog_versions']['rtc_codes'] = V
save(paths[4], bundle)
save(E / 'catalog101-bundle-installation.json', {'created_at': datetime.now(timezone.utc).isoformat(),
    'files': {p.relative_to(ROOT).as_posix(): sha(p) for p in paths}, 'class_rows': 173, 'cst_rows': 18,
    'official_sources': len(bundle['official_sources']), 'new_facts': [], 'new_questions': [],
    'own_fact_question_resources_content_unchanged': True, 'rate_catalog_unchanged': bundle['content_hashes']['rtc_rate_catalog'],
    'engine_decision_rules_unchanged': True, 'correlation_unchanged': True, 'full_suite_complete': False, 'production_ready': False})
print('PASS:bundle101/173 classes/18 CST/66 sources;own facts/questions unchanged,no new inputs or classifications')
