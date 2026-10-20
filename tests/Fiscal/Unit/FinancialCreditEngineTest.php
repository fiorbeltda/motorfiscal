<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialCreditEngine;
use App\Modules\Fiscal\Core\Shared\LocalDate;

/** Synthetic qualified evidence, not a source of SELIC, DI or actual supplier payments.
 * @return array<string, mixed>
 */
function financialCreditProof(): array
{
    $proof = ['supplier_id' => 'supplier', 'acquirer_id' => 'borrower', 'document_id' => 'financial-document',
        'route' => 'LOAN_INSTALMENT_ART194', 'operation_category' => 'CREDIT', 'acquirer_regime' => 'REGULAR',
        'not_personal_use' => true, 'acquirer_in_section_specific_regime' => false,
        'cooperative_option271_member_borrower' => false, 'foreign_currency_referenced' => false,
        'operation_qualification_evidence_id' => 'FIXTURE.QUALIFIED', 'supplier_report_evidence_id' => 'FIXTURE.REPORT',
        'fiscal_document_evidence_id' => 'FIXTURE.DOCUMENT', 'fiscal_document_valid' => true,
        'supplier_report_bound_to_operation' => true, 'supplier_report_hash' => str_repeat('a', 64),
        'credit_request_ledger_evidence_id' => 'FIXTURE.REQUEST.LEDGER', 'transaction_id' => 'instalment-1', 'request_not_already_appropriated' => true,
        'observed_on' => '2026-09-30', 'payment_occurred' => true, 'paid_on' => '2026-09-20', 'payment_evidence_id' => 'FIXTURE.PAID',
        'instalment_paid' => '1200.00', 'contractual_principal_part' => '1000.00', 'qualified_selic_amount' => '50.00',
        'contractual_amortization_evidence_id' => 'FIXTURE.AMORTIZATION', 'benchmark_kind' => 'SELIC_OVERNIGHT_ON_PRINCIPAL',
        'benchmark_calculation_qualified' => true, 'benchmark_period_evidence_id' => 'FIXTURE.REFERENCE.PERIOD',
        'benchmark_source_hash' => str_repeat('b', 64), 'components' => []];
    foreach (['IBS' => '0.15', 'CBS' => '1.35'] as $tax => $amount) {
        $proof['components'][$tax] = ['tax' => $tax, 'supplier_report_component_id' => 'report-'.$tax,
            'reported_credit_limit' => '5.00', 'requested_credit' => $amount,
            'extinction_qualification' => 'PROVEN_EXTINCTION', 'extinction_evidence_id' => 'FIXTURE.EXTINCTION',
            'supplier_tax_actually_paid_for_fee' => $amount, 'supplier_tax_paid_on' => '2026-09-25', 'supplier_payment_evidence_id' => 'FIXTURE.SUPPLIER.PAYMENT'];
    }

    return $proof;
}

/** @param array<string, mixed> $proof
 * @return array<string, mixed>
 */
