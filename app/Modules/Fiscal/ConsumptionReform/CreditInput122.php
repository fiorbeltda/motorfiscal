<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/** Structural credit evidence only; never authenticates a document or grants a credit. */
final readonly class CreditInput122
{
    /** @param array<string, mixed> $facts
     * @return array<string, mixed>
     */
    public function qualify(array $facts, string $market): array
    {
        if (! $this->identifier($facts['rtc.input_document_id'] ?? null)) {
            return $this->stop('RTC_CREDIT122_DOCUMENT_ID_REQUIRED', missing: ['rtc.input_document_id']);
        }
        if ($market === 'IMPORT') {
            foreach (['rtc.import_document_id', 'rtc.import_credit_fiscal_document_id', 'rtc.import_credit_extinction_evidence_id'] as $key) {
                // Absence is handled by the existing, treatment-sensitive import conditions.
                if (isset($facts[$key]) && ! $this->identifier($facts[$key])) {
                    return $this->stop('RTC_CREDIT122_IMPORT_IDENTIFIER_INVALID', missing: [$key]);
                }
            }
        }
        $adjustments = $facts['rtc.credit_adjustments'] ?? null;
        if ($adjustments === null) {
            return $this->stop('RTC_CREDIT_EVIDENCE_MISSING', missing: ['rtc.credit_adjustments']);
        }
        if (! is_array($adjustments) || ! array_is_list($adjustments)) {
            return $this->stop('RTC_CREDIT122_ADJUSTMENTS_NOT_LIST', 'INVALID');
        }
        $contexts = [];
        foreach ($adjustments as $index => $row) {
            if (! is_array($row) || ! isset($row['tax'], $row['kind'], $row['amount'], $row['evidence_id'])
                || ! in_array($row['kind'], ['REVERSAL', 'DISALLOWANCE'], true)) {
                return $this->stop('RTC_CREDIT_ADJUSTMENT_UNPROVEN', index: $index);
            }
            if (! in_array($row['tax'], ['IBS', 'CBS'], true)) {
                return $this->stop('RTC_CREDIT_TAX_UNKNOWN', index: $index);
            }
            $amount = $row['amount'];
            if ((! is_string($amount) && ! is_int($amount))
                || preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', (string) $amount) !== 1) {
                return $this->stop('RTC_CREDIT122_AMOUNT_NOT_EXACT', 'INVALID', $index);
            }
            if (! $this->identifier($row['evidence_id'])) {
                return $this->stop('RTC_CREDIT122_EVIDENCE_REQUIRED', index: $index);
            }
            if (array_key_exists('line_reference', $row) && ! $this->identifier($row['line_reference'])) {
                return $this->stop('RTC_CREDIT122_LINE_REFERENCE_INVALID', index: $index);
            }
            // Caller-added unrelated fields cannot create another evidence context.
            $context = CanonicalJson::hash(['document_id' => trim($facts['rtc.input_document_id']),
                'tax' => $row['tax'], 'kind' => $row['kind'], 'evidence_id' => trim($row['evidence_id'])]);
            $contexts[$context][] = ['index' => $index,
                'line' => isset($row['line_reference']) ? trim($row['line_reference']) : null];
        }
        foreach ($contexts as $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $seen = [];
            foreach ($rows as $row) {
                if ($row['line'] === null || in_array($row['line'], $seen, true)) {
                    return $this->stop('RTC_CREDIT122_LINE_IDENTITY_AMBIGUOUS', index: $row['index']);
                }
                $seen[] = $row['line'];
            }
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_CREDIT122_INPUT_QUALIFIED', 'missing_facts' => [],
            'row_count' => count($adjustments), 'input_hash' => CanonicalJson::hash($facts),
            'credit_granted' => false, 'document_authenticated' => false, 'extinction_proven' => false];
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /** Correction facts for existing arithmetic refusals; only used by the122 engine path.
     * @param  list<string>  $missing
     * @return list<string>
     */
    public function correctionFacts(string $reason, array $missing): array
    {
        if ($missing !== []) {
            return $missing;
        }

        return match ($reason) {
            'RTC_NEGATIVE_INPUT_CREDIT' => ['rtc.input_ibs_amount', 'rtc.input_cbs_amount'],
            'RTC_CREDIT_ADJUSTMENT_UNPROVEN', 'RTC_NEGATIVE_CREDIT_ADJUSTMENT',
            'RTC_CREDIT_TAX_UNKNOWN', 'RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE' => ['rtc.credit_adjustments'],
            default => [],
        };
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED', ?int $index = null, array $missing = []): array
    {
        if ($status === 'ACTION_REQUIRED' && $index !== null && $missing === []) {
            // The collection owns row corrections; retain the exact row index in the trace.
            $missing = ['rtc.credit_adjustments'];
        }

        return ['status' => $status, 'reason' => $reason, 'row_index' => $index, 'missing_facts' => $missing,
            'credit_granted' => false, 'document_authenticated' => false, 'extinction_proven' => false];
    }
}
