<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use InvalidArgumentException;

/** Validation for local supplied legal split records; it does not authenticate an authority. */
final readonly class SplitLegalEvidence162
{
    public const LAW_HASH = 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde';

    /** @phpstan-assert true $condition */
    public function must(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($reason);
        }
    }

    public function id(mixed $value): string
    {
        $this->must(is_string($value) && trim($value) === $value && (new CreditEvidence156)->reference($value)
            && preg_match('/\A\p{Z}|\p{Z}\z/u', $value) === 0, 'A162_IDENTIFIER_REQUIRED');

        return $value;
    }

    /** @param array<string, mixed> $record */
    public function proof(array $record): void
    {
        $this->id($record['evidence_id'] ?? null);
        $this->must(is_string($record['source_hash'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $record['source_hash']) === 1, 'A162_SOURCE_HASH_REQUIRED');
    }

    /** @param array<string, mixed> $record
     * @param  list<string>  $fields
     */
    public function fields(array $record, array $fields): void
    {
        $this->must(count($record) === count($fields) && array_diff($fields, array_keys($record)) === [], 'A162_RECORD_FIELDS_REQUIRED');
    }

    /** @param array<string, mixed> $input
     * @param  list<string>  $fields
     */
    public function envelope(array $input, array $fields, string $contract): void
    {
        $this->must(count($input) === count($fields) && array_diff($fields, array_keys($input)) === [], 'A162_ENVELOPE_FIELDS_REQUIRED');
        $this->must(($input['contract'] ?? null) === $contract && ($input['currency'] ?? null) === 'BRL', 'A162_CONTRACT_BRL_REQUIRED');
        $this->must(in_array($input['source_kind'] ?? null, ['SYNTHETIC_TEST', 'DOCUMENTARY_INPUT'], true), 'A162_PROVENANCE_REQUIRED');
        $this->proof($input);
        $body = $input;
        unset($body['input_hash']);
        $this->must(($input['input_hash'] ?? null) === CanonicalJson::hash($body), 'A162_INPUT_HASH_CONFLICT');
    }

    public function money(mixed $value): BigDecimal
    {
        $this->must(is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1, 'A162_CANONICAL_MONEY_REQUIRED');

        return BigDecimal::of($value);
    }

    /** @return array{IBS: BigDecimal, CBS: BigDecimal} */
    public function taxes(mixed $value): array
    {
        $this->must(is_array($value) && count($value) === 2 && array_key_exists('IBS', $value) && array_key_exists('CBS', $value), 'A162_BOTH_TAXES_REQUIRED');

        return ['IBS' => $this->money($value['IBS']), 'CBS' => $this->money($value['CBS'])];
    }

    public function date(mixed $value): string
    {
        $this->must(is_string($value) && (new CreditEvidence156)->date($value), 'A162_DATE_REQUIRED');

        return $value;
    }

    public function instant(mixed $value): DateTimeImmutable
    {
        $this->must(is_string($value) && preg_match('/^\d{4}-\d\d-\d\dT(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value) === 1, 'A162_TIMESTAMP_REQUIRED');
        try {
            $at = new DateTimeImmutable($value);
        } catch (\Exception) {
            throw new InvalidArgumentException('A162_TIMESTAMP_INVALID');
        }
        $this->must(DateTimeImmutable::getLastErrors() === false, 'A162_TIMESTAMP_INVALID');

        return $at;
    }

    /** @param array<string, mixed> $record */
    public function lifecycle(array $record, string $on, DateTimeImmutable $known): void
    {
        $this->proof($record);
        $this->must(($record['status'] ?? null) === 'ACTIVE'
            && $this->instant($record['published_at'] ?? null) <= $known
            && $this->date($record['valid_from'] ?? null) <= $on
            && (($record['valid_until'] ?? null) === null || $on < $this->date($record['valid_until'])), 'A162_PARAMETER_NOT_APPLICABLE');
        foreach (['revoked_at', 'superseded_at'] as $key) {
            $this->must(($record[$key] ?? null) === null || $this->instant($record[$key]) > $known, 'A162_PARAMETER_REVOKED_OR_SUPERSEDED');
        }
    }
}