function financialCreditResult(array $proof): array
{
    $tax = ['status' => 'RESOLVED', 'supplier_id' => 'supplier', 'period_end' => '2026-09-30',
        'components' => ['IBS_STATE' => ['percent' => '0.10'], 'IBS_MUNICIPAL' => ['percent' => '0.00'], 'CBS' => ['percent' => '0.90']]];

    return (new FinancialCreditEngine)->resolve($proof, $tax, 'supplier', 'borrower', 'financial-document',
        LocalDate::fromString('2026-09-30'), new DateTimeImmutable('2026-10-02'),
        ['strategy' => 'LC214_FINANCIAL_BORROWER_CREDIT_ART190_194_199', 'status' => 'ACTIVE',
            'published_at' => '2026-10-01', 'valid_from' => '2026-01-01',
            'source_hash' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde']);
}

it('calculates over SELIC credit after cash payment and contractual principal deduction', function (): void {
    $result = financialCreditResult(financialCreditProof());
    expect($result['status'])->toBe('RESOLVED')->and($result['eligibility'])->toBe('ALLOWED')
        ->and($result['credit_base'])->toBe('150.00')
        ->and($result['components']['IBS']['computed_limit'])->toBe('0.15')
        ->and($result['components']['CBS']['requested_credit'])->toBe('1.35')
        ->and($result['appropriation_performed'])->toBeFalse()->and($result['posting_performed'])->toBeFalse();
});

it('uses over DI on the advance discount instead of loan principal deductions', function (): void {
    $proof = financialCreditProof();
    $proof['route'] = 'RECEIVABLE_DISCOUNT_ART196';
    $proof['operation_category'] = 'FACTORING';
    $proof['discount_applied'] = '200.00';
    $proof['qualified_di_curve_amount'] = '50.00';
    $proof['benchmark_kind'] = 'DI_CURVE_FOR_ADVANCE_TERM';
    expect(financialCreditResult($proof)['credit_base'])->toBe('150.00');
    $proof['benchmark_kind'] = 'SELIC_OVERNIGHT_ON_PRINCIPAL';
    expect(financialCreditResult($proof)['reason'])->toBe('RTC_FINANCIAL_CREDIT_BENCHMARK_CALCULATION_UNPROVEN');
});

it('qualifies debt issuance only with the holding period and public debt exception proven', function (): void {
    $proof = financialCreditProof();
    $proof['route'] = 'DEBT_ISSUE_ART195';
    $proof['operation_category'] = 'SECURITIES';
    $proof['held_by_specific_regime_creditor_during_payment_period'] = true;
    $proof['creditor_holding_period_evidence_id'] = 'FIXTURE.HOLDING';
    $proof['public_debt_exception_qualified'] = false;
    $proof['public_debt_exception_evidence_id'] = 'FIXTURE.PUBLIC.EXCEPTION.REVIEW';
    expect(financialCreditResult($proof)['eligibility'])->toBe('ALLOWED');
    $proof['public_debt_exception_qualified'] = true;
    expect(financialCreditResult($proof)['eligibility'])->toBe('DENIED');
});

it('uses actual supplier paid fee taxes and allows a financial acquirer only without base deduction', function (): void {
    $proof = financialCreditProof();
    $proof['route'] = 'FEES_ART198';
    $proof['operation_category'] = 'FX';
    $proof['acquirer_in_section_specific_regime'] = true;
    $proof['expense_deducted_from_specific_base'] = false;
    expect(financialCreditResult($proof)['components']['CBS']['computed_limit'])->toBe('1.35');
    $proof['expense_deducted_from_specific_base'] = true;
    expect(financialCreditResult($proof)['reason'])->toBe('RTC_FINANCIAL_CREDIT_EXPENSE_ALREADY_DEDUCTED_ART198');
});

it('does not treat accessory compliance as extinction or a proved article48 exception', function (): void {
    $proof = financialCreditProof();
    unset($proof['components']['IBS']['extinction_evidence_id']);
    $conditional = financialCreditResult($proof);
    expect($conditional['eligibility'])->toBe('MIXED')->and($conditional['components']['IBS']['requested_credit'])->toBe('0.00')
        ->and($conditional['components']['CBS']['requested_credit'])->toBe('1.35');
    $proof['components']['IBS']['extinction_qualification'] = 'ART48_DOCUMENTAL_DISPENSATION';
    expect(financialCreditResult($proof)['status'])->toBe('ACTION_REQUIRED');
    $proof['components']['IBS'] += ['split_not_implemented' => true, 'acquirer_collection_not_implemented' => true,
        'implementation_status_evidence_id' => 'FIXTURE.IMPLEMENTATION.STATUS', 'implementation_source_hash' => str_repeat('c', 64),
        'correct_fiscal_debits_qualified' => true];
    expect(financialCreditResult($proof)['eligibility'])->toBe('ALLOWED');
});

it('keeps unpaid operations conditional and does not generate a negative credit', function (): void {
    $proof = financialCreditProof();
    $proof['payment_occurred'] = false;
    expect(financialCreditResult($proof)['eligibility'])->toBe('CONDITIONAL');
    $proof['payment_occurred'] = true;
    $proof['qualified_selic_amount'] = '250.00';
    foreach (['IBS', 'CBS'] as $tax) {
        $proof['components'][$tax]['requested_credit'] = '0.00';
    }
    expect(financialCreditResult($proof)['credit_base'])->toBe('0.00');
});

it('requires rates from the payment period and rejects future supplier fee tax payments', function (): void {
    $proof = financialCreditProof();
    $proof['paid_on'] = '2026-08-20';
    expect(financialCreditResult($proof)['reason'])->toBe('RTC_FINANCIAL_CREDIT_RATE_PAYMENT_PERIOD_CONFLICT');
    $proof = financialCreditProof();
    $proof['route'] = 'FEES_ART198';
    $proof['components']['IBS']['supplier_tax_paid_on'] = '2026-10-01';
    expect(financialCreditResult($proof)['reason'])->toBe('RTC_FINANCIAL_CREDIT_SUPPLIER_FEE_PAYMENT_DATE_CONFLICT:IBS');
});

it('applies the statutory borrower exclusions independently of generic credit eligibility', function (string $failure, string $reason): void {
    $proof = financialCreditProof();
    if ($failure === 'simples') {
        $proof['acquirer_regime'] = 'SIMPLES_NACIONAL';
    } elseif ($failure === 'personal') {
        $proof['not_personal_use'] = false;
    } elseif ($failure === 'specific') {
        $proof['acquirer_in_section_specific_regime'] = true;
    } elseif ($failure === 'member') {
        $proof['cooperative_option271_member_borrower'] = true;
    } elseif ($failure === 'currency') {
        $proof['foreign_currency_referenced'] = true;
    } else {
        $proof['route'] = 'OTHER_ART199';
    }
    $result = financialCreditResult($proof);
    expect($result['eligibility'])->toBe('DENIED')->and($result['reason'])->toBe('RTC_FINANCIAL_CREDIT_'.$reason);
})->with([
    ['simples', 'ACQUIRER_NOT_REGULAR'], ['personal', 'PERSONAL_USE_ART57'], ['specific', 'ACQUIRER_IN_SECTION_SPECIFIC_REGIME'],
    ['member', 'COOPERATIVE_MEMBER_ART197_I'], ['currency', 'FOREIGN_CURRENCY_ART197_II'], ['other', 'NO_EXPRESS_PERMISSION_ART199'],
]);

it('rejects conflicting identity timing amortization report limits and reuse', function (string $failure, string $status, string $reason): void {
    $proof = financialCreditProof();
    switch ($failure) {
        case 'supplier': $proof['supplier_id'] = 'other';
            break;
        case 'document': $proof['document_id'] = 'other';
            break;
        case 'future': $proof['observed_on'] = '2026-10-03';
            break;
        case 'future-paid': $proof['paid_on'] = '2026-10-01';
            break;
        case 'principal': $proof['contractual_principal_part'] = '1200.01';
            break;
        case 'benchmark': unset($proof['benchmark_source_hash']);
            break;
        case 'report': $proof['components']['IBS']['reported_credit_limit'] = '0.14';
            break;
        case 'excess': $proof['components']['CBS']['requested_credit'] = '1.36';
            break;
        case 'reuse': $proof['request_not_already_appropriated'] = false;
            break;
        case 'hash': $proof['supplier_report_hash'] = 'bad';
            break;
        case 'route': $proof['operation_category'] = 'LEASING';
            break;
    }
    $result = financialCreditResult($proof);
    expect($result['status'])->toBe($status)->and($result['reason'])->toBe('RTC_FINANCIAL_CREDIT_'.$reason);
})->with([
    ['supplier', 'INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT'], ['document', 'INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT'],
    ['future', 'INVALID', 'OBSERVATION_CONFLICT'], ['future-paid', 'INVALID', 'PAYMENT_DATE_OR_PROOF_CONFLICT'],
    ['principal', 'INVALID', 'PRINCIPAL_EXCEEDS_INSTALMENT'], ['benchmark', 'ACTION_REQUIRED', 'BENCHMARK_CALCULATION_UNPROVEN'],
    ['report', 'INVALID', 'COMPUTED_CREDIT_EXCEEDS_SUPPLIER_REPORT:IBS'], ['excess', 'INVALID', 'REQUEST_EXCEEDS_ALLOWED_CREDIT:CBS'],
    ['reuse', 'INVALID', 'PRIOR_APPROPRIATION_CONFLICT_OR_UNPROVEN'], ['hash', 'ACTION_REQUIRED', 'DOCUMENT_OR_SUPPLIER_REPORT_UNPROVEN'],
    ['route', 'INVALID', 'ROUTE_OPERATION_CONFLICT'],
]);
