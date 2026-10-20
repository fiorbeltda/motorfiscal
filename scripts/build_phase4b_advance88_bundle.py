"""Exclusive versioned resources for advance origin; no future rates or production approval."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-advance88-preparation-20261015'
V = '2026.10.15-88-draft1'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)

assert (E / 'before-integration-archive.json').exists()
bundle = read(F / 'bundles/consumption-phase4b-2026.10.15-86-draft1.json')
transition = read(F / bundle['resource_paths']['rtc_transition_catalog'])
facts = read(F / bundle['resource_paths']['fact_definitions'])
questions = read(F / bundle['resource_paths']['questions'])
controls = read(F / bundle['production_controls']['path'])
paths = [F / f'{folder}/{name}-{V}.json' for folder, name in [
    ('catalogs', 'rtc-transition'), ('facts', 'consumption-phase4b'),
    ('questions', 'consumption-integrated'), ('production', 'phase4b-controls'),
    ('bundles', 'consumption-phase4b')]]
receipt = E / 'advance88-bundle-installation.json'
assert not any(p.exists() for p in [*paths, receipt])
transition['version'] = V
transition['tax_point_event_policy']['advance_origin_contract'] = 'RTC-ADVANCE-ORIGIN-2026.10.15-88'
transition['tax_point_event_policy']['scope'] += ';ADVANCE_ORIGIN_LOCAL_NOT_REGISTERED_OR_DOCUMENT_SETTLED'
rank = max(q['rank'] for q in questions)
for index, (key, kind, prompt) in enumerate([
    ('rtc.assess_advance_origin', 'BOOLEAN', 'Qualificar pagamento antecipado antes do fornecimento?'),
    ('rtc.advance_origin_contexts', 'ARRAY', 'Informe a operação, parcela paga e provas vinculadas, sem presumir fornecimento concluído.')], 1):
    assert not any(row['key'] == key for row in facts)
    facts.append({'key': key, 'type': kind, 'material': True})
    questions.append({'fact_key': key, 'answer_type': kind, 'rank': rank + index, 'prompt': prompt})
controls['version'] = V
row = next(r for r in controls['controls'] if r['id'] == '4B.1.TEMPORAL_EVENT_SETTLEMENT')
row['gap'] += ' Origem88: cálculo nominal local anterior ao fornecimento; registro e liquidação ainda não comprovados.'
for role, value, path in zip(['rtc_transition_catalog', 'fact_definitions', 'questions'], [transition, facts, questions], paths[:3]):
    save(path, value)
    bundle['resource_paths'][role] = path.relative_to(F).as_posix()
    bundle['content_hashes'][role] = canonical(value)
save(paths[3], controls)
bundle['production_controls'] = {'path': paths[3].relative_to(F).as_posix(), 'sha256': canonical(controls)}
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V, version=V,
              knowledge_available_at=datetime.now(timezone.utc).replace(microsecond=0).isoformat())
bundle['catalog_versions']['rtc_transition'] = V
save(paths[4], bundle)
save(receipt, {'status': 'ADVANCE88_RESOURCES_CREATED_VALIDATION_PENDING', 'production_ready': False,
    'full_suite_complete': False, 'files': {p.relative_to(ROOT).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in paths},
    'official_sources': len(bundle['official_sources']), 'controls': len(controls['controls']),
    'rate_catalog_unchanged': bundle['content_hashes']['rtc_rate_catalog']})
print('PASS: own88 bundle,2 new facts/questions; predecessor sources, rates and controls preserved')
