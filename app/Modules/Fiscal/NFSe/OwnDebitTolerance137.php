<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Draft XML validation primitive;preserves the assessed debit and its price bridge. */
final readonly class OwnDebitTolerance137
{
    /**
     * @return array<string, mixed>
     */
    public function compare(mixed $base, mixed $rate, mixed $amount, string $treatment): array
    {
        if ($treatment === 'NON_INCIDENCE') {
            if ($base !== null || $rate !== null || $amount !== '0.00') {
                return $this->stop('NON_INCIDENCE_REQUIRES_ABSENT_BASE_RATE_AND_ZERO_DEBIT');
            }

            return ['status' => 'STAGED_NON_INCIDENCE_NO_NUMERIC_BASE_OR_RATE', 'canonical_half_even_reference' => null,
                'value_preserved' => '0.00', 'amount_rewritten' => false, 'eligible_for_integration' => false];
        }
        if ($treatment !== 'TAXABLE' || ! $this->money($base) || ! $this->money($amount)
            || ! is_string($rate) || preg_match('/\A(?:0|[1-9][0-9]?)\.[0-9]{2}\z/', $rate) !== 1) {
            return $this->stop('EXACT_CURRENT_SCHEMA_BASE_RATE_AND_DEBIT_REQUIRED');
        }
        $raw = BigDecimal::of($base)->multipliedBy($rate)->dividedBy('100', 6, RoundingMode::Unnecessary);
        $canonical = $raw->toScale(2, RoundingMode::HalfEven);
        $difference = BigDecimal::of($amount)->minus($canonical)->abs()->toScale(2, RoundingMode::Unnecessary);
        if ($difference->compareTo('0.01') > 0) {
            return [...$this->stop('DEBIT_EXCEEDS_ONE_CENT_TOLERANCE'),
                'canonical_half_even_reference' => (string) $canonical, 'absolute_difference' => (string) $difference];
        }

        return ['status' => 'STAGED_OWN_DEBIT_WITHIN_HALF_EVEN_TOLERANCE', 'raw_reference' => (string) $raw,
            'canonical_half_even_reference' => (string) $canonical, 'absolute_difference' => (string) $difference,
            'value_preserved' => $amount, 'amount_rewritten' => false, 'price_bridge_rewritten' => false,
            'eligible_for_integration' => false];
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', $value) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'TOLERANCE137_'.$reason, 'amount_rewritten' => false, 'eligible_for_integration' => false];
    }
}
