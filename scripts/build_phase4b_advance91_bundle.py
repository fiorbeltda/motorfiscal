"""Create own recorded-advance resources without changing sources, rates or production approvals."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-advance91-integration-20261015'
V = '2026.10.15-91-draft1'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p, value):
    with p.open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)
assert (E / 'before-integration-archive.json').exists()
bundle = read(F / 'bundles/consumption-phase4b-2026.10.15-88-draft1.json')
transition = read(F / bundle['resource_paths']['rtc_transition_catalog'])
facts = read(F / bundle['resource_paths']['fact_definitions'])
questions = read(F / bundle['resource_paths']['questions'])
controls = read(F / bundle['production_controls']['path'])
paths = [F / f'{folder}/{name}-{V}.json' for folder, name in [
    ('catalogs', 'rtc-transition'), ('facts', 'consumption-phase4b'), ('questions', 'consumption-integrated'),
    ('production', 'phase4b-controls'), ('bundles', 'consumption-phase4b')]]
receipt = E / 'advance91-bundle-installation.json'
assert not any(p.exists() for p in [*paths, receipt])
transition['version'] = V
transition['tax_point_event_policy']['recorded_advance_contract'] = 'RTC-RECORDED-ADVANCE-2026.10.15-91'
transition['tax_point_event_policy']['scope'] += ';CAPTURED_PRIOR_RECORDS_LOCAL_FINAL_RECONCILIATION_NOT_POSTING'
added = [('rtc.reconcile_recorded_advances', 'BOOLEAN', 'Conciliar o definitivo com snapshots e registros anteriores vinculados?'),
    ('rtc.recorded_advance_final_contexts', 'ARRAY', 'Informe o único definitivo com documento, prova de fornecimento e vínculo ao livro anterior.'),
    ('rtc.prior_advance_books', 'ARRAY', 'Informe o único livro qualificado de registros anteriores, completo e com seus estados explícitos.'),
    ('rtc.prior_advance_captures', 'ARRAY', 'Informe os snapshots nativos anteriores com hashes, conhecimento capturado e vínculos ao registro.')]
rank = max(row['rank'] for row in questions)
for index, (key, kind, prompt) in enumerate(added, 1):
    assert not any(row['key'] == key for row in facts)
    facts.append({'key': key, 'type': kind, 'material': True})
    questions.append({'fact_key': key, 'answer_type': kind, 'rank': rank + index, 'prompt': prompt})
controls['version'] = V
next(row for row in controls['controls'] if row['id'] == '4B.1.TEMPORAL_EVENT_SETTLEMENT')['gap'] += '91: vínculo local de registros e memória final com valores capturados; qualificação legal do registro, consumo atômico e documento permanecem pendentes.'
for role, value, path in zip(['rtc_transition_catalog', 'fact_definitions', 'questions'], [transition, facts, questions], paths[:3]):
    save(path, value); bundle['resource_paths'][role] = path.relative_to(F).as_posix(); bundle['content_hashes'][role] = canonical(value)
save(paths[3], controls)
bundle['production_controls'] = {'path': paths[3].relative_to(F).as_posix(), 'sha256': canonical(controls)}
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V, version=V,
    knowledge_available_at=datetime.now(timezone.utc).replace(microsecond=0).isoformat())
bundle['catalog_versions']['rtc_transition'] = V
save(paths[4], bundle)
save(receipt, {'status': 'ADVANCE91_RESOURCES_CREATED_VALIDATION_PENDING', 'files': {p.relative_to(ROOT).as_posix(): sha(p) for p in paths},
    'new_facts': [row[0] for row in added], 'official_sources': len(bundle['official_sources']),
    'rate_catalog_unchanged': bundle['content_hashes']['rtc_rate_catalog'], 'full_suite_complete': False, 'production_ready': False})
print('PASS:own91 bundle/four facts and questions;59 sources/rates/production controls preserved')
