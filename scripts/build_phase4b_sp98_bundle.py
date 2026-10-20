"""Version SP98 resources; old bundles, parameters and classification rules remain byte-identical."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = Path(r'D:\ProjetoSaasExports\phase4b-sp98-integration-20261016')
S = Path(r'D:\ProjetoSaasExports\phase4b-document97-sp-primary-research-20261016-v2')
V = '2026.10.16-98-draft1'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)

assert (E / 'before-integration-archive.json').exists()
bundle = read(F / 'bundles/consumption-phase4b-2026.10.16-96-draft1.json')
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
facts = read(F / bundle['resource_paths']['fact_definitions'])
questions = read(F / bundle['resource_paths']['questions'])
controls = read(F / bundle['production_controls']['path'])
paths = [F / f'{d}/{stem}-{V}.json' for d, stem in [('catalogs', 'rtc-codes'), ('facts', 'consumption-phase4b'),
    ('questions', 'consumption-integrated'), ('production', 'phase4b-controls'), ('bundles', 'consumption-phase4b')]]
assert not any(p.exists() for p in paths)
sources = read(S / 'primary-source-observation.json')['sources']
for source_name, source_id, stem in [('sp-pn4-2016-consolidado.html', 'SP98_PN4_2016_OBSERVED', 'sp98-pn4-2016-observed'),
    ('sp-pn4-2016-detalhes.html', 'SP98_PN4_2016_DETAILS_OBSERVED', 'sp98-pn4-2016-details-observed')]:
    source = next(s for s in sources if s['file'] == source_name)
    target = F / 'sources/official' / (stem + '-20261016.html')
    assert not target.exists() and sha(S / source_name) == source['sha256']
    shutil.copy2(S / source_name, target)
    bundle['official_sources'].append({'id': source_id, 'url': source['url'], 'file': target.name, 'sha256': source['sha256'],
        'version': 'OBSERVED_2026.10.16', 'published_on': '2016-11-10', 'effective_from': None,
        'observed_at': source['observed_at'],
        'proven_effective_scope': 'Observed SP municipal PN4/2016 source/details;local documentary conditions only,not complete historical applicability,FX,tax or document approval.'})
    codes['sources'][source_id.lower()] = {'source_id': source_id, 'file': target.name, 'sha256': source['sha256'],
        'version': 'OBSERVED_2026.10.16', 'retrieved_on': '2026-10-16'}
codes['version'] = V
codes['partial_export_sp_result_policy'] = {'current_binding_contract': 'SP-CURRENT-PARTIAL-EXPORT-CONTEXT-2026.10.16-98',
    'source_effective_date_proven': False, 'contract': 'SP-SOFTWARE-EXPORT-RESULT-LOCAL-2026.10.16-97',
    'source_sha256': '066427dc1da3c25fddd1628bc3c168d1805665e113b2d7c5bbb894825e20a274',
    'source_observed_at': '2026-10-16T16:28:22.913454+00:00', 'scope': 'LOCAL_SP_ITEM1_RESULT_NOT_LEGAL_ACCEPTANCE'}
added = [('rtc.assess_export_sp_results', 'BOOLEAN', 'Qualificar provas municipaisSP de resultado dos dois documentos,após a avaliação ISS nacional?'),
    ('rtc.export_sp_result_proofs', 'ARRAY', 'Informe dois registros RTC_FOREIGN/RTC_DOMESTIC com municipal_proof independente,vínculo de software,pessoaBR e sujeito do resultado.')]
rank = max(q['rank'] for q in questions)
for i, (key, kind, prompt) in enumerate(added, 1):
    assert not any(f['key'] == key for f in facts)
    facts.append({'key': key, 'type': kind, 'material': True})
    questions.append({'fact_key': key, 'answer_type': kind, 'rank': rank + i, 'prompt': prompt})
controls['version'] = V
next(c for c in controls['controls'] if c['id'] == '4B.2.EXPORT_IMPORT')['gap'] += '98:condições municipaisSP do resultado vinculadas localmente;sem aprovação legal,conversão,imposto ou XML por documento.'
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
save(E / 'sp98-bundle-installation.json', {'created_at': datetime.now(timezone.utc).isoformat(),
    'files': {p.relative_to(ROOT).as_posix(): sha(p) for p in paths}, 'added_facts': [r[0] for r in added],
    'official_sources': len(bundle['official_sources']), 'rate_catalog_unchanged': bundle['content_hashes']['rtc_rate_catalog'],
    'full_suite_complete': False, 'production_ready': False})
print('PASS:own98 bundle/two facts/questions;62 sources;rates unchanged')
