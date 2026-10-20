<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialBaseEngine;
use App\Modules\Fiscal\Core\Shared\LocalDate;

/** @return array<string, mixed> */
function financialBaseLedger(): array
{
    return ['family' => 'CREDIT_FX_SECURITIES_ART192', 'supplier_id' => 'financial-supplier', 'currency' => 'BRL',
        'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'observed_on' => '2026-10-02',
        'ledger_complete' => true, 'financial_scope_qualified' => true, 'recognition_dates_qualified' => true, 'cooperative_art271_option' => false,
        'net_of_ibs_cbs_iss' => true, 'ledger_evidence_id' => 'FIXTURE.CLOSED', 'scope_evidence_id' => 'FIXTURE.SCOPE',
        'recognition_evidence_id' => 'FIXTURE.RECOGNITION', 'tax_exclusion_evidence_id' => 'FIXTURE.NET',
        'entries' => [financialBaseEntry('interest', 'FINANCIAL_REVENUE', '1000.00'),
            financialBaseEntry('principal', 'CREDIT_PRINCIPAL', '10000.00'),
            financialBaseEntry('funding', 'FUNDING_EXPENSE', '200.00'),
            financialBaseEntry('funding-principal', 'FUNDING_PRINCIPAL', '8000.00')]];
}

/** @return array<string, mixed> */
function financialBaseEntry(string $id, string $kind, string $amount): array
{
    return ['id' => $id, 'kind' => $kind, 'amount' => $amount, 'supplier_id' => 'financial-supplier',
        'document_id' => 'document-'.$id, 'recognized_on' => '2026-09-20', 'qualification_evidence_id' => 'FIXTURE.QUALIFIED',
        'operation_authorized' => true, 'within_operational_limits' => true, 'not_administrative_expense' => true,
        'authorization_evidence_id' => 'FIXTURE.AUTHORIZED'];
}

/** @param array<string, mixed> $ledger
 * @return array<string, mixed>
 */
