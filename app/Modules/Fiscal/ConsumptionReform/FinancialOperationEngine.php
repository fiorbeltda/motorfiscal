<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/** Links legal operation qualification to the correct monthly base without assigning tax or document codes. */
final readonly class FinancialOperationEngine
{
    /** @param array<string, mixed> $proof
     * @param  array<string, mixed>|null  $ledger
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function resolve(array $proof, ?array $ledger, string $supplierId, string $documentId, LocalDate $on, DateTimeImmutable $knownAt, array $rules): array
    {
        $qualification = (new FinancialRegimeQualifier)->resolve($proof, $supplierId, $documentId, $on, $knownAt, $rules['scope'] ?? []);
        if ($qualification['status'] !== 'RESOLVED') {
            return $qualification;
        }
        if ($qualification['regime'] === 'GENERAL_ART184') {
            if ($ledger !== null) {
                return ['status' => 'INVALID', 'reason' => 'RTC_FINANCIAL_GENERAL_RULES_WITH_SPECIFIC_BASE_CONFLICT'];
            }

            return ['status' => 'RESOLVED', 'reason' => 'RTC_FINANCIAL_GENERAL_OPERATION_QUALIFIED',
                'qualification' => $qualification, 'base' => null, 'scope' => 'GENERAL_OPERATION_USE_GENERAL_TAX_ENGINE'];
        }
        if ($ledger === null) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_FINANCIAL_QUALIFIED_LEDGER_MISSING'];
        }
        if (($ledger['supplier_id'] ?? null) !== $supplierId || ($ledger['scope_document_id'] ?? null) !== $documentId
            || ($ledger['family'] ?? null) !== $qualification['base_family']) {
            return ['status' => 'INVALID', 'reason' => 'RTC_FINANCIAL_OPERATION_LEDGER_SCOPE_CONFLICT'];
        }
        $base = (new FinancialBaseEngine)->resolve($ledger, $on, $knownAt, $rules['base'] ?? []);
        if ($base['status'] !== 'RESOLVED') {
            return $base;
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_FINANCIAL_OPERATION_AND_BASE_QUALIFIED',
            'qualification' => $qualification, 'base' => $base,
            'scope' => 'QUALIFIED_OPERATION_AND_BASE_NO_TAX_CREDIT_OR_DOCUMENT_APPROVAL'];
    }
}
