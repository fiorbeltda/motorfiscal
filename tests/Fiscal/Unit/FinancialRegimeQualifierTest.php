<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialRegimeQualifier;
use App\Modules\Fiscal\Core\Shared\LocalDate;

/** @return array<string, mixed> */
function financialScopeProof(): array
{
    return ['supplier_id' => 'supplier', 'document_id' => 'document', 'provider_category' => 'BANK',
        'supervision' => 'SFN_SUPERVISED', 'supervision_effective' => true,
        'supervision_registry_evidence_id' => 'FIXTURE.REGISTRY', 'provider_scope_evidence_id' => 'FIXTURE.PROVIDER',
        'operation_scope_evidence_id' => 'FIXTURE.OPERATION', 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31',
        'observed_on' => '2026-10-01', 'operation_category' => 'CREDIT', 'provider_authorized_for_operation' => true,
        'operation_authorization_evidence_id' => 'FIXTURE.AUTHORIZATION'];
}

/** @param array<string, mixed> $proof
 * @return array<string, mixed>
 */
function financialScopeResult(array $proof): array
{
    return (new FinancialRegimeQualifier)->resolve($proof, 'supplier', 'document', LocalDate::fromString('2026-10-01'),
        new DateTimeImmutable('2026-10-02'), ['strategy' => 'LC214_FINANCIAL_SCOPE_ART182_184', 'status' => 'ACTIVE',
            'valid_from' => '2026-01-01', 'published_at' => '2026-10-01',
            'source_hash' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde']);
}

it('selects the specific base family from the proven operation rather than the provider name', function (string $operation, string $family): void {
    $proof = financialScopeProof();
    $proof['operation_category'] = $operation;
    $result = financialScopeResult($proof);
    expect($result['status'])->toBe('RESOLVED')->and($result['regime'])->toBe('SPECIFIC_FINANCIAL_ART181')
        ->and($result['base_family'])->toBe($family);
})->with([
    ['CREDIT', 'CREDIT_FX_SECURITIES_ART192'], ['FX', 'CREDIT_FX_SECURITIES_ART192'], ['SECURITIES', 'CREDIT_FX_SECURITIES_ART192'],
    ['SECURITIZATION', 'SECURITIZATION_FACTORING_ART193'], ['FACTORING', 'SECURITIZATION_FACTORING_ART193'],
    ['LEASING', 'LEASING'], ['CONSORTIUM_ADMINISTRATION', 'CONSORTIUM'], ['RESOURCE_MANAGEMENT', 'RESOURCE_MANAGEMENT'],
    ['PAYMENT_ARRANGEMENT', 'PAYMENT_ARRANGEMENT'], ['MARKET_INFRASTRUCTURE', 'MARKET_INFRASTRUCTURE'],
    ['INSURANCE_OTHER_THAN_HEALTH', 'INSURANCE'], ['REINSURANCE', 'REINSURANCE'], ['PRIVATE_PENSION', 'PRIVATE_PENSION'],
    ['CAPITALIZATION', 'CAPITALIZATION'], ['FINANCIAL_INTERMEDIATION_ART182_XV', 'FINANCIAL_INTERMEDIATION'],
    ['VIRTUAL_ASSETS', 'VIRTUAL_ASSETS'], ['MUTUAL_ASSET_PROTECTION', 'MUTUAL_ASSET_PROTECTION'],
]);

it('qualifies listed nonsupervised providers and documented economic activity', function (string $category): void {
    $proof = financialScopeProof();
    $proof['supervision'] = 'NOT_SFN_SUPERVISED';
    $proof['provider_category'] = $category;
    $proof['activity_basis'] = 'PROFESSIONAL_ACTIVITY';
    $proof['activity_evidence_id'] = 'FIXTURE.ACTIVITY';
    $proof['correspondent_registration_evidence_id'] = 'FIXTURE.BCB.REGISTRATION';
    expect(financialScopeResult($proof)['regime'])->toBe('SPECIFIC_FINANCIAL_ART181');
})->with(['PAYMENT_OR_LOYALTY_PARTICIPANT', 'SECURITIZATION_COMPANY', 'FACTORING_COMPANY',
    'SIMPLE_CREDIT_COMPANY', 'BCB_CORRESPONDENT', 'OTHER_ECONOMIC_ACTIVITY_PROVIDER']);

it('selects general rules for regulated exclusive bank fees', function (): void {
    $proof = financialScopeProof();
    $proof['operation_category'] = 'BANK_EXCLUSIVE_FEE_SERVICE';
    $proof['bank_exclusive_by_regulation'] = true;
    $proof['paid_by_fee_or_commission'] = true;
    $proof['bank_exclusivity_regulatory_evidence_id'] = 'FIXTURE.EXCLUSIVITY';
    $result = financialScopeResult($proof);
    expect($result['regime'])->toBe('GENERAL_ART184')->and($result['base_family'])->toBeNull();
    $proof['provider_category'] = 'CREDIT_COOPERATIVE';
    expect(financialScopeResult($proof)['reason'])->toBe('RTC_FINANCIAL_SCOPE_BANK_GENERAL_EXCEPTION_CONFLICT');
});

it('selects general rules for payment account maintenance or closure only by a payment institution', function (string $account): void {
    $proof = financialScopeProof();
    $proof['operation_category'] = 'PAYMENT_ACCOUNT_MAINTENANCE_OR_CLOSURE';
    $proof['provider_category'] = 'PAYMENT_INSTITUTION';
    $proof['payment_account_kind'] = $account;
    $proof['paid_by_fee_or_commission'] = true;
    expect(financialScopeResult($proof)['regime'])->toBe('GENERAL_ART184');
})->with(['PREPAID', 'POSTPAID']);

it('keeps a proven nonfinancial service of a bank in general rules', function (): void {
    $proof = financialScopeProof();
    $proof['operation_category'] = 'OTHER_NON_FINANCIAL_SERVICE';
    $proof['not_art182_service'] = true;
    $proof['non_financial_scope_evidence_id'] = 'FIXTURE.SOFTWARE.SERVICE';
    expect(financialScopeResult($proof)['regime'])->toBe('GENERAL_ART184');
    unset($proof['non_financial_scope_evidence_id']);
    expect(financialScopeResult($proof)['reason'])->toBe('RTC_FINANCIAL_SCOPE_NON_FINANCIAL_SCOPE_UNPROVEN');
});

it('blocks missing or conflicting identity dates registry authorization and legal scope', function (string $failure, string $status, string $reason): void {
    $proof = financialScopeProof();
    switch ($failure) {
        case 'supplier': $proof['supplier_id'] = 'other';
            break;
        case 'document': $proof['document_id'] = 'other';
            break;
        case 'observation': $proof['observed_on'] = '2026-10-03';
            break;
        case 'expired': $proof['valid_to'] = '2026-09-30';
            break;
        case 'registry': unset($proof['supervision_registry_evidence_id']);
            break;
        case 'authorization': unset($proof['operation_authorization_evidence_id']);
            break;
        case 'health': $proof['operation_category'] = 'HEALTH_INSURANCE';
            break;
        case 'unknown-role': $proof['provider_category'] = 'FINTECH';
            break;
        case 'pension': $proof['provider_category'] = 'PENSION_ENTITY';
            break;
        case 'economic':
            $proof['supervision'] = 'NOT_SFN_SUPERVISED';
            $proof['provider_category'] = 'OTHER_ECONOMIC_ACTIVITY_PROVIDER';
            break;
        case 'unknown-supervision': unset($proof['supervision']);
            break;
    }
    $result = financialScopeResult($proof);
    expect($result['status'])->toBe($status)->and($result['reason'])->toBe('RTC_FINANCIAL_SCOPE_'.$reason);
})->with([
    ['supplier', 'INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT'], ['document', 'INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT'],
    ['observation', 'INVALID', 'PROOF_PERIOD_CONFLICT'], ['expired', 'ACTION_REQUIRED', 'PROOF_OUTSIDE_PERIOD'],
    ['registry', 'ACTION_REQUIRED', 'SUPERVISION_UNPROVEN'], ['authorization', 'ACTION_REQUIRED', 'OPERATION_AUTHORIZATION_UNPROVEN'],
    ['health', 'ACTION_REQUIRED', 'OPERATION_SCOPE_NOT_PROVEN'], ['unknown-role', 'ACTION_REQUIRED', 'SUPERVISION_UNPROVEN'],
    ['pension', 'ACTION_REQUIRED', 'PENSION_TAXPAYER_STATUS_UNPROVEN'], ['economic', 'ACTION_REQUIRED', 'ECONOMIC_ACTIVITY_UNPROVEN'],
    ['unknown-supervision', 'ACTION_REQUIRED', 'SUPERVISION_STATUS_UNPROVEN'],
]);
