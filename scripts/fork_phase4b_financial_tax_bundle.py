"""Bind financial gross debits to published rates without approving credits or documents."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'resources/fiscal'
VERSION = '2026.10.02-12'


def read(path):
    return json.loads((ROOT/path).read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def write(path, value):
    target = ROOT/path
    if target.exists():
        if read(path) != value:
            raise RuntimeError(f'Refusing to overwrite {target}')
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2)+'\n', encoding='utf-8')


previous = read('bundles/consumption-phase4b-2026.10.02-11.json')
target = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(target)['knowledge_available_at'] if (ROOT/target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
codes['financial_operation']['tax'] = {**codes['financial_operation']['base'],
    'strategy': 'LC214_FINANCIAL_GROSS_DEBITS_ART189_233_348', 'published_at': available,
    'scope': 'GROSS_DEBITS_ONLY_NO_CREDIT_SETTLEMENT_OR_DOCUMENT_APPROVAL'}
codes['financial_rate_catalog'] = {'version': VERSION, 'rates': [],
    'notice': 'No future financial component rate is invented from statutory combined percentages;2026 uses preserved test rates.'}
facts = read(previous['resource_paths']['fact_definitions'])
facts.append({'key': 'rtc.financial_service_subject_to_iss', 'type': 'BOOLEAN', 'material': True})
questions = read(previous['resource_paths']['questions'])
questions.append({'fact_key': 'rtc.financial_service_subject_to_iss', 'answer_type': 'BOOLEAN',
    'rank': max(row['rank'] for row in questions)+1,
    'prompt': 'O serviço financeiro tem incidência de ISS qualificada independentemente? Esse fato condiciona a soma de alíquotas do art233, sem substituir a decisão de ISS.'})
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.4.BASE_AND_SPECIAL_REGIMES':
        control['gap'] += ' Débitos financeiros brutos192/193 calculados em2026 com343/346/348; condições de recolhimento separadas. Componentes futuros exigem atos; créditos/documentos específicos e demais bases pendentes.'
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION, predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
bundle['catalog_versions']['rtc_codes'] = VERSION
for key, path, value in [('rtc_code_catalog', f'catalogs/rtc-codes-{VERSION}.json', codes),
    ('fact_definitions', f'facts/consumption-phase4b-{VERSION}.json', facts),
    ('questions', f'questions/consumption-integrated-{VERSION}.json', questions)]:
    write(path, value); bundle['resource_paths'][key] = path; bundle['content_hashes'][key] = digest(value)
write(f'production/phase4b-controls-{VERSION}.json', controls)
bundle['production_controls'] = {'path': f'production/phase4b-controls-{VERSION}.json', 'sha256': digest(controls)}
write(target, bundle)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {'version': VERSION, 'retrieved_on': '2026-10-02',
    'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources']})
base = read('scenarios/consumption-integrated-2026.10.02-11/integrated-financial-scope-bank-software-2026.json')
base.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
for name in ['bank-software', 'savings-bank-software', 'payment-institution-software']:
    case = read(f'scenarios/consumption-integrated-2026.10.02-11/integrated-financial-scope-{name}-2026.json')
    case.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available,
        scenario_id=f'integrated-financial-tax-general-{name}-2026')
    write(f'scenarios/consumption-integrated-{VERSION}/{case["scenario_id"]}.json', case)
base['competence_date'] = '2026-09-30'
f = base['facts']
for key in list(f):
    if key.startswith(('service.', 'inss.', 'federal.', 'payer.', 'payment.', 'invoice.')):
        del f[key]
f.update({'rtc.operation_date': '2026-09-30', 'rtc.operation_kind': 'FINANCIAL', 'federal.evaluate_withholdings': False})
p = f['rtc.financial_scope_proof'][0]
p.update(operation_category='CREDIT', provider_authorized_for_operation=True, operation_authorization_evidence_id='FIXTURE.CREDIT.AUTHORIZATION')
entry = {'supplier_id': f['rtc.supplier_id'], 'recognized_on': '2026-09-20', 'qualification_evidence_id': 'FIXTURE.QUALIFIED'}
ledger = {'family': 'CREDIT_FX_SECURITIES_ART192', 'supplier_id': f['rtc.supplier_id'], 'scope_document_id': f['rtc.document_id'],
    'currency': 'BRL', 'period_start': '2026-09-01', 'period_end': '2026-09-30', 'observed_on': '2026-10-01',
    'ledger_complete': True, 'financial_scope_qualified': True, 'recognition_dates_qualified': True, 'net_of_ibs_cbs_iss': True,
    'cooperative_art271_option': False, 'ledger_evidence_id': 'FIXTURE.LEDGER', 'scope_evidence_id': 'FIXTURE.SCOPE',
    'recognition_evidence_id': 'FIXTURE.RECOGNITION', 'tax_exclusion_evidence_id': 'FIXTURE.NET',
    'entries': [{**entry, 'id': 'interest', 'kind': 'FINANCIAL_REVENUE', 'document_id': 'contract1', 'amount': '1000.00'},
        {**entry, 'id': 'principal', 'kind': 'CREDIT_PRINCIPAL', 'document_id': 'contract1', 'amount': '10000.00'},
        {**entry, 'id': 'funding', 'kind': 'FUNDING_EXPENSE', 'document_id': 'funding1', 'amount': '200.00',
            'operation_authorized': True, 'within_operational_limits': True, 'not_administrative_expense': True, 'authorization_evidence_id': 'FIXTURE.AUTHORIZED'}]}
f['rtc.financial_ledger'] = [ledger]
cases = {'dispensed': copy.deepcopy(base), 'not-dispensed': copy.deepcopy(base), 'unknown-condition': copy.deepcopy(base),
    'negative-base': copy.deepcopy(base), 'mixed-cooperative': copy.deepcopy(base), 'factoring': copy.deepcopy(base)}
cases['not-dispensed']['facts']['rtc.accessory_obligations_fulfilled'] = False
del cases['unknown-condition']['facts']['rtc.accessory_obligations_fulfilled']
cases['negative-base']['facts']['rtc.financial_ledger'][0]['entries'][2]['amount'] = '1100.00'
c = cases['mixed-cooperative']['facts']['rtc.financial_ledger'][0]
c.update(cooperative_art271_option=True, cooperative_all_operation_value='3.00', cooperative_zero_operation_value='1.00', cooperative_complete_operations_evidence_id='FIXTURE.ALL.OPS')
c = cases['factoring']['facts']; c['rtc.financial_scope_proof'][0]['operation_category'] = 'FACTORING'
l = c['rtc.financial_ledger'][0]; l['family'] = 'SECURITIZATION_FACTORING_ART193'
l['entries'] = [{**entry, 'id': 'discount', 'kind': 'SECURITIZATION_DISCOUNT', 'document_id': 'discount1', 'amount': '1000.00'}, ledger['entries'][2]]
for name, case in cases.items():
    case['scenario_id'] = f'financial-gross-debits-{name}-2026'
    write(f'scenarios/consumption-phase4b-{VERSION}/{case["scenario_id"]}.json', case)
print(f'Created {bundle["bundle_id"]}:6 RTC partial calculations and3 integrated general regressions')
