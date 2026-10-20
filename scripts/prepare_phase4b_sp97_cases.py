"""Build independent SP97 documentary cases; never installs into accepted96 runtime."""
from pathlib import Path
from copy import deepcopy
import hashlib
import json
import argparse

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document97-sp-primary-research-20261016-v2')
parser = argparse.ArgumentParser()
parser.add_argument('--v2', action='store_true')
options = parser.parse_args()
output = E / ('cases-v2.json' if options.v2 else 'cases.json')
assert not output.exists()
national = json.loads((ROOT / 'tests/Fixtures/iss-export95-cases.json').read_text(encoding='utf-8'))[0]

def digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

base = {'name': 'foreign-subject-no-br-link', 'context': deepcopy(national['context']),
        'national_policy': national['policy'], 'known_at': '2026-10-16T16:29:00+00:00',
        'policy': {'contract': 'SP-SOFTWARE-EXPORT-RESULT-LOCAL-2026.10.16-97',
            'source_sha256': '066427dc1da3c25fddd1628bc3c168d1805665e113b2d7c5bbb894825e20a274',
            'source_observed_at': '2026-10-16T16:28:22.913454+00:00',
            'scope': 'LOCAL_SP_ITEM1_RESULT_NOT_LEGAL_ACCEPTANCE'},
        'expected_status': 'LOCAL_SP_EXPORT_NON_INCIDENCE_CANDIDATE',
        'expected_reason': 'SP97_FOREIGN_SUBJECT_AND_NO_BR_SOFTWARE_LINK_BOUND'}
context = base['context']
value = {key: context[key] for key in ['operation_id', 'document_id', 'supplier_id', 'acquirer_id',
    'recipient_id', 'transaction_evidence_id', 'supply_on']}
value.update(national_result_proof_hash=context['result_proof']['source_hash'], supplier_establishment_municipality='3550308',
    supplier_establishment_evidence_reference='SYNTHETIC-SP-ESTABLISHMENT-97', service_list_item='1',
    result_subject_kind='INTANGIBLE_ELEMENT', result_subject_id='SYNTHETIC-FOREIGN-SOFTWARE-RESULT-97',
    result_subject_scope='ABROAD', result_subject_location_reference='SYNTHETIC-FOREIGN-SUBJECT-LOCATION-97',
    software_asset_kind='PROGRAM', software_asset_id='SYNTHETIC-SOFTWARE-ASSET-97', br_person_link='NO_BR_LINK',
    br_person_link_evidence_reference='SYNTHETIC-SOFTWARE-BR-LINK-ASSESSMENT-97', service_execution='SINGLE', monthly_stage=None)
base['proof'] = {'kind': 'SP_SOFTWARE_RESULT_MATERIAL_EVIDENCE', 'evidence_id': 'SYNTHETIC-MUNICIPAL-PROOF-97',
    'valid_from': '2026-09-01', 'valid_to': '2026-09-30', 'observed_at': '2026-10-16T16:28:30+00:00',
    'revoked_at': None, 'superseded_at': None, 'value': value}

def seal(case):
    if isinstance(case.get('proof'), dict):
        case['proof']['source_hash'] = digest({k: v for k, v in case['proof'].items() if k != 'source_hash'})

cases = []
def add(name, edit=lambda c: None, reason=None, status='ACTION_REQUIRED', reseal=True):
    case = deepcopy(base)
    case['name'] = name
    edit(case)
    if reason:
        case.update(expected_status=status, expected_reason='SP97_' + reason)
    if reseal: seal(case)
    cases.append(case)

seal(base)
add('foreign-subject-no-br-link')
add('foreign-subject-but-software-linked-to-br', lambda c: c['proof']['value'].update(br_person_link='HAS_BR_LINK'),
    'SOFTWARE_LINKED_TO_BR_PERSON', 'LOCAL_SP_EXPORT_EXCEPTION_BOUND')
def br_result(c):
    proof = c['context']['result_proof']
    proof['value']['result_scope'] = 'BR'
    proof['source_hash'] = digest({k: v for k, v in proof.items() if k != 'source_hash'})
    c['proof']['value'].update(result_subject_scope='BR', national_result_proof_hash=proof['source_hash'])
