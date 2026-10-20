"""Independent expected outcomes for per-document ISS result binding; all data explicitly synthetic."""
from pathlib import Path
from copy import deepcopy
import hashlib
import json

E = Path(r'D:\ProjetoSaasExports\phase4b-iss-export95-preparation-20261016')
policy = {'contract': 'ISS-SOFTWARE-EXPORT-RESULT-LOCAL-2026.10.16-95',
          'source_sha256': '3ae4d3fc894ced12874364e366867b736c81369352cbaf97c70d57e534cdf86c',
          'source_observed_at': '2026-10-16T14:16:16.333043+00:00',
          'scope': 'LOCAL_SOFTWARE_RESULT_EVIDENCE_NOT_LEGAL_ACCEPTANCE'}
context = {'operation_id': 'OP-95', 'document_id': 'FOREIGN-DPS-95', 'supplier_id': '11111111000191',
           'acquirer_id': 'FOREIGN-ACQUIRER-95', 'recipient_id': 'FOREIGN-RECIPIENT-95',
           'transaction_evidence_id': 'SYNTHETIC-CONTRACT-95', 'service_kind': 'SOFTWARE_SERVICE',
           'supplier_country': 'BR', 'performed_in': 'BR', 'supply_on': '2026-09-25',
           'special_context': False, 'result_proof': None}
value = {k: v for k, v in context.items() if k not in ['supplier_country', 'special_context', 'result_proof']}
value.update(result_scope='ABROAD', result_description='SYNTHETIC: deployed software outcome used exclusively outside Brazil',
             result_evidence_reference='SYNTHETIC-RESULT-LOCATION-RECORD-95')
context['result_proof'] = {'kind': 'SERVICE_RESULT_MATERIAL_EVIDENCE', 'evidence_id': 'SYNTHETIC-ISS-RESULT-95',
    'valid_from': '2026-09-01', 'valid_to': '2026-09-30', 'observed_at': '2026-10-16T14:00:00+00:00',
    'revoked_at': None, 'superseded_at': None, 'value': value, 'source_hash': None}

