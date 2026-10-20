"""Exclusive advance fixtures with predeclared expected gates and unchanged ISS competence/value."""
from pathlib import Path
from copy import deepcopy
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-advance88-preparation-20261015'
S = E / 'scenarios'
M = E / 'advance88-case-manifest.json'
assert not S.exists() and not M.exists()
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def save(p, value):
    with p.open('x', encoding='utf-8') as f: json.dump(value, f, ensure_ascii=False, indent=2)
bundle = read(ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.15-88-draft1.json')
base = read(ROOT / 'storage/app/phase4b-temporal86-integration-preparation-20261015/scenarios-v2/opt-in-absent-unchanged.json')
base.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'])
fixture = read(E / 'advance-origin-cases.json')
cases = {c['name']: c for c in fixture['cases']}
S.mkdir()
rows = []
normal = 'RTC_ADVANCE_REGISTRATION_AND_DOCUMENT_OBLIGATION_NOT_PROVEN'
def scenario(name):
    c = deepcopy(cases[name])
    # Bind synthetic advance evidence to the same supplier as the real runner fixture.
    context = json.loads(json.dumps(c['context']).replace('SUP87', base['facts']['rtc.supplier_id']))
    selected = next(p for p in context['paid_installments'] if p['id'] == c['selection'])
    p = deepcopy(base)
    p['knowledge_at'] = c['known_at']
    p['facts'].update({'rtc.assess_advance_origin': True, 'rtc.advance_origin_contexts': [context],
        'rtc.tax_point_operation_id': context['operation_id'], 'rtc.tax_point_installment_id': c['selection'],
        'rtc.gross_amount': selected['amount'], 'rtc.operation_date': selected['payment']['date'],
        'rtc.document_id': (selected.get('payment_document') or {}).get('document_id', 'NO-ADVANCE-DOCUMENT')})
    return p
def add(name, p, reason=normal, nominal=None):
    p['scenario_id'] = 'SYNTHETIC-ADVANCE88-' + name
    target = S / (name + '.json')
    save(target, p)
    rows.append({'case': name, 'path': target.relative_to(ROOT).as_posix(), 'status': 'ACTION_REQUIRED',
                 'reason': reason, 'expected_nominal': nominal})
for name in ['past-payment-without-completed-supply', 'text-id-0', 'text-id-01', 'text-id-0001',
             'payment-before-document', 'multiple-selected', 'multiple-permuted',
             '2027-payment-linked-to-2026-document-not-cash-relief']:
    add(name, scenario(name), nominal=cases[name]['expected'])
add('explicit-no-document', scenario('explicit-no-document'), 'RTC_ADVANCE_FISCAL_DOCUMENT_BINDING_REQUIRED')
p = scenario('past-payment-without-completed-supply')
p['knowledge_at'] = '2027-01-10T23:00:00+00:00'
p['facts']['rtc.operation_date'] = '2027-01-08'
ctx = p['facts']['rtc.advance_origin_contexts'][0]
ctx['supply_state_evidence'].update(date='2027-01-09', observed_on='2027-01-09')
ctx['paid_installments'][0]['payment'].update(date='2027-01-08', observed_on='2027-01-08')
ctx['paid_installments'][0]['payment_document'].update(date='2027-01-07', observed_on='2027-01-07')
add('2027-document-no-cbs-fallback', p, 'RTC_RATE_NOT_PROVEN:CBS')
for name, field, value, reason in [
    ('wrong-operation', 'rtc.tax_point_operation_id', 'OTHER', 'RTC_ADVANCE_OPERATION_SUPPLIER_BINDING_INVALID'),
    ('wrong-supplier', 'rtc.supplier_id', '99999999000191', 'RTC_ADVANCE_OPERATION_SUPPLIER_BINDING_INVALID'),
    ('wrong-date', 'rtc.operation_date', '2026-10-09', 'RTC_ADVANCE_SELECTED_DATE_OR_GROSS_CONFLICT'),
    ('wrong-gross', 'rtc.gross_amount', '301.00', 'RTC_ADVANCE_SELECTED_DATE_OR_GROSS_CONFLICT'),
    ('wrong-document', 'rtc.document_id', 'OTHER', 'RTC_ADVANCE_FISCAL_DOCUMENT_BINDING_REQUIRED'),
    ('input-credit', 'rtc.credit_context', 'INPUT', 'RTC_ADVANCE_CONTEXT_REQUIRES_SEPARATE_TREATMENT'),
    ('public-purchase', 'customer.person_type', 'PUBLIC_ADMINISTRATION', 'RTC_ADVANCE_CONTEXT_REQUIRES_SEPARATE_TREATMENT'),
    ('final-route-conflict', 'rtc.assess_tax_point_events', True, 'RTC_ADVANCE_FINAL_CONTEXT_CONFLICT')]:
    p = scenario('past-payment-without-completed-supply'); p['facts'][field] = value; add(name, p, reason)
for name in ['invalid-payment-amount', 'reused-payment-reference', 'reused-document-link', 'unknown-actual-supply']:
    add(name, scenario(name), 'RTC_ADVANCE_' + cases[name]['origin_reason'])
p = scenario('past-payment-without-completed-supply')
p['facts']['rtc.base_adjustments'] = [{'type': 'UNCONDITIONAL_DISCOUNT', 'amount': '10.00'}]
add('base-transformation-not-allocated', p, 'RTC_ADVANCE_BASE_OR_RATE_TRANSFORMATION_UNPROVEN')
save(M, {'bundle': 'bundles/consumption-phase4b-2026.10.15-88-draft1.json', 'cases': rows,
    'iss_competence_unchanged': base['competence_date'], 'iss_operation_amount_unchanged': base['facts']['operation.amount'],
    'synthetic_only': True, 'registration_proven': False, 'production_ready': False})
print(f'PASS:{len(rows)} exclusive advance88 scenarios; independent expected nominal values and gates')
