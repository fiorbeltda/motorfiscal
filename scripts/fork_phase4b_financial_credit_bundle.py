"""Activate qualified borrower credits; synthetic benchmarks do not validate real data."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'resources/fiscal'
VERSION = '2026.10.02-13'


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


previous = read('bundles/consumption-phase4b-2026.10.02-12.json')
target = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(target)['knowledge_available_at'] if (ROOT/target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
codes['financial_operation']['credit'] = {**codes['financial_operation']['base'],
    'strategy': 'LC214_FINANCIAL_BORROWER_CREDIT_ART190_194_199', 'published_at': available,
    'scope': 'QUALIFIED_REPORT_CASH_BASIS_BENCHMARK_NO_APPROPRIATION_OR_POSTING'}
facts = read(previous['resource_paths']['fact_definitions'])
facts.append({'key': 'rtc.financial_credit_proof', 'type': 'ARRAY', 'material': True})
questions = read(previous['resource_paths']['questions'])
questions.append({'fact_key': 'rtc.financial_credit_proof', 'answer_type': 'ARRAY',
    'rank': max(row['rank'] for row in questions)+1,
    'prompt': 'Informe a prova financeira vinculada ao tomador, operação, pagamento, relatório do fornecedor e referência Selic ou DI qualificada, por componente IBS/CBS.'})
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.4.BASE_AND_SPECIAL_REGIMES':
        control['gap'] += ' Crédito financeiro194–199 integrado com prova pré-qualificada, caixa, limites do relatório e extinção por componente. Selic/DI brutas, qualificação automática da carteira195 e documento financeiro permanecem pendentes.'
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
    predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
bundle['catalog_versions']['rtc_codes'] = VERSION
for key, path, value in [('rtc_code_catalog', f'catalogs/rtc-codes-{VERSION}.json', codes),
    ('fact_definitions', f'facts/consumption-phase4b-{VERSION}.json', facts),
    ('questions', f'questions/consumption-integrated-{VERSION}.json', questions)]:
    write(path, value)
    bundle['resource_paths'][key] = path
    bundle['content_hashes'][key] = digest(value)
write(f'production/phase4b-controls-{VERSION}.json', controls)
bundle['production_controls'] = {'path': f'production/phase4b-controls-{VERSION}.json', 'sha256': digest(controls)}
write(target, bundle)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {'version': VERSION, 'retrieved_on': '2026-10-02',
    'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources']})

base = read('scenarios/consumption-phase4b-2026.10.02-12/financial-gross-debits-not-dispensed-2026.json')
base.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
f = base['facts']
f.update({'rtc.credit_context': 'INPUT', 'rtc.acquirer_regime': 'REGULAR'})
proof = {'supplier_id': f['rtc.supplier_id'], 'acquirer_id': f['rtc.acquirer_id'], 'document_id': f['rtc.document_id'],
    'route': 'LOAN_INSTALMENT_ART194', 'operation_category': 'CREDIT', 'acquirer_regime': 'REGULAR',
    'not_personal_use': True, 'acquirer_in_section_specific_regime': False,
    'cooperative_option271_member_borrower': False, 'foreign_currency_referenced': False,
    'operation_qualification_evidence_id': 'FIXTURE.QUALIFIED', 'supplier_report_evidence_id': 'FIXTURE.REPORT',
    'fiscal_document_evidence_id': 'FIXTURE.DOCUMENT', 'fiscal_document_valid': True,
    'supplier_report_bound_to_operation': True, 'supplier_report_hash': 'a'*64,
    'credit_request_ledger_evidence_id': 'FIXTURE.REQUEST.LEDGER', 'transaction_id': 'instalment1', 'request_not_already_appropriated': True,
    'observed_on': '2026-09-30', 'payment_occurred': True, 'paid_on': '2026-09-20', 'payment_evidence_id': 'FIXTURE.PAID',
    'instalment_paid': '1200.00', 'contractual_principal_part': '1000.00', 'qualified_selic_amount': '50.00',
    'contractual_amortization_evidence_id': 'FIXTURE.AMORTIZATION', 'benchmark_kind': 'SELIC_OVERNIGHT_ON_PRINCIPAL',
    'benchmark_calculation_qualified': True, 'benchmark_period_evidence_id': 'FIXTURE.REFERENCE.PERIOD',
    'benchmark_source_hash': 'b'*64, 'components': {}}
for tax, amount in [('IBS', '0.15'), ('CBS', '1.35')]:
    proof['components'][tax] = {'tax': tax, 'supplier_report_component_id': f'report-{tax}',
        'reported_credit_limit': '5.00', 'requested_credit': amount,
        'extinction_qualification': 'PROVEN_EXTINCTION', 'extinction_evidence_id': 'FIXTURE.EXTINCTION',
        'supplier_tax_actually_paid_for_fee': amount, 'supplier_tax_paid_on': '2026-09-25',
        'supplier_payment_evidence_id': 'FIXTURE.SUPPLIER.PAYMENT'}
f['rtc.financial_credit_proof'] = [proof]
cases = {name: copy.deepcopy(base) for name in ['loan', 'receivable', 'private-debt', 'public-debt', 'fee', 'mixed-extinction',
    'unpaid', 'currency-denied', 'benchmark-unproven', 'identity-conflict', 'category-conflict', 'missing-proof', 'output-unassessed']}
for name, case in cases.items():
    cf = case['facts']; cp = cf['rtc.financial_credit_proof'][0]
    if name == 'receivable':
        cp.update(route='RECEIVABLE_DISCOUNT_ART196', discount_applied='200.00', qualified_di_curve_amount='50.00', benchmark_kind='DI_CURVE_FOR_ADVANCE_TERM')
    elif name in ['private-debt', 'public-debt']:
        cp.update(route='DEBT_ISSUE_ART195', operation_category='SECURITIES',
            held_by_specific_regime_creditor_during_payment_period=True, creditor_holding_period_evidence_id='FIXTURE.HOLDING',
            public_debt_exception_qualified=name == 'public-debt', public_debt_exception_evidence_id='FIXTURE.EXCEPTION.REVIEW')
        cf['rtc.financial_scope_proof'][0]['operation_category'] = 'SECURITIES'
    elif name == 'fee':
        cp.update(route='FEES_ART198', acquirer_in_section_specific_regime=True, expense_deducted_from_specific_base=False)
    elif name == 'mixed-extinction':
        del cp['components']['IBS']['extinction_evidence_id']
    elif name == 'unpaid':
        cp['payment_occurred'] = False
    elif name == 'currency-denied':
        cp['foreign_currency_referenced'] = True
    elif name == 'benchmark-unproven':
        del cp['benchmark_source_hash']
    elif name == 'identity-conflict':
        cp['acquirer_id'] = 'other'
    elif name == 'category-conflict':
        cp['operation_category'] = 'FX'
    elif name in ['missing-proof', 'output-unassessed']:
        del cf['rtc.financial_credit_proof']
        if name == 'output-unassessed':
            cf['rtc.credit_context'] = 'OUTPUT'
    case['scenario_id'] = f'financial-borrower-credit-{name}-2026'
    write(f'scenarios/consumption-phase4b-{VERSION}/{case["scenario_id"]}.json', case)
for name in ['bank-software', 'savings-bank-software', 'payment-institution-software']:
    case = read(f'scenarios/consumption-integrated-2026.10.02-12/integrated-financial-tax-general-{name}-2026.json')
    case.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available,
        scenario_id=f'integrated-financial-credit-general-{name}-2026')
    write(f'scenarios/consumption-integrated-{VERSION}/{case["scenario_id"]}.json', case)
print('Materialized13 borrower-credit cases and3 general integration cases; specific document remains pending.')
