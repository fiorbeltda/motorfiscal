"""Mechanically version96 resources around the live ISS-result opt-in; preserve91 and all rate catalogs."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-integration-20261016')
S = Path(r'D:\ProjetoSaasExports\phase4b-document94-iss-bridge-sources-20261016')
V = '2026.10.16-96-draft1'

def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)

assert (E / 'before-integration-archive.json').exists()
bundle = read(F / 'bundles/consumption-phase4b-2026.10.15-91-draft1.json')
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
facts = read(F / bundle['resource_paths']['fact_definitions'])
questions = read(F / bundle['resource_paths']['questions'])
controls = read(F / bundle['production_controls']['path'])
source = next(s for s in read(S / 'iss-bridge-primary-observation.json')['sources'] if s['file'] == 'lc116-current.html')
assert sha(S / source['file']) == '3ae4d3fc894ced12874364e366867b736c81369352cbaf97c70d57e534cdf86c'
source_path = F / 'sources/official/iss-export96-lc116-observed-20261016.html'
paths = [F / f'{directory}/{stem}-{V}.json' for directory, stem in [
    ('catalogs', 'rtc-codes'), ('facts', 'consumption-phase4b'), ('questions', 'consumption-integrated'),
    ('production', 'phase4b-controls'), ('bundles', 'consumption-phase4b')]]
assert not any(p.exists() for p in [source_path, *paths, E / 'export-iss96-bundle-installation.json'])
shutil.copy2(S / source['file'], source_path)
assert sha(source_path) == source['sha256']
codes['version'] = V
codes['partial_export_iss_result_policy'] = {
    'current_binding_contract': 'ISS-CURRENT-PARTIAL-EXPORT-CONTEXT-2026.10.16-96',
    'contract': 'ISS-SOFTWARE-EXPORT-RESULT-LOCAL-2026.10.16-95',
    'source_sha256': source['sha256'], 'source_observed_at': source['observed_at'],
    'scope': 'LOCAL_SOFTWARE_RESULT_EVIDENCE_NOT_LEGAL_ACCEPTANCE', 'source_effective_date_proven': False}
codes['sources']['iss_export_result96'] = {'source_id': 'ISS_EXPORT96_LC116_OBSERVED',
    'file': source_path.name, 'sha256': source['sha256'], 'version': 'OBSERVED_2026.10.16', 'retrieved_on': '2026-10-16'}
added = [('rtc.assess_export_iss_results', 'BOOLEAN', 'Qualificar resultados ISS independentes para os dois documentos da exportação parcial?'),
    ('rtc.export_operation_reference', 'STRING', 'Qual é a referência da operação que vincula as duas provas ISS, sem presumir autenticação?'),
    ('rtc.export_iss_result_contexts', 'ARRAY', 'Informe exatamente os dois contextos RTC_FOREIGN/RTC_DOMESTIC e suas provas ISS de resultado, documento, partes e data.')]
rank = max(q['rank'] for q in questions)
for i, (key, kind, prompt) in enumerate(added, 1):
    assert not any(f['key'] == key for f in facts)
    fact = {'key': key, 'type': kind, 'material': True}
    if kind == 'STRING': fact['min_length'] = 1
    facts.append(fact)
    questions.append({'fact_key': key, 'answer_type': kind, 'rank': rank + i, 'prompt': prompt})
controls['version'] = V
next(c for c in controls['controls'] if c['id'] == '4B.2.EXPORT_IMPORT')['gap'] += '96: resultados ISS por documento vinculados localmente após alocação RTC;identidade fiscal,data ISS,conversão,base e par XML ainda não qualificados.'
for role, value, path in zip(['rtc_code_catalog', 'fact_definitions', 'questions'], [codes, facts, questions], paths[:3]):
    save(path, value)
    bundle['resource_paths'][role] = path.relative_to(F).as_posix()
    bundle['content_hashes'][role] = canonical(value)
save(paths[3], controls)
bundle['production_controls'] = {'path': paths[3].relative_to(F).as_posix(), 'sha256': canonical(controls)}
bundle['official_sources'].append({'id': 'ISS_EXPORT96_LC116_OBSERVED', 'url': source['url'],
    'file': source_path.name, 'sha256': source['sha256'], 'version': 'OBSERVED_2026.10.16',
    'published_on': None, 'effective_from': None, 'observed_at': source['observed_at'],
    'proven_effective_scope': 'Observed LC116 art2 text for local software result-evidence binding only;not historical/municipal applicability,conversion,tax,document acceptance or production.'})
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V,
              version=V, knowledge_available_at=datetime.now(timezone.utc).replace(microsecond=0).isoformat())
bundle['catalog_versions']['rtc_codes'] = V
save(paths[4], bundle)
save(E / 'export-iss96-bundle-installation.json', {'created_at': datetime.now(timezone.utc).isoformat(),
    'status': 'EXPORT_ISS96_VERSIONED_RESOURCES_CREATED_VALIDATION_PENDING',
    'files': {p.relative_to(ROOT).as_posix(): sha(p) for p in [source_path, *paths]},
    'added_facts': [a[0] for a in added], 'official_sources': len(bundle['official_sources']),
    'rate_catalog_unchanged': bundle['content_hashes']['rtc_rate_catalog'], 'full_suite_complete': False, 'production_ready': False})
print('PASS:bundle96/three facts and questions/60 sources;rates and legacy resources untouched', flush=True)
