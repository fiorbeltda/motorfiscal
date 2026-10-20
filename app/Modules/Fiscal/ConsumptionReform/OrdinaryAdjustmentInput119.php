<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/** Validate nested values before any monetary cast; never repair or deduplicate input. */
final readonly class OrdinaryAdjustmentInput119
{
    /** @return array<string, mixed> */
    public function qualify(mixed $adjustments): array
    {
        if (! is_array($adjustments) || ! array_is_list($adjustments)) {
            return $this->stop('RTC_ADJUSTMENTS_INVALID', 'INVALID');
        }
        $contexts = [];
        foreach ($adjustments as $index => $row) {
            if (! is_array($row) || ! isset($row['kind'], $row['amount'], $row['evidence_id'])) {
                return $this->stop('RTC_ADJUSTMENT_EVIDENCE_MISSING', index: $index);
            }
            $amount = $row['amount'];
            if ((! is_string($amount) && ! is_int($amount))
                || preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', (string) $amount) !== 1) {
                return $this->stop('RTC_ADJUSTMENT119_AMOUNT_NOT_EXACT', 'INVALID', $index);
            }
            if (! $this->identifier($row['evidence_id'])) {
                return $this->stop('RTC_ADJUSTMENT119_EVIDENCE_EMPTY', index: $index);
            }
            if (! in_array($row['kind'], ['INCLUDED_CHARGE', 'UNCONDITIONAL_DISCOUNT',
                'DOCUMENTED_REIMBURSEMENT', 'LEGACY_TAX_EXCLUSION'], true)) {
                return $this->stop('RTC_ADJUSTMENT_KIND_UNKNOWN', 'INVALID', $index);
            }
            if ($row['kind'] === 'UNCONDITIONAL_DISCOUNT' && ! $this->identifier($row['document_id'] ?? null)) {
                return $this->stop('RTC_UNCONDITIONAL_DISCOUNT_PROOF_MISSING', index: $index);
            }
            if ($row['kind'] === 'DOCUMENTED_REIMBURSEMENT'
                && (! $this->identifier($row['third_party_id'] ?? null)
                    || ! $this->identifier($row['third_party_document_id'] ?? null))) {
                return $this->stop('RTC_THIRD_PARTY_REIMBURSEMENT_PROOF_MISSING', index: $index);
            }
            if (array_key_exists('line_reference', $row) && ! $this->identifier($row['line_reference'])) {
                return $this->stop('RTC_ADJUSTMENT119_LINE_REFERENCE_INVALID', index: $index);
            }
            // Same document may support several rows. Require explicit distinct identities in that context.
            $context = ['evidence_id' => trim($row['evidence_id']), 'kind' => $row['kind']];
            $contextKeys = match ($row['kind']) {
                'UNCONDITIONAL_DISCOUNT' => ['document_id'],
                'DOCUMENTED_REIMBURSEMENT' => ['third_party_id', 'third_party_document_id'],
                'LEGACY_TAX_EXCLUSION' => ['tax'],
                default => [],
            };
            foreach ($contextKeys as $key) {
                $value = $row[$key] ?? null;
                if ($value !== null && ! is_string($value)) {
                    return $this->stop('RTC_ADJUSTMENT119_CONTEXT_INVALID', index: $index);
                }
                $context[$key] = is_string($value) ? trim($value) : null;
            }
            $contexts[CanonicalJson::hash($context)][] = ['index' => $index,
                'line' => isset($row['line_reference']) ? trim($row['line_reference']) : null];
        }
        foreach ($contexts as $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $seen = [];
            foreach ($rows as $row) {
                if ($row['line'] === null || in_array($row['line'], $seen, true)) {
                    return $this->stop('RTC_ADJUSTMENT119_LINE_IDENTITY_AMBIGUOUS', index: $row['index']);
                }
                $seen[] = $row['line'];
            }
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_ADJUSTMENT119_INPUT_QUALIFIED',
            'row_count' => count($adjustments), 'input_hash' => CanonicalJson::hash($adjustments),
            'evidence_authenticity_proven' => false, 'legal_applicability_approved' => false];
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED', ?int $index = null): array
    {
        return ['status' => $status, 'reason' => $reason, 'row_index' => $index,
            'evidence_authenticity_proven' => false, 'legal_applicability_approved' => false];
    }
}
