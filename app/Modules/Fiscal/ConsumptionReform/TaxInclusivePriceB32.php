<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use Closure;

/**
 * B32: LC 214 art. 12 §2, I — the IBS and CBS on the operation are not part of their own base. When the charged
 * price already embeds them, the base B is the largest amount in cents such that B + tax(B) equals the charged
 * amount, using the engine's own per-tax HALF_UP rounding. If no cent satisfies the identity exactly (rounding gap),
 * the engine asks instead of choosing a value.
 */
final readonly class TaxInclusivePriceB32
{
    public const KEY = 'tax_inclusive_price_b32';

    public const POLICY = ['version' => 'B32', 'source' => 'LC214 art. 12 §2º, I', 'method' => 'EXACT_CENT_SEARCH_HALF_UP_2'];

    /** @param array<string, mixed> $codes */
    public static function enabled(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null)
            && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY);
    }

    /**
     * @param  list<string>  $percents  state, municipal and CBS rates in percent
     * @param  Closure(DecimalValue, string): string  $tax  engine rounding for one tax
     * @return array<string, mixed>
     */
    public function resolve(string $charged, array $percents, Closure $tax): array
    {
        $gross = DecimalValue::fromString($charged);
        $rate = DecimalValue::fromString('0');
        foreach ($percents as $percent) {
            $rate = $rate->plus(DecimalValue::fromString($percent));
        }
        $estimate = $gross->multipliedBy(DecimalValue::fromString('100'))
            ->dividedBy(DecimalValue::fromString('100')->plus($rate), 10)->rounded(2);
        $cent = DecimalValue::fromString('0.01');
        $found = null;
        // The exact base, if it exists, lies within a few cents of the estimate; search from above to keep the largest.
        for ($offset = 3; $offset >= -3; $offset--) {
            $candidate = $estimate->plus($cent->multipliedBy(DecimalValue::fromString((string) $offset)))->rounded(2);
            if ($candidate->isNegative()) {
                continue;
            }
            $total = $candidate;
            foreach ($percents as $percent) {
                $total = $total->plus(DecimalValue::fromString($tax($candidate, $percent)));
            }
            if ($total->rounded(2)->compareTo($gross->rounded(2)) === 0) {
                $found = $candidate;
                break;
            }
        }
        if ($found === null) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_TAX_INCLUSIVE_PRICE_ROUNDING_GAP', 'charged' => $gross->rounded(2)->value(),
                'combined_rate_percent' => $rate->value()];
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_TAX_INCLUSIVE_PRICE_SPLIT', 'source_article' => self::POLICY['source'],
            'charged' => $gross->rounded(2)->value(), 'combined_rate_percent' => $rate->value(),
            'taxable_base' => $found->value(), 'embedded_taxes' => $gross->minus($found)->rounded(2)->value(),
            'method' => self::POLICY['method']];
    }
}
