<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialOperationEngine;
use App\Modules\Fiscal\Core\Shared\LocalDate;

/** @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>} */
function financialOperationInput(): array
{
    $source = 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde';
    $rule = ['status' => 'ACTIVE', 'valid_from' => '2026-01-01', 'published_at' => '2026-10-01', 'source_hash' => $source];
    $rules = ['scope' => [...$rule, 'strategy' => 'LC214_FINANCIAL_SCOPE_ART182_184'],
        'base' => [...$rule, 'strategy' => 'LC214_FINANCIAL_BASE_ART192_193']];
    $proof = ['supplier_id' => 'supplier', 'document_id' => 'period-document', 'provider_category' => 'BANK',
        'supervision' => 'SFN_SUPERVISED', 'supervision_effective' => true, 'supervision_registry_evidence_id' => 'FIXTURE.REGISTRY',
        'provider_scope_evidence_id' => 'FIXTURE.PROVIDER', 'operation_scope_evidence_id' => 'FIXTURE.OPERATION',
        'valid_from' => '2026-01-01', 'observed_on' => '2026-10-01', 'operation_category' => 'CREDIT',
        'provider_authorized_for_operation' => true, 'operation_authorization_evidence_id' => 'FIXTURE.AUTHORIZATION'];
    $ledger = ['family' => 'CREDIT_FX_SECURITIES_ART192', 'supplier_id' => 'supplier', 'scope_document_id' => 'period-document',
        'currency' => 'BRL', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'observed_on' => '2026-10-01',
        'ledger_complete' => true, 'financial_scope_qualified' => true, 'recognition_dates_qualified' => true,
        'net_of_ibs_cbs_iss' => true, 'cooperative_art271_option' => false, 'ledger_evidence_id' => 'FIXTURE.LEDGER',
        'scope_evidence_id' => 'FIXTURE.SCOPE', 'recognition_evidence_id' => 'FIXTURE.RECOGNITION', 'tax_exclusion_evidence_id' => 'FIXTURE.NET',
        'entries' => [['id' => 'interest', 'kind' => 'FINANCIAL_REVENUE', 'amount' => '1000.00', 'supplier_id' => 'supplier',
            'document_id' => 'contract-1', 'recognized_on' => '2026-09-20', 'qualification_evidence_id' => 'FIXTURE.QUALIFIED']]];

    return [$proof, $ledger, $rules];
}

it('composes financial scope and the bound monthly base', function (): void {
    [$proof, $ledger, $rules] = financialOperationInput();
    $result = (new FinancialOperationEngine)->resolve($proof, $ledger, 'supplier', 'period-document',
        LocalDate::fromString('2026-09-30'), new DateTimeImmutable('2026-10-02'), $rules);
    expect($result['status'])->toBe('RESOLVED')->and($result['base']['net_base'])->toBe('1000.00')
        ->and($result['qualification']['regime'])->toBe('SPECIFIC_FINANCIAL_ART181');
});

it('rejects a ledger for another document supplier or financial family', function (string $key, string $value): void {
    [$proof, $ledger, $rules] = financialOperationInput();
    $ledger[$key] = $value;
    $result = (new FinancialOperationEngine)->resolve($proof, $ledger, 'supplier', 'period-document',
        LocalDate::fromString('2026-09-30'), new DateTimeImmutable('2026-10-02'), $rules);
    expect($result['status'])->toBe('INVALID')->and($result['reason'])->toBe('RTC_FINANCIAL_OPERATION_LEDGER_SCOPE_CONFLICT');
})->with([['scope_document_id', 'other'], ['supplier_id', 'other'], ['family', 'SECURITIZATION_FACTORING_ART193']]);

it('requires a qualified ledger for specific financial services', function (): void {
    [$proof, , $rules] = financialOperationInput();
    $result = (new FinancialOperationEngine)->resolve($proof, null, 'supplier', 'period-document',
        LocalDate::fromString('2026-09-30'), new DateTimeImmutable('2026-10-02'), $rules);
    expect($result['reason'])->toBe('RTC_FINANCIAL_QUALIFIED_LEDGER_MISSING');
});

it('routes proven general services away from the specific financial base', function (): void {
    [$proof, $ledger, $rules] = financialOperationInput();
    $proof['operation_category'] = 'OTHER_NON_FINANCIAL_SERVICE';
    $proof['not_art182_service'] = true;
    $proof['non_financial_scope_evidence_id'] = 'FIXTURE.SOFTWARE';
    $engine = new FinancialOperationEngine;
    $general = $engine->resolve($proof, null, 'supplier', 'period-document', LocalDate::fromString('2026-09-30'), new DateTimeImmutable('2026-10-02'), $rules);
    $conflict = $engine->resolve($proof, $ledger, 'supplier', 'period-document', LocalDate::fromString('2026-09-30'), new DateTimeImmutable('2026-10-02'), $rules);
    expect($general['status'])->toBe('RESOLVED')->and($general['base'])->toBeNull()
        ->and($conflict['status'])->toBe('INVALID')->and($conflict['reason'])->toBe('RTC_FINANCIAL_GENERAL_RULES_WITH_SPECIFIC_BASE_CONFLICT');
});
