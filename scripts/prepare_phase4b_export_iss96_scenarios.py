"""Own96 scenarios retain native73 money and independently declared ISS result proofs; no XML approval."""
from pathlib import Path
from datetime import datetime, timezone
from copy import deepcopy
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-integration-20261016')
V = '2026.10.16-96-draft1'
BUNDLE = 'bundles/consumption-phase4b-' + V + '.json'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
bundle = read(F / BUNDLE)
old = read(ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014/export-integrated-case-manifest.json')
templates = read(Path(r'D:\ProjetoSaasExports\phase4b-iss-export95-pair-binding-20261016\cases.json'))
folder = F / 'scenarios' / ('export-iss-' + V)
folder.mkdir(exist_ok=False)
known = datetime.now(timezone.utc).isoformat()

def rehash(doc):
    for row in doc['facts'].get('rtc.export_iss_result_contexts', []):
        proof = row['iss_context']['result_proof']
        proof['source_hash'] = canonical({k: v for k, v in proof.items() if k != 'source_hash'})

def scenario(name, original='usd-direct'):
    row = next(c for c in old['cases'] if c['name'] == original)
    doc = read(ROOT / row['path'])
    doc.update(scenario_id='SYNTHETIC-EXPORT-ISS96-' + name, knowledge_at=known,
               rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'])
    facts = doc['facts']
    if original == 'brl-control':
        facts.update({'rtc.export_foreign_document_id': 'SYNTHETIC-FOREIGN-BRL-96',
                      'rtc.export_transaction_evidence_id': 'SYNTHETIC-TRANSACTION-BRL-96',
                      'rtc.acquirer_foreign_nif': 'SYNTHETIC-NIF-BRL-96'})
    facts['rtc.assess_export_iss_results'] = True
    facts['rtc.export_operation_reference'] = doc['scenario_id']
    records = deepcopy(templates[0]['records'])
    for record in records:
        context = record['iss_context']
        foreign = record['role'] == 'RTC_FOREIGN'
        context.update(operation_id=doc['scenario_id'],
            document_id=facts['rtc.export_foreign_document_id' if foreign else 'rtc.export_domestic_document_id'],
            supplier_id=facts['rtc.supplier_id'], acquirer_id=facts['rtc.acquirer_id'],
            recipient_id=facts['rtc.recipient_id' if foreign else 'rtc.export_domestic_recipient_id'],
            transaction_evidence_id=facts['rtc.export_transaction_evidence_id'], supply_on=doc['competence_date'])
        value = context['result_proof']['value']
        for key in ['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'transaction_evidence_id', 'supply_on']:
            value[key] = context[key]
        context['result_proof']['evidence_id'] = 'SYNTHETIC-ISS96-RESULT-' + name + '-' + record['role']
    facts['rtc.export_iss_result_contexts'] = records
    rehash(doc)
    return doc, row['independent_decimal']

cases = []
def add(name, mutate=lambda d: None, *, reason='RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN', original='usd-direct', bound=True, rehash_proof=True):
    doc, decimal = scenario(name, original)
    mutate(doc)
    if rehash_proof: rehash(doc)
    path = folder / (name + '.json')
    save(path, doc)
    cases.append({'name': name, 'path': path.relative_to(ROOT).as_posix(), 'reason': reason, 'expected_bound': bound,
                  'independent_decimal': decimal if original != 'brl-control' or 'future' not in name else None})

for original in ['usd-direct', 'usd-weekend', 'usd-quarter', 'usd-mostly-foreign', 'usd-mostly-domestic', 'brl-control']:
    add(original, original=original)
add('no-operation-reference', lambda d: d['facts'].pop('rtc.export_operation_reference'), reason='RTC_EXPORT_ISS96_OPERATION_DOCUMENT_PARTIES_REQUIRED', bound=False)
add('no-contexts', lambda d: d['facts'].pop('rtc.export_iss_result_contexts'), reason='RTC_EXPORT_ISS96_TWO_INDEPENDENT_RESULT_CONTEXTS_REQUIRED', bound=False)
add('duplicate-role', lambda d: d['facts']['rtc.export_iss_result_contexts'][1].update(role='RTC_FOREIGN'), reason='RTC_EXPORT_ISS96_DUPLICATE_RESULT_ROLE', bound=False)
add('wrong-operation-proof', lambda d: d['facts']['rtc.export_iss_result_contexts'][0]['iss_context'].update(operation_id='OTHER'), reason='RTC_EXPORT_ISS96_RESULT_CONTEXT_SOURCE_BINDING_CONFLICT', bound=False)
add('swapped-document-proof', lambda d: d['facts']['rtc.export_iss_result_contexts'][0].update(iss_context=deepcopy(d['facts']['rtc.export_iss_result_contexts'][1]['iss_context'])), reason='RTC_EXPORT_ISS96_RESULT_CONTEXT_SOURCE_BINDING_CONFLICT', bound=False)
add('proof-hash', lambda d: d['facts']['rtc.export_iss_result_contexts'][0]['iss_context']['result_proof']['value'].update(result_scope='BR'), reason='RTC_EXPORT_ISS96_INDEPENDENT_ISS_RESULT_NOT_BOUND', bound=False, rehash_proof=False)
add('payment-not-result', lambda d: d['facts']['rtc.export_iss_result_contexts'][0]['iss_context']['result_proof'].update(kind='PAYMENT'), reason='RTC_EXPORT_ISS96_INDEPENDENT_ISS_RESULT_NOT_BOUND', bound=False)
add('mixed-result', lambda d: d['facts']['rtc.export_iss_result_contexts'][0]['iss_context']['result_proof']['value'].update(result_scope='MIXED'), reason='RTC_EXPORT_ISS96_INDEPENDENT_ISS_RESULT_NOT_BOUND', bound=False)
add('foreign-rtc-iss-br', lambda d: d['facts']['rtc.export_iss_result_contexts'][0]['iss_context']['result_proof']['value'].update(result_scope='BR'))
add('domestic-rtc-iss-abroad', lambda d: d['facts']['rtc.export_iss_result_contexts'][1]['iss_context']['result_proof']['value'].update(result_scope='ABROAD'))
add('wrong-route', lambda d: d['facts'].update({'rtc.market': 'DOMESTIC'}), reason='RTC_EXPORT_ISS96_PARTIAL_EXPORT_ROUTE_REQUIRED', bound=False)
add('full-export-not-partial', lambda d: d['facts'].update({'rtc.export_share_percent': '100'}), reason='RTC_EXPORT_ISS96_PARTIAL_EXPORT_ROUTE_REQUIRED', bound=False)
add('advance-combination', lambda d: d['facts'].update({'rtc.assess_advance_origin': True}), reason='RTC_EXPORT_ISS96_SEPARATE_TEMPORAL_CONTEXT_REQUIRED', bound=False)
add('disabled-malformed-contexts', lambda d: d['facts'].update({'rtc.assess_export_iss_results': False, 'rtc.export_iss_result_contexts': []}), bound=False)
def future(d):
    d.update(competence_date='2027-01-01', knowledge_at='2027-01-02T12:00:00+00:00')
    d['facts']['rtc.operation_date'] = '2027-01-01'
add('future-cbs-not-guessed', future, reason='RTC_PARTIAL_EXPORT_DOMESTIC_CLASSIFICATION_NOT_PROVEN', original='brl-control', bound=False)
save(E / 'export-iss96-case-manifest.json', {'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': BUNDLE,
    'cases': cases, 'new_facts': ['rtc.assess_export_iss_results', 'rtc.export_operation_reference', 'rtc.export_iss_result_contexts'],
    'full_suite_complete': False, 'production_ready': False})
print('Prepared', len(cases), 'own96 scenarios;old scenarios untouched', flush=True)