add('result-subject-in-br', br_result, 'RESULT_SUBJECT_IN_BR', 'LOCAL_SP_EXPORT_EXCEPTION_BOUND')
for subject in ['PERSON', 'TANGIBLE_ELEMENT', 'INTANGIBLE_ELEMENT', 'ECONOMIC_INTEREST']:
    for asset in ['SYSTEM', 'PROGRAM', 'DATABASE', 'EQUIPMENT']:
        add('subject-' + subject + '-asset-' + asset, lambda c, s=subject, a=asset: c['proof']['value'].update(result_subject_kind=s, software_asset_kind=a))
stage = {'period': '2026-09', 'stage_id': 'SYNTHETIC-SEPTEMBER-STAGE', 'completed_on': '2026-09-25',
         'completion_evidence_reference': 'SYNTHETIC-MONTHLY-COMPLETION-97'}
add('continuing-monthly-stage-without-final-delivery', lambda c: c['proof']['value'].update(service_execution='CONTINUING_MONTHLY', monthly_stage=stage))
add('policy-key-order-invariant', lambda c: c.update(policy=dict(reversed(list(c['policy'].items())))))
add('value-key-order-invariant', lambda c: c['proof'].update(value=dict(reversed(list(c['proof']['value'].items())))))
for key in base['policy']:
    add('policy-missing-' + key, lambda c, k=key: c['policy'].pop(k), 'MUNICIPAL_SOURCE_NOT_BOUND')
add('policy-extra-field', lambda c: c['policy'].update(extra=True), 'MUNICIPAL_SOURCE_NOT_BOUND')
add('source-not-observed', lambda c: c.update(known_at='2026-10-16T16:28:22.913453+00:00'), 'MUNICIPAL_SOURCE_NOT_YET_OBSERVED')
add('national-source-not-bound', lambda c: c['national_policy'].update(source_sha256='0' * 64), 'NATIONAL_RESULT_NOT_BOUND')
add('national-proof-missing', lambda c: c['context'].pop('result_proof'), 'NATIONAL_RESULT_NOT_BOUND')
for key in base['proof']:
    add('proof-missing-' + key, lambda c, k=key: c['proof'].pop(k),
        ('EXACT_MUNICIPAL_MATERIAL_PROOF_REQUIRED' if options.v2 else
            ('MUNICIPAL_PROOF_HASH_CONFLICT' if key == 'source_hash' else 'EXACT_MUNICIPAL_MATERIAL_PROOF_REQUIRED')), reseal=False)
for proof in [None, [], True, 'PAYMENT', 3]:
    add('proof-type-' + str(proof), lambda c, p=proof: c.update(proof=p), 'EXACT_MUNICIPAL_MATERIAL_PROOF_REQUIRED')
for kind in ['PAYMENT', 'RECIPIENT_DOMICILE', 'RTC_FOREIGN_CONSUMPTION', 'SERVICE_RESULT_MATERIAL_EVIDENCE']:
    add('substitute-' + kind, lambda c, k=kind: c['proof'].update(kind=k), 'EXACT_MUNICIPAL_MATERIAL_PROOF_REQUIRED')
add('municipal-proof-resigned-wrong-document', lambda c: c['proof']['value'].update(document_id='OTHER-DOC'), 'MUNICIPAL_OPERATION_DOCUMENT_PARTY_CONFLICT')
add('municipal-proof-unresigned-wrong-document', lambda c: c['proof']['value'].update(document_id='OTHER-DOC'), 'MUNICIPAL_PROOF_HASH_CONFLICT', reseal=False)
for key in value:
    add('value-missing-' + key, lambda c, k=key: c['proof']['value'].pop(k), 'EXACT_MUNICIPAL_VALUE_REQUIRED')
