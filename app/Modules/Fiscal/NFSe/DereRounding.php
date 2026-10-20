<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/** DeRE annexII rounding; preserved general and historical fiscal calculations are unchanged. */
final readonly class DereRounding
{
    public function intermediate(string $value): string
    {
        return (string) $this->decimal($value)->toScale(8, RoundingMode::HalfEven);
    }

    public function finalAmount(string $value): string
    {
        return (string) $this->decimal($value)->toScale(2, RoundingMode::HalfEven);
    }

    public function taxAmount(string $base, string $percent): string
    {
        $amount = $this->decimal($base);
        $rate = $this->decimal($percent);
        if ($amount->isNegative() || $rate->isNegative() || $rate->isGreaterThan('100')) {
            throw new InvalidArgumentException('Nonnegative DeRE base and percent0..100 required.');
        }

        return (string) $amount->multipliedBy($rate)->dividedBy('100', 8, RoundingMode::HalfEven)->toScale(2, RoundingMode::HalfEven);
    }

    private function decimal(string $value): BigDecimal
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Decimal string required.');
        }

        return BigDecimal::of($value);
    }
}
