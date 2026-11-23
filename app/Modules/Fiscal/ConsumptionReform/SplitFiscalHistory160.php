<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use InvalidArgumentException;

/** Supplied PSP fiscal revisions and explicit return resets; not the public platform protocol. */
final readonly class SplitFiscalHistory160
{
    /** @param array<string, mixed> $input
     * @return array{revisions: array<string, array<string, mixed>>, active: string, trace: list<array<string, mixed>>}
     */
    public function qualify(array $input, DateTimeImmutable $cutoff, DateTimeImmutable $known, DateTimeImmutable $initiated, ?DateTimeImmutable $closed): array
    {
        $rows = $input['fiscal_revisions'] ?? null;
        $this->require(($input['fiscal_revision_history_complete'] ?? null) === true
            && is_array($rows) && array_is_list($rows) && $rows !== [], 'A160_COMPLETE_FISCAL_HISTORY_REQUIRED');
        $this->require(count($rows) === 1 || in_array($input['arrangement'] ?? null, ['BOLETO', 'PIX_DYNAMIC'], true), 'A160_ARRANGEMENT_UPDATE_NOT_ALLOWED');
        $this->require(($input['fiscal_inputs_unchanged'] ?? null) === (count($rows) === 1), 'A160_STABILITY_DECLARATION_CONFLICT');
        $ordered = [];
        foreach ($rows as $row) {
            $this->require(is_array($row) && is_int($row['sequence'] ?? null)
                && $row['sequence'] > 0 && ! isset($ordered[$row['sequence']]), 'A160_FISCAL_SEQUENCE_CONFLICT');
            $ordered[$row['sequence']] = $row;
        }
        ksort($ordered);
        $revisions = [];
        $trace = [];
        $previousAt = null;
        $previousData = null;
        $active = null;
        foreach (array_values($ordered) as $index => $row) {
            $fields = ['sequence', 'version', 'effective_at', 'document_id', 'document_issued_at', 'document_linked_at', 'original_amount', 'taxes',
                'reset_returns', 'reset_evidence_id', 'evidence_id', 'source_hash'];
            $this->require(count($row) === count($fields) && array_diff($fields, array_keys($row)) === []
                && $row['sequence'] === $index + 1, 'A160_FISCAL_FIELDS_OR_GAP_CONFLICT');
            $this->require($this->identifier($row['version']) && $this->identifier($row['document_id'])
                && $this->identifier($row['evidence_id']) && ! isset($revisions['#'.$row['version']]), 'A160_FISCAL_ID_CONFLICT');
            $this->require(is_string($row['source_hash']) && preg_match('/^[a-f0-9]{64}$/D', $row['source_hash']) === 1, 'A160_FISCAL_SOURCE_HASH_REQUIRED');
            $at = $this->instant($row['effective_at']);
            $issued = $this->instant($row['document_issued_at']);
            $linked = $this->instant($row['document_linked_at']);
            $this->require($at <= $known && ($closed === null || $at <= $closed)
                && $issued <= $linked && $linked <= $known
                && ($previousAt === null ? $at == $initiated : $at > $previousAt), 'A160_FISCAL_DATE_CONFLICT');
            $original = $this->money($row['original_amount']);
            $taxes = $row['taxes'];
            $this->require($original->isPositive() && is_array($taxes) && count($taxes) === 2
                && array_key_exists('IBS', $taxes) && array_key_exists('CBS', $taxes), 'A160_FISCAL_AMOUNTS_REQUIRED');
            $this->require($this->money($taxes['IBS'])->plus($this->money($taxes['CBS']))->compareTo($original) <= 0, 'A160_FISCAL_TAX_SUM_CONFLICT');
            $data = CanonicalJson::hash([$row['document_id'], $row['original_amount'], $taxes]);
            $this->require($index === 0 ? ($row['reset_returns'] === false && $row['reset_evidence_id'] === null)
                : ($row['reset_returns'] === true && $this->identifier($row['reset_evidence_id']) && $data !== $previousData), 'A160_FISCAL_RESET_PROOF_REQUIRED');
            $revisions['#'.$row['version']] = $row;
            if ($at <= $cutoff) {
                $active = $row['version'];
            }
            $trace[] = ['version' => $row['version'], 'sequence' => $row['sequence'], 'effective_at' => $row['effective_at'],
                'reset_returns' => $row['reset_returns'], 'processed_by_cutoff' => $at <= $cutoff];
            $previousAt = $at;
            $previousData = $data;
        }
        $this->require(is_string($active), 'A160_NO_FISCAL_REVISION_AT_CUTOFF');
        $selected = $revisions['#'.$active];
        $this->require(($input['fiscal_input_version'] ?? null) === $active
            && ($input['document_id'] ?? null) === $selected['document_id']
            && $this->instant($input['document_issued_at'] ?? null) == $this->instant($selected['document_issued_at'])
            && $this->instant($input['document_link']['linked_at'] ?? null) == $this->instant($selected['document_linked_at'])
            && ($input['original_amount'] ?? null) === $selected['original_amount']
            && CanonicalJson::hash($input['taxes'] ?? null) === CanonicalJson::hash($selected['taxes']), 'A160_FISCAL_SNAPSHOT_CONFLICT');

        return ['revisions' => $revisions, 'active' => $active, 'trace' => $trace];
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && trim($value) === $value && (new CreditEvidence156)->reference($value)
            && preg_match('/\A\p{Z}|\p{Z}\z/u', $value) === 0;
    }

    private function money(mixed $value): BigDecimal
    {
        $this->require(is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1, 'A160_FISCAL_MONEY_REQUIRED');

        return BigDecimal::of($value);
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $this->require(is_string($value) && preg_match('/^\d{4}-\d\d-\d\dT(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value) === 1, 'A160_FISCAL_TIMESTAMP_REQUIRED');
        try {
            $at = new DateTimeImmutable($value);
        } catch (\Exception) {
            throw new InvalidArgumentException('A160_FISCAL_TIMESTAMP_INVALID');
        }
        $this->require(DateTimeImmutable::getLastErrors() === false, 'A160_FISCAL_TIMESTAMP_INVALID');

        return $at;
    }

    /** @phpstan-assert true $condition */
    private function require(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($reason);
        }
    }
}
