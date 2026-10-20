"""Publish an own export policy/facts/questions bundle after gated code installation."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib, json
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
V = '2026.10.14-73-draft1'
def read(path): return json.loads(path.read_text(encoding='utf-8'))
def save(path, value):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
        stream.write('\n')

assert (E / 'export-fx-installation.json').exists()
assert read(ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014/combined-integration-development-validation.json')['full_suite_complete']
target = F / ('bundles/consumption-phase4b-' + V + '.json')
assert not target.exists()
bundle = read(F / 'bundles/consumption-phase4b-2026.10.14-70-draft1.json')
catalog = read(F / bundle['resource_paths']['rtc_code_catalog'])
policy = json.loads(json.dumps(catalog['ordinary_import_fx']))
known = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
policy.update(contract='RTC-ORDINARY-PARTIAL-EXPORT-FX-LOCAL-2026.10.14-73', published_at=known,
              scope='SYNTHETIC_COMPLETE_DIGITAL_PARTIAL_EXPORT_TRANSACTION_LOCAL_ONLY', production_ready=False)
primary = ROOT / 'storage/app/phase4b-fx67-preparation-20261013'
policy['legal_basis'] = {'cbs': 'REG_ART13_PAR4_AND_ART93', 'ibs': 'REG_ART13_PAR4_AND_ART93',
    'cbs_source_sha256': hashlib.sha256((primary / 'cbs-regulation.html').read_bytes()).hexdigest(),
    'ibs_source_sha256': hashlib.sha256((primary / 'ibs-regulation.pdf').read_bytes()).hexdigest(),
    'no_material_goods_import_rule': True, 'does_not_close_document_obligation': True}
policy.pop('source_hash')
policy['source_hash'] = digest(policy)
catalog['ordinary_partial_export_fx'] = policy
catalog['version'] = V
facts = read(F / bundle['resource_paths']['fact_definitions'])
questions = read(F / bundle['resource_paths']['questions'])
new_facts = [
    ('rtc.acquirer_foreign_nif', 'STRING', 'Qual identificação fiscal estrangeira do adquirente está vinculada à exportação?'),
    ('rtc.export_foreign_document_id', 'STRING', 'Qual documento da parcela estrangeira está vinculado à operação?'),
    ('rtc.export_transaction_evidence_id', 'STRING', 'Qual evidência vincula a transação às parcelas estrangeira e brasileira?'),
    ('rtc.export_fx_source_id', 'STRING', 'Qual fonte de cotação qualificada foi selecionada para converter o valor da exportação parcial?'),
    ('rtc.export_fx_proof', 'ARRAY', 'Qual prova local vincula moeda, conversão, partes, documentos, valores e alocação das parcelas?'),
]
rank = max(q['rank'] for q in questions)
for i, (key, kind, prompt) in enumerate(new_facts, 1):
    assert not any(f['key'] == key for f in facts)
    facts.append({'key': key, 'type': kind, 'material': True, **({'min_length': 1} if kind == 'STRING' else {})})
    questions.append({'fact_key': key, 'answer_type': kind, 'rank': rank + i, 'prompt': prompt})
for role, value, path in [
    ('rtc_code_catalog', catalog, 'catalogs/rtc-codes-' + V + '.json'),
    ('fact_definitions', facts, 'facts/consumption-phase4b-' + V + '.json'),
    ('questions', questions, 'questions/consumption-integrated-' + V + '.json'),
]:
    save(F / path, value)
    bundle['resource_paths'][role] = path
    bundle['content_hashes'][role] = digest(value)
controls = read(F / bundle['production_controls']['path'])
controls['version'] = V
control_path = 'production/phase4b-controls-' + V + '.json'
save(F / control_path, controls)
bundle['production_controls'] = {'path': control_path, 'sha256': digest(controls)}
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V,
              version=V, knowledge_available_at=known)
bundle['catalog_versions']['rtc_codes'] = V
save(target, bundle)
save(E / 'export-bundle-preparation.json', {'status': 'OWN_EXPORT_FX_BUNDLE_FACTS_QUESTIONS_AND_POLICY_PUBLISHED',
    'version': V, 'known_at': known, 'new_facts': [x[0] for x in new_facts],
    'qualified_material_origins': list(policy['qualified_quote_origins']), 'production_ready': False})
print('PASS:own export bundle with five separate facts/questions published; document obligation still pending')