def bind_hash(doc):
    proof = doc['result_proof']
    if isinstance(proof, dict):
        material = {k: v for k, v in proof.items() if k != 'source_hash'}
        proof['source_hash'] = hashlib.sha256(json.dumps(material, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

bind_hash(context)
cases = []

def case(name, mutate=lambda d: None, reason=None, *, expected='ABROAD', known='2026-10-16T14:17:00+00:00', policy_changes=None, rehash=True):
    doc = deepcopy(context)
    mutate(doc)
    if rehash:
        bind_hash(doc)
    status = 'ACTION_REQUIRED' if reason else ('LOCAL_ISS_EXPORT_NON_INCIDENCE_CANDIDATE' if expected == 'ABROAD' else 'LOCAL_ISS_EXPORT_EXCEPTION_BOUND')
    why = reason or ('RESULT_ABROAD_LOCALLY_BOUND' if expected == 'ABROAD' else 'RESULT_IN_BR_NOT_EXPORT_NON_INCIDENCE')
    cases.append({'name': name, 'context': doc, 'policy': {**policy, **(policy_changes or {})}, 'known_at': known,
                  'expected_status': status, 'expected_reason': 'ISS_EXPORT95_' + why,
                  'expected_result_scope': None if reason else expected})

case('independent-foreign-result')
case('foreign-payer-but-result-br', lambda d: d['result_proof']['value'].update(result_scope='BR'), expected='BR')

def identifiers_zero(d):
    d['document_id'] = '0'
    d['result_proof']['value']['document_id'] = '0'
case('zero-id-not-discarded', identifiers_zero)
for key in policy:
    case('unbound-policy-' + key, reason='SOURCE_CONTRACT_NOT_BOUND', policy_changes={key: 'wrong'})
case('source-not-observed', reason='SOURCE_NOT_YET_OBSERVED', known='2026-10-16T14:16:16.333042+00:00')
case('source-observation-inclusive', known='2026-10-16T14:16:16.333043+00:00')
for key in context:
    case('missing-context-' + key, lambda d, k=key: d.pop(k), 'EXPLICIT_CONTEXT_REQUIRED', rehash=key != 'result_proof')
case('extra-rtc-share-not-iss-proof', lambda d: d.update(rtc_foreign_share='40'), 'UNMAPPED_CONTEXT_FIELDS')
for key in ['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'transaction_evidence_id']:
    case('blank-id-' + key, lambda d, k=key: d.update({k: ' '}), 'OPERATION_DOCUMENT_PARTIES_REQUIRED')
case('nul-document-id', lambda d: d.update(document_id='X\0Y'), 'OPERATION_DOCUMENT_PARTIES_REQUIRED')
for key, bad in [('service_kind', 'GOODS'), ('supplier_country', 'US'), ('performed_in', 'US'), ('special_context', True), ('special_context', 'false')]:
    case('separate-' + key + '-' + str(bad), lambda d, k=key, v=bad: d.update({k: v}), 'SEPARATE_SERVICE_OR_SPECIAL_CONTEXT_REQUIRED')
for label, bad in [('calendar', '2026-02-30'), ('timestamp', '2026-09-25T00:00:00Z'), ('number', 20260925), ('null', None)]:
    case('date-' + label, lambda d, v=bad: d.update(supply_on=v), 'EXACT_SERVICE_DATE_REQUIRED')
for bad in ['2025-12-31', '2033-01-01']:
    case('period-' + bad, lambda d, v=bad: d.update(supply_on=v), 'LOCAL_PERIOD_NOT_COVERED')
case('future-service', lambda d: d.update(supply_on='2026-10-17'), 'SERVICE_DATE_NOT_REACHED')
for label, bad in [('null', None), ('list', []), ('string', 'proof'), ('number', 1), ('boolean', True)]:
    case('proof-' + label, lambda d, v=bad: d.update(result_proof=v), 'RESULT_PROOF_REQUIRED', rehash=False)
for key in context['result_proof']:
    case('missing-proof-' + key, lambda d, k=key: d['result_proof'].pop(k), 'EXACT_RESULT_PROOF_CONTRACT_REQUIRED', rehash=False)
case('extra-proof-field', lambda d: d['result_proof'].update(qualified=True), 'EXACT_RESULT_PROOF_CONTRACT_REQUIRED')
for kind in ['PAYMENT', 'RECIPIENT_DOMICILE', 'RTC_FOREIGN_CONSUMPTION']:
    case('substitute-' + kind, lambda d, v=kind: d['result_proof'].update(kind=v), 'RESULT_NOT_PAYMENT_DOMICILE_OR_RTC_CONSUMPTION_REQUIRED')
case('blank-evidence', lambda d: d['result_proof'].update(evidence_id=''), 'RESULT_NOT_PAYMENT_DOMICILE_OR_RTC_CONSUMPTION_REQUIRED')
case('stale-hash', lambda d: d['result_proof']['value'].update(result_scope='BR'), 'RESULT_PROOF_HASH_CONFLICT', rehash=False)
case('malformed-hash', lambda d: d['result_proof'].update(source_hash='x'), 'RESULT_PROOF_HASH_CONFLICT', rehash=False)
for key, bad in [('valid_from', '2026-02-30'), ('valid_to', None), ('valid_from', '2026-10-01'), ('valid_to', '2026-09-24')]:
    case('proof-period-' + key + '-' + str(bad), lambda d, k=key, v=bad: d['result_proof'].update({k: v}), 'RESULT_PROOF_PERIOD_NOT_BOUND')
for label, bad in [('future', '2026-10-17T00:00:00+00:00'), ('before-service', '2026-09-24T23:00:00-03:00'),
                   ('invalid-date', '2026-02-30T14:00:00+00:00'), ('no-offset', '2026-10-16T14:00:00'),
                   ('offset15', '2026-10-16T14:00:00+15:00'), ('offset14minute', '2026-10-16T14:00:00+14:01')]:
    case('proof-observation-' + label, lambda d, v=bad: d['result_proof'].update(observed_at=v), 'RESULT_PROOF_OBSERVATION_NOT_BOUND')
case('offset14-valid-instant', lambda d: d['result_proof'].update(observed_at='2026-10-17T04:00:00+14:00'))
case('offset-minus14-valid-instant', lambda d: d['result_proof'].update(observed_at='2026-10-16T00:00:00-14:00'))
for key in ['revoked_at', 'superseded_at']:
    case('ended-' + key, lambda d, k=key: d['result_proof'].update({k: '2026-10-16T14:17:00+00:00'}), 'RESULT_PROOF_NO_LONGER_CURRENT')
    case('future-end-' + key, lambda d, k=key: d['result_proof'].update({k: '2026-10-17T00:00:00+00:00'}))
    case('bad-end-' + key, lambda d, k=key: d['result_proof'].update({k: '2026-02-30T14:00:00+00:00'}), 'RESULT_PROOF_LIFECYCLE_INVALID')
    case('end-before-observation-' + key, lambda d, k=key: d['result_proof'].update({k: '2026-10-16T13:59:59+00:00'}), 'RESULT_PROOF_LIFECYCLE_INVALID')
for label, bad in [('null', None), ('list', []), ('string', 'value')]:
    case('value-' + label, lambda d, v=bad: d['result_proof'].update(value=v), 'RESULT_PROOF_VALUE_CONTRACT_REQUIRED')
for key in value:
    case('missing-value-' + key, lambda d, k=key: d['result_proof']['value'].pop(k), 'RESULT_PROOF_VALUE_CONTRACT_REQUIRED')
case('extra-value-field', lambda d: d['result_proof']['value'].update(assume_export=True), 'RESULT_PROOF_VALUE_CONTRACT_REQUIRED')
for key in ['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'transaction_evidence_id', 'supply_on', 'service_kind', 'performed_in']:
    case('bound-value-' + key, lambda d, k=key: d['result_proof']['value'].update({k: 'OTHER'}), 'RESULT_OPERATION_DOCUMENT_PARTY_BINDING_CONFLICT')
for key in ['result_description', 'result_evidence_reference']:
    case('missing-material-' + key, lambda d, k=key: d['result_proof']['value'].update({k: ' '}), 'INDEPENDENT_RESULT_DESCRIPTION_AND_EVIDENCE_REQUIRED')
for scope in ['MIXED', 'UNKNOWN', None, True]:
    case('result-scope-' + str(scope), lambda d, v=scope: d['result_proof']['value'].update(result_scope=v), 'INDIVIDUAL_DOCUMENT_RESULT_SCOPE_REQUIRED')

def domestic_sibling(d):
    d['document_id'] = 'DOMESTIC-DPS-95'
    d['recipient_id'] = 'DOMESTIC-RECIPIENT-95'
    d['result_proof']['value'].update(document_id=d['document_id'], recipient_id=d['recipient_id'], result_scope='BR')
case('qualified-domestic-sibling', domestic_sibling, expected='BR')
case('foreign-proof-swapped-onto-sibling', lambda d: d.update(document_id='DOMESTIC-DPS-95'), 'RESULT_OPERATION_DOCUMENT_PARTY_BINDING_CONFLICT')
case('no-tax-ratio-inference', lambda d: d['result_proof']['value'].update(rtc_tax_share='40'), 'RESULT_PROOF_VALUE_CONTRACT_REQUIRED')
assert len({c['name'] for c in cases}) == len(cases)
with (E / 'cases.json').open('x', encoding='utf-8') as stream:
    json.dump(cases, stream, ensure_ascii=False, indent=2)
print('Prepared', len(cases), 'synthetic native ISS result-binding cases')
