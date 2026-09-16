<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Formula;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\Money;
use InvalidArgumentException;

final class PercentageOfBaseFormula implements Formula
{
    public function id(): string
    {
        return 'PERCENTAGE_OF_BASE';
    }

    public function calculate(array $arguments): string
    {
        if (! isset($arguments['base'], $arguments['rate'])) {
            throw new InvalidArgumentException('PERCENTAGE_OF_BASE requires base and rate.');
        }

        return Money::brl($arguments['base'])
            ->percentage(DecimalValue::fromString($arguments['rate']))
            ->amount();
    }
}