function financialBaseResult(array $ledger): array
{
    return (new FinancialBaseEngine)->resolve($ledger, LocalDate::fromString('2026-09-30'),
        new DateTimeImmutable('2026-10-02'), ['strategy' => 'LC214_FINANCIAL_BASE_ART192_193', 'status' => 'ACTIVE',
            'published_at' => '2026-10-01', 'valid_from' => '2026-01-01',
            'source_hash' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde']);
}

it('excludes both credit and funding principal and deducts only eligible financial expenses', function (): void {
    $result = financialBaseResult(financialBaseLedger());
    expect($result['status'])->toBe('RESOLVED')->and($result['revenue'])->toBe('1000.00')
        ->and($result['deductions_before_art188'])->toBe('200.00')->and($result['excluded'])->toBe('18000.00')
        ->and($result['net_base'])->toBe('800.00')->and($result['posting_performed'])->toBeFalse();
});

it('recognizes securities margin and reversals only of previously deducted losses', function (): void {
    $ledger = financialBaseLedger();
    $ledger['entries'][] = [...financialBaseEntry('sale', 'SECURITIES_DISPOSAL', '500.00'), 'acquisition_cost' => '350.00'];
    $ledger['entries'][] = [...financialBaseEntry('recovery', 'REVERSAL_OR_RECOVERY', '80.00'), 'previously_deducted' => true];
    $ledger['entries'][] = [...financialBaseEntry('not-deducted', 'REVERSAL_OR_RECOVERY', '90.00'), 'previously_deducted' => false];
    $result = financialBaseResult($ledger);
    expect($result['revenue'])->toBe('1230.00')->and($result['net_base'])->toBe('1030.00')
        ->and($result['entries'][4]['computed_amount'])->toBe('150.00');
});

it('segregates unrealized fair value from realized returns and derivatives', function (): void {
    $ledger = financialBaseLedger();
    $ledger['entries'][] = [...financialBaseEntry('fair-value', 'FAIR_VALUE_UNREALIZED', '60.00'),
        'subaccount_evidence_id' => 'FIXTURE.SUBACCOUNT', 'exceeds_produced_return' => true];
    $ledger['entries'][] = financialBaseEntry('realized', 'FAIR_VALUE_REALIZED', '40.00');
    $ledger['entries'][] = financialBaseEntry('derivative', 'DERIVATIVE_INCOME', '30.00');
    $ledger['entries'][] = financialBaseEntry('derivative-expense', 'DERIVATIVE_EXPENSE', '10.00');
    $result = financialBaseResult($ledger);
    expect($result['deferred'])->toBe('60.00')->and($result['net_base'])->toBe('860.00');
});

it('reverses cooperative deductions by operation value with one monetary rounding', function (): void {
    $ledger = financialBaseLedger();
    $ledger['cooperative_art271_option'] = true;
    $ledger['cooperative_complete_operations_evidence_id'] = 'FIXTURE.ALL.OPERATIONS';
    $ledger['cooperative_all_operation_value'] = '3.00';
    $ledger['cooperative_zero_operation_value'] = '1.00';
    $result = financialBaseResult($ledger);
    expect($result['art188_reversal'])->toBe('66.67')->and($result['net_base'])->toBe('866.67');
});

it('excludes cooperative member credit revenues and their funding expenses for statutory resources', function (): void {
    $ledger = financialBaseLedger();
    foreach ([0, 2] as $index) {
        $ledger['entries'][$index]['cooperative_member_credit_exclusion'] = true;
        $ledger['entries'][$index]['funding_origin'] = 'PUBLIC_DIRECTED_EQUALIZED_OFFICIAL_FUND';
        $ledger['entries'][$index]['cooperative_member_funding_evidence_id'] = 'FIXTURE.LINKED.FUNDING';
    }
    $result = financialBaseResult($ledger);
    expect($result['net_base'])->toBe('0.00')->and($result['deductions_before_art188'])->toBe('0.00');
});

it('uses discount as revenue for securitization and admits listed third party costs', function (): void {
    $ledger = financialBaseLedger();
    $ledger['family'] = 'SECURITIZATION_FACTORING_ART193';
    $ledger['entries'] = [financialBaseEntry('discount', 'SECURITIZATION_DISCOUNT', '500.00'),
        [...financialBaseEntry('custody', 'SECURITIZATION_COST', '50.00'),
            'not_employee_or_administrator' => true, 'securitization_cost_category' => 'CUSTODY']];
    $result = financialBaseResult($ledger);
    expect($result['status'])->toBe('RESOLVED')->and($result['net_base'])->toBe('450.00');
});

it('retains a negative computed base without granting an unqualified carryforward', function (): void {
    $ledger = financialBaseLedger();
    $ledger['entries'][2]['amount'] = '1100.00';
    $result = financialBaseResult($ledger);
    expect($result['net_base'])->toBe('-100.00')
        ->and($result['negative_base_carryforward'])->toBe('NOT_ASSESSED_REQUIRES_SPECIFIC_RULE');
});

it('admits the statutory deduction categories with their required qualifications', function (string $kind, array $proof): void {
    $ledger = financialBaseLedger();
    $ledger['entries'][] = [...financialBaseEntry('additional', $kind, '10.00'), ...$proof];
    $result = financialBaseResult($ledger);
    expect($result['status'])->toBe('RESOLVED')->and($result['net_base'])->toBe('790.00');
})->with([
    ['FX_EXPENSE', []], ['SECURITIES_LOSS', []], ['DERIVATIVE_EXPENSE', []], ['FAIR_VALUE_REALIZED_EXPENSE', []],
    ['DEBT_INSTRUMENT_CHARGE', ['not_equity_instrument' => true]],
    ['CREDIT_LOSS', ['market_value_qualified' => true, 'income_tax_deductibility_qualified' => true, 'credit_loss_entity_eligible' => true]],
    ['ADVISOR_EXPENSE', ['not_employee_or_administrator' => true, 'advisor_category' => 'INVESTMENT_ADVISOR', 'advisor_registration_evidence_id' => 'FIXTURE.REGISTERED']],
]);

it('rejects general revenue as a substitute for the article193 discount base', function (): void {
    $ledger = financialBaseLedger();
    $ledger['family'] = 'SECURITIZATION_FACTORING_ART193';
    $result = financialBaseResult($ledger);
    expect($result['status'])->toBe('INVALID')->and($result['reason'])->toBe('RTC_FINANCIAL_BASE_ENTRY_FAMILY_CONFLICT');
});

it('keeps a future or revoked source rule unavailable', function (): void {
    $rule = ['strategy' => 'LC214_FINANCIAL_BASE_ART192_193', 'status' => 'ACTIVE', 'valid_from' => '2026-01-01',
        'published_at' => '2026-10-03', 'source_hash' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde'];
    $engine = new FinancialBaseEngine;
    $future = $engine->resolve(financialBaseLedger(), LocalDate::fromString('2026-09-30'), new DateTimeImmutable('2026-10-02'), $rule);
    $rule['published_at'] = '2026-10-01';
    $rule['revoked_at'] = '2026-10-02';
    $revoked = $engine->resolve(financialBaseLedger(), LocalDate::fromString('2026-09-30'), new DateTimeImmutable('2026-10-02'), $rule);
    expect($future['reason'])->toBe('RTC_FINANCIAL_BASE_RULE_NOT_AVAILABLE')
        ->and($revoked['reason'])->toBe('RTC_FINANCIAL_BASE_RULE_NOT_AVAILABLE');
});

it('rejects duplicate entries foreign owners administrative expenses and unqualified special deductions', function (string $failure, string $status, string $reason): void {
    $ledger = financialBaseLedger();
    switch ($failure) {
        case 'duplicate': $ledger['entries'][] = $ledger['entries'][0];
            break;
        case 'duplicate-component': $copy = $ledger['entries'][0];
            $copy['id'] = 'different-id';
            $ledger['entries'][] = $copy;
            break;
        case 'owner': $ledger['entries'][0]['supplier_id'] = 'other';
            break;
        case 'date': $ledger['entries'][0]['recognized_on'] = '2026-08-31';
            break;
        case 'administrative': $ledger['entries'][2]['not_administrative_expense'] = false;
            break;
        case 'authorization': unset($ledger['entries'][2]['authorization_evidence_id']);
            break;
        case 'kind': $ledger['entries'][2]['kind'] = 'OFFICE_RENT';
            break;
        case 'employee': $ledger['entries'][2]['kind'] = 'ADVISOR_EXPENSE';
            $ledger['entries'][2]['not_employee_or_administrator'] = false;
            break;
        case 'equity': $ledger['entries'][2]['kind'] = 'DEBT_INSTRUMENT_CHARGE';
            $ledger['entries'][2]['not_equity_instrument'] = false;
            break;
        case 'loss': $ledger['entries'][2]['kind'] = 'CREDIT_LOSS';
            break;
        case 'fair-value': $ledger['entries'][0]['kind'] = 'FAIR_VALUE_UNREALIZED';
            break;
        case 'reversal': $ledger['entries'][0]['kind'] = 'REVERSAL_OR_RECOVERY';
            break;
        case 'incomplete': $ledger['ledger_complete'] = false;
            break;
        case 'future': $ledger['observed_on'] = '2026-10-03';
            break;
        case 'currency': $ledger['currency'] = 'USD';
            break;
        case 'unknown-option': unset($ledger['cooperative_art271_option']);
            break;
    }
    $result = financialBaseResult($ledger);
    expect($result['status'])->toBe($status)->and($result['reason'])->toBe('RTC_FINANCIAL_BASE_'.$reason);
})->with([
    ['duplicate', 'INVALID', 'ENTRY_IDENTITY_CONFLICT'], ['owner', 'INVALID', 'ENTRY_IDENTITY_CONFLICT'],
    ['duplicate-component', 'INVALID', 'DUPLICATE_DOCUMENT_COMPONENT'],
    ['date', 'INVALID', 'ENTRY_OUTSIDE_PERIOD'], ['administrative', 'INVALID', 'DEDUCTION_CONDITION_NOT_MET:not_administrative_expense'],
    ['authorization', 'ACTION_REQUIRED', 'DEDUCTION_AUTHORIZATION_UNPROVEN'], ['kind', 'INVALID', 'DEDUCTION_NOT_AUTHORIZED:OFFICE_RENT'],
    ['employee', 'INVALID', 'EMPLOYEE_OR_ADMINISTRATOR_COST'], ['equity', 'INVALID', 'EQUITY_INSTRUMENT_CHARGE'],
    ['loss', 'ACTION_REQUIRED', 'CREDIT_LOSS_QUALIFICATION_UNPROVEN'], ['fair-value', 'ACTION_REQUIRED', 'FAIR_VALUE_DEFERRAL_UNPROVEN'],
    ['reversal', 'ACTION_REQUIRED', 'PREVIOUS_DEDUCTION_UNPROVEN'], ['incomplete', 'ACTION_REQUIRED', 'CONDITION_UNPROVEN:ledger_complete'],
    ['future', 'INVALID', 'PERIOD_OR_OBSERVATION_CONFLICT'], ['currency', 'ACTION_REQUIRED', 'CURRENCY_CONVERSION_REQUIRED'],
    ['unknown-option', 'ACTION_REQUIRED', 'COOPERATIVE_OPTION_UNPROVEN'],
]);