for key in ['operation_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'transaction_evidence_id', 'supply_on']:
    add('value-swap-' + key, lambda c, k=key: c['proof']['value'].update({k: 'OTHER'}), 'MUNICIPAL_OPERATION_DOCUMENT_PARTY_CONFLICT')
add('national-proof-link-conflict', lambda c: c['proof']['value'].update(national_result_proof_hash='0' * 64), 'NATIONAL_RESULT_PROOF_LINK_CONFLICT')
for key, val in [('supplier_establishment_municipality', '3304557'), ('supplier_establishment_municipality', 3550308), ('service_list_item', '17')]:
    add('jurisdiction-' + key + '-' + str(val), lambda c, k=key, v=val: c['proof']['value'].update({k: v}), 'SEPARATE_MUNICIPALITY_OR_SERVICE_REQUIRED')
for key in ['supplier_establishment_evidence_reference', 'result_subject_id', 'result_subject_location_reference', 'software_asset_id', 'br_person_link_evidence_reference']:
    for invalid in ['', None, True, ' \x00 ']:
        add('reference-' + key + '-' + repr(invalid), lambda c, k=key, v=invalid: c['proof']['value'].update({k: v}), 'INDEPENDENT_MUNICIPAL_DOCUMENTARY_REFERENCES_REQUIRED')
for key in ['result_subject_kind', 'software_asset_kind']:
    add('unsupported-' + key, lambda c, k=key: c['proof']['value'].update({k: 'OTHER'}), 'MUNICIPAL_SUBJECT_AND_SOFTWARE_ASSET_REQUIRED')
for scope in ['BR', 'MIXED', None]:
    add('scope-' + str(scope), lambda c, s=scope: c['proof']['value'].update(result_subject_scope=s), 'NATIONAL_MUNICIPAL_RESULT_SCOPE_CONFLICT')
for state in ['NOT_PROVEN', None, False, 'false', 'UNKNOWN']:
    add('br-link-' + str(state), lambda c, s=state: c['proof']['value'].update(br_person_link=s), 'SOFTWARE_BR_LINK_MUST_BE_DOCUMENTED')
add('single-with-stage', lambda c: c['proof']['value'].update(monthly_stage=stage), 'SEPARATE_EXECUTION_STAGE_REQUIRED')
add('execution-unmapped', lambda c: c['proof']['value'].update(service_execution='FINAL_INVOICE'), 'EXPLICIT_SERVICE_EXECUTION_REQUIRED')
for key in stage:
    def missing_stage(c, k=key):
        item = deepcopy(stage)
        item.pop(k)
        c['proof']['value'].update(service_execution='CONTINUING_MONTHLY', monthly_stage=item)
    add('stage-missing-' + key, missing_stage, 'DOCUMENTED_MONTHLY_STAGE_REQUIRED')
for key, val in [('period', '2026-10'), ('completed_on', '2026-09-24'), ('completed_on', '2026-02-30'), ('stage_id', ''), ('completion_evidence_reference', '')]:
    def wrong_stage(c, k=key, v=val):
        item = deepcopy(stage)
        item[k] = v
        c['proof']['value'].update(service_execution='CONTINUING_MONTHLY', monthly_stage=item)
    add('stage-invalid-' + key + '-' + val, wrong_stage, 'DOCUMENTED_MONTHLY_STAGE_REQUIRED')
for key, val in [('valid_from', '2026-09-26'), ('valid_to', '2026-09-24'), ('valid_from', '2026-02-30'), ('valid_to', None)]:
    add('proof-period-' + key + '-' + str(val), lambda c, k=key, v=val: c['proof'].update({k: v}), 'MUNICIPAL_PROOF_PERIOD_NOT_BOUND')
for val in ['2026-10-16T16:29:01+00:00', '2026-09-24T23:59:00-03:00', '2026-10-16T16:28:30Z',
            '2026-02-30T16:28:30+00:00', '2026-10-16T16:28:30+14:01', '2026-10-16T16:28:30-15:00']:
    add('proof-observed-' + val, lambda c, v=val: c['proof'].update(observed_at=v), 'MUNICIPAL_PROOF_OBSERVATION_NOT_BOUND')
for key in ['revoked_at', 'superseded_at']:
    add(key + '-invalid', lambda c, k=key: c['proof'].update({k: 'invalid'}), 'MUNICIPAL_PROOF_LIFECYCLE_INVALID')
    add(key + '-before-observation', lambda c, k=key: c['proof'].update({k: '2026-10-16T16:28:29+00:00'}), 'MUNICIPAL_PROOF_LIFECYCLE_INVALID')
    add(key + '-known', lambda c, k=key: c['proof'].update({k: '2026-10-16T16:29:00+00:00'}), 'MUNICIPAL_PROOF_NO_LONGER_CURRENT')
    add(key + '-future', lambda c, k=key: c['proof'].update({k: '2026-10-16T16:30:00+00:00'}))
assert len(cases) == len({c['name'] for c in cases})
with output.open('x', encoding='utf-8') as stream:
    json.dump(cases, stream, ensure_ascii=False, indent=2)
print(json.dumps({'cases': len(cases), 'runtime_integrated': False}))
