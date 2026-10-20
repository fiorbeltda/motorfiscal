"""Build source-bound pair cases from immutable native73 captures; no changes to runtime/regression inputs."""
from pathlib import Path
from copy import deepcopy
import hashlib
import json
import shutil

E = Path(r'D:\ProjetoSaasExports\phase4b-iss-export95-pair-binding-20261016')
S = Path(r'D:\ProjetoSaasExports\phase4b-document94-pair-binding-gap-20261016')
Q = Path(r'D:\ProjetoSaasExports\phase4b-iss-export95-preparation-20261016')

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def canonical_hash(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

def proof_hash(context):
    proof = context['result_proof']
    material = {k: v for k, v in proof.items() if k != 'source_hash'}
    proof['source_hash'] = canonical_hash(material)

sources = read(S / 'document94-native-pair-binding-gap.json')['cases']
assert len(sources) == 6
(E / 'sources').mkdir(exist_ok=False)
source_index = []
snapshots = {}
for source in sources:
    name = source['name']
    native = source['native_captures']['rtc']
    file = S / native['snapshot_file']
    assert hashlib.sha256(file.read_bytes()).hexdigest() == native['sha256']
    shutil.copy2(file, E / 'sources' / (name + '.json'))
    snapshots[name] = read(file)
    source_index.append({'name': name, 'file': 'sources/' + name + '.json', 'sha256': native['sha256']})
policy = {**read(Q / 'cases.json')[0]['policy'],
          'pair_binding_contract': 'ISS-PARTIAL-EXPORT-PAIR-RESULT-BINDING-LOCAL-2026.10.16-95'}
template = read(Q / 'cases.json')[0]['context']

def records_for(name, updates):
    source = deepcopy(snapshots[name]['input'])
    source['facts'].update(updates)
    facts = source['facts']
    result = []
    for role, doc_key, recipient_key, scope in [
        ('RTC_FOREIGN', 'rtc.export_foreign_document_id', 'rtc.recipient_id', 'ABROAD'),
        ('RTC_DOMESTIC', 'rtc.export_domestic_document_id', 'rtc.export_domestic_recipient_id', 'BR')
    ]:
        context = deepcopy(template)
        context.update(operation_id=source['scenario_id'], document_id=facts.get(doc_key), supplier_id=facts['rtc.supplier_id'],
                       acquirer_id=facts['rtc.acquirer_id'], recipient_id=facts[recipient_key],
                       transaction_evidence_id=facts.get('rtc.export_transaction_evidence_id'), supply_on=source['competence_date'])
        proof = context['result_proof']
        proof['evidence_id'] = 'SYNTHETIC-INDEPENDENT-ISS-RESULT-' + name + '-' + role
        value = proof['value']
        for key in ['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'transaction_evidence_id', 'supply_on']:
            value[key] = context[key]
        value.update(result_scope=scope, result_description='SYNTHETIC independent material result for ' + role,
                     result_evidence_reference='SYNTHETIC-RESULT-RECORD-' + name + '-' + role)
        proof_hash(context)
        result.append({'role': role, 'iss_context': context})
    return result

cases = []

def case(name, mutate=lambda rows: None, reason=None, *, source='usd-direct', scopes=None, updates=None,
         source_mutation=None, policy_changes=None, known='2026-10-16T14:20:00+00:00', empty_trust=False, rehash=True):
    updates = updates or {}
    records = records_for(source, updates)
    mutate(records)
    if rehash and isinstance(records, list):
        for record in records:
            if isinstance(record, dict) and isinstance(record.get('iss_context'), dict) and isinstance(record['iss_context'].get('result_proof'), dict):
                proof_hash(record['iss_context'])
    cases.append({'name': name, 'source_case': source, 'records': records, 'policy': {**policy, **(policy_changes or {})},
                  'known_at': known, 'native_input_updates': updates, 'source_mutation': source_mutation, 'empty_trusted_knowledge': empty_trust,
                  'expected_status': 'ACTION_REQUIRED' if reason else 'LOCAL_INDEPENDENT_ISS_PAIR_RESULTS_BOUND',
                  'expected_reason': 'ISS_PAIR95_' + (reason or 'DOCUMENT_RESULTS_BOUND_NOT_DOCUMENTS_QUALIFIED'),
                  'expected_scopes': None if reason else (scopes or ['ABROAD', 'BR'])})

for source in sources:
    name = source['name']
    case('native-' + name, reason='SOURCE_DOCUMENT_AND_PARTY_IDENTIFIERS_REQUIRED' if name == 'brl-control' else None, source=name)
case('record-order-not-tax-semantics', lambda rows: rows.reverse())
case('foreign-rtc-can-have-iss-result-br', lambda rows: rows[0]['iss_context']['result_proof']['value'].update(result_scope='BR'), scopes=['BR', 'BR'])
case('domestic-rtc-result-not-forced-br', lambda rows: rows[1]['iss_context']['result_proof']['value'].update(result_scope='ABROAD'), scopes=['ABROAD', 'ABROAD'])
case('one-record', lambda rows: rows.pop(), 'EXACTLY_TWO_INDEPENDENT_DOCUMENT_CONTEXTS_REQUIRED')
case('three-records', lambda rows: rows.append(deepcopy(rows[0])), 'EXACTLY_TWO_INDEPENDENT_DOCUMENT_CONTEXTS_REQUIRED')
case('duplicate-role', lambda rows: rows[1].update(role='RTC_FOREIGN'), 'DUPLICATE_DOCUMENT_ROLE')
case('unknown-role', lambda rows: rows[0].update(role='GLOBAL_ISS'), 'DOCUMENT_ROLE_CONTEXT_CONTRACT_REQUIRED')
case('extra-record-field', lambda rows: rows[0].update(tax_amount='0.00'), 'DOCUMENT_ROLE_CONTEXT_CONTRACT_REQUIRED')
case('missing-record-role', lambda rows: rows[0].pop('role'), 'DOCUMENT_ROLE_CONTEXT_CONTRACT_REQUIRED')
case('non-array-context', lambda rows: rows[0].update(iss_context='proof'), 'DOCUMENT_ROLE_CONTEXT_CONTRACT_REQUIRED')
case('swapped-role-contexts', lambda rows: rows[0].update(iss_context=deepcopy(rows[1]['iss_context'])), 'DOCUMENT_CONTEXT_NOT_BOUND_TO_SOURCE')
for index, role in [(0, 'foreign'), (1, 'domestic')]:
    for key in ['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'transaction_evidence_id', 'service_kind', 'supply_on', 'supplier_country']:
        case(role + '-wrong-' + key, lambda rows, i=index, k=key: rows[i]['iss_context'].update({k: 'OTHER'}), 'DOCUMENT_CONTEXT_NOT_BOUND_TO_SOURCE')
    case(role + '-mixed-result', lambda rows, i=index: rows[i]['iss_context']['result_proof']['value'].update(result_scope='MIXED'), 'INDEPENDENT_ISS_RESULT_NOT_BOUND')
    case(role + '-payment-not-result', lambda rows, i=index: rows[i]['iss_context']['result_proof'].update(kind='PAYMENT'), 'INDEPENDENT_ISS_RESULT_NOT_BOUND')
    case(role + '-stale-proof-hash', lambda rows, i=index: rows[i]['iss_context']['result_proof']['value'].update(result_scope='BR' if i == 0 else 'ABROAD'), 'INDEPENDENT_ISS_RESULT_NOT_BOUND', rehash=False)
    case(role + '-revoked-result', lambda rows, i=index: rows[i]['iss_context']['result_proof'].update(revoked_at='2026-10-16T14:19:00+00:00'), 'INDEPENDENT_ISS_RESULT_NOT_BOUND')
    case(role + '-performed-outside-local-scope', lambda rows, i=index: rows[i]['iss_context'].update(performed_in='US'), 'INDEPENDENT_ISS_RESULT_NOT_BOUND')
for mutation, reason in [('BODY', 'SOURCE_NATIVE_REPLAY_MISMATCH'), ('RESIGNED_BODY', 'SOURCE_NATIVE_REPLAY_MISMATCH'),
                         ('KNOWLEDGE', 'SOURCE_KNOWLEDGE_NOT_TRUSTED'), ('FUTURE', 'SOURCE_KNOWLEDGE_IN_FUTURE'),
                         ('EMPTY', 'SOURCE_SNAPSHOT_REQUIRED')]:
    case('source-' + mutation.lower(), reason=reason, source_mutation=mutation)
case('untrusted-empty-knowledge', reason='SOURCE_KNOWLEDGE_NOT_TRUSTED', empty_trust=True)
case('wrong-pair-contract', reason='POLICY_NOT_BOUND', policy_changes={'pair_binding_contract': 'wrong'})
case('wrong-result-source', reason='POLICY_NOT_BOUND', policy_changes={'source_sha256': '0' * 64})
case('not-known-at-source-observation', reason='SOURCE_NOT_YET_OBSERVED', known='2026-10-16T14:16:16.333042+00:00')
case('parent-may-name-foreign-child', updates={'rtc.document_id': 'foreign-invoice-73'})
case('two-child-documents-must-differ', reason='SOURCE_DOCUMENT_REFERENCE_ALIAS', updates={'rtc.export_domestic_document_id': 'foreign-invoice-73'})
case('brl-with-new-explicit-references-not-old-corpus-acceptance', source='brl-control', updates={
    'rtc.export_foreign_document_id': 'SYNTHETIC-FOREIGN-BRL-95', 'rtc.export_transaction_evidence_id': 'SYNTHETIC-TRANSACTION-BRL-95',
    'rtc.acquirer_foreign_nif': 'SYNTHETIC-NIF-BRL-95'})
case('native-source-no-longer-export', reason='QUALIFIED_PARTIAL_EXPORT_SOURCE_REQUIRED', updates={'rtc.market': 'DOMESTIC'})
assert len({c['name'] for c in cases}) == len(cases)
for name, value in [('source-index.json', source_index), ('cases.json', cases)]:
    with (E / name).open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
print('Prepared', len(cases), 'pair-binding cases from six immutable native captures')
