<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use InvalidArgumentException;

/** A supplied future installment is an allocation, never a financial transaction. */
final readonly class SplitPlannedInstallment159
{
    public const CONTRACT = 'RTC-SPLIT-PLANNED-INSTALLMENT-A159-V1';

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        try {
            return $this->qualify($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(),
                'official' => false, 'posted' => false, 'payment_performed' => false];
        }
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function qualify(array $input): array
    {
        $required = ['contract', 'currency', 'source_kind', 'source_hash', 'evidence_id', 'input_hash',
            'operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'transaction_id',
            'known_at', 'operation_at', 'planned_initiation_at', 'original_amount', 'taxes', 'installment'];
        $this->require(count($input) === count($required) && array_diff($required, array_keys($input)) === [], 'A159_PLANNED_FIELDS_REQUIRED');
        $this->require($input['contract'] === self::CONTRACT && $input['currency'] === 'BRL', 'A159_PLANNED_CONTRACT_BRL_REQUIRED');
        $this->require(in_array($input['source_kind'], ['SYNTHETIC_TEST', 'DOCUMENTARY_INPUT'], true), 'A159_PLANNED_PROVENANCE_REQUIRED');
        $body = $input;
        unset($body['input_hash']);
        $this->require($input['input_hash'] === CanonicalJson::hash($body), 'A159_PLANNED_HASH_CONFLICT');
        foreach (['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'transaction_id', 'evidence_id'] as $key) {
            $this->require(is_string($input[$key]) && trim($input[$key]) === $input[$key]
                && (new CreditEvidence156)->reference($input[$key])
                && preg_match('/\A\p{Z}|\p{Z}\z/u', $input[$key]) === 0, 'A159_PLANNED_ID_REQUIRED');
        }
        $this->require(is_string($input['source_hash']) && preg_match('/^[a-f0-9]{64}$/D', $input['source_hash']) === 1, 'A159_PLANNED_SOURCE_HASH_REQUIRED');
        $known = $this->instant($input['known_at']);
        $operation = $this->instant($input['operation_at']);
        $planned = $this->instant($input['planned_initiation_at']);
        $this->require($operation <= $known && $planned > $known, 'A159_PLANNED_DATE_CONFLICT');
        $original = $this->money($input['original_amount']);
        $this->require($original->isPositive(), 'A159_PLANNED_POSITIVE_AMOUNT_REQUIRED');
        $taxes = $input['taxes'];
        $this->require(is_array($taxes) && count($taxes) === 2
            && array_key_exists('IBS', $taxes) && array_key_exists('CBS', $taxes), 'A159_PLANNED_TWO_TAXES_REQUIRED');
        $this->require($this->money($taxes['IBS'])->plus($this->money($taxes['CBS']))->compareTo($original) <= 0, 'A159_PLANNED_TAX_SUM_CONFLICT');
        $installment = $input['installment'];
        $this->require(is_array($installment) && is_int($installment['number'] ?? null) && is_int($installment['count'] ?? null)
            && $installment['number'] >= 1 && $installment['number'] <= $installment['count']
            && ($installment['operation_id'] ?? null) === $input['operation_id']
            && ($installment['document_id'] ?? null) === $input['document_id']
            && ($installment['original_amount'] ?? null) === $input['original_amount']
            && CanonicalJson::hash($installment['taxes'] ?? null) === CanonicalJson::hash($taxes)
            && is_string($installment['allocation_evidence_id'] ?? null)
            && (new CreditEvidence156)->reference($installment['allocation_evidence_id']), 'A159_PLANNED_ALLOCATION_REQUIRED');

        return ['status' => 'LOCAL_SPLIT_INSTALLMENT_PLANNED', 'input' => $input,
            'financial_state' => 'NOT_INITIATED', 'settled_at' => null, 'segregation' => null,
            'official' => false, 'posted' => false, 'payment_performed' => false, 'extinction_proven' => false];
    }

    private function money(mixed $value): BigDecimal
    {
        $this->require(is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1, 'A159_PLANNED_MONEY_REQUIRED');

        return BigDecimal::of($value);
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $this->require(is_string($value) && preg_match('/^\d{4}-\d\d-\d\dT(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value) === 1, 'A159_PLANNED_TIMESTAMP_REQUIRED');
        try {
            $date = new DateTimeImmutable($value);
        } catch (\Exception) {
            throw new InvalidArgumentException('A159_PLANNED_TIMESTAMP_INVALID');
        }
        $this->require(DateTimeImmutable::getLastErrors() === false, 'A159_PLANNED_TIMESTAMP_INVALID');

        return $date;
    }

    /** @phpstan-assert true $condition */
    private function require(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($reason);
        }
    }
}
