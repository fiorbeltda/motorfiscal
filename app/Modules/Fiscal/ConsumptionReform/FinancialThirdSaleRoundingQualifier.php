<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;

/** Proves covered, uncovered and newly created rounding separately on a subsequent native sale. */
final readonly class FinancialThirdSaleRoundingQualifier
{
    public const METHOD = 'PROVED_THIRD_PERIOD_AVERAGE_RELEASE_WITH_COVERED_AND_UNCOVERED_ROUNDING_8_2';

    /** @param array<string,mixed> $input
     * @param  array<string,mixed>  $declaration
     * @param  array<string,mixed>  $sale
     * @param  array<string,mixed>  $memory
     * @param  array<string,mixed>  $history
     * @return array<string,mixed>
     */
    public function resolve(array $input, array $declaration, array $sale, array $memory, array $history,
        DateTimeImmutable $known, BigDecimal $total): array
    {
        $quantity = BigDecimal::of($sale['quantity'])->plus($sale['quantity_after']);
        $before = BigDecimal::of($sale['book_balance_after'])->plus($sale['book_amount']);
        $fractionQuantity = BigDecimal::of($sale['quantity']);
        $average = $before->multipliedBy($fractionQuantity)->dividedBy($quantity, 8, RoundingMode::HalfEven);
        $book = $average->toScale(2, RoundingMode::HalfEven);
        $covered = BigDecimal::of($history['opening_carrying_components']['fiscal_covered_rounding'])
            ->multipliedBy($fractionQuantity)->dividedBy($quantity, 8, RoundingMode::HalfEven);
        $uncovered = BigDecimal::of($history['opening_carrying_components']['uncovered_created_rounding'])
            ->multipliedBy($fractionQuantity)->dividedBy($quantity, 8, RoundingMode::HalfEven);
        $created = $book->minus($average)->toScale(8);
        if (! $book->isEqualTo($sale['book_amount']) || ! $covered->plus($uncovered)->plus($created)->isEqualTo($total)) {
            return $this->stop('THIRD_BOOK_RELEASE_ROUNDING_AMOUNT_CONFLICT');
        }
        $amounts = ['unrounded_average_release' => (string) $average, 'actual_book_release' => (string) $book,
            'fiscal_covered_rounding_consumed' => (string) $covered, 'uncovered_created_rounding_consumed' => (string) $uncovered,
            'created_rounding_adjustment' => (string) $created, 'total_rounding_release' => (string) $total->toScale(8)];
        $period = $input['position']['period'];
        $value = ['supplier_id' => $input['position']['supplier_id'], 'period' => $period, 'title' => $input['title'],
            'operation' => $sale, 'daily_memory_hash' => CanonicalJson::hash($memory), 'history_source_set_hash' => $history['source_set_hash'],
            'method' => self::METHOD, ...$amounts, 'normative_homologation_claimed' => false];
        if (! (new FinancialPublicOfferDebtQualifier)->proof($declaration['book_release_rounding_evidence'] ?? null, $value,
            $input['position']['supplier_id'], $period.'-01', (new DateTimeImmutable($period.'-01'))->format('Y-m-t'), $known)) {
            return $this->stop('INDEPENDENT_THIRD_BOOK_RELEASE_ROUNDING_UNPROVEN');
        }

        return ['status' => 'RESOLVED', 'method' => self::METHOD, ...$amounts,
            'existing_rounding_consumed' => (string) $covered->plus($uncovered)->toScale(8),
            'evidence_hash' => $declaration['book_release_rounding_evidence']['source_hash'], 'posting_performed' => false];
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_TVM_REALIZATION_'.$reason, 'production_ready' => false];
    }
}
