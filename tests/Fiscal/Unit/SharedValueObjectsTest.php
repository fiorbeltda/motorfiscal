<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Formula\PercentageOfBaseFormula;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Core\Shared\Money;

it('rejects invalid local dates', function (): void {
    LocalDate::fromString('2026-02-30');
})->throws(InvalidArgumentException::class);

it('calculates fiscal percentages without floats', function (): void {
    $amount = Money::brl('1000.00')->percentage(DecimalValue::fromString('2.75'));

    expect($amount->amount())->toBe('27.50');
});

it('executes allowlisted formulas with deterministic rounding', function (): void {
    $formula = new PercentageOfBaseFormula;

    expect($formula->calculate(['base' => '199.99', 'rate' => '3.00']))->toBe('6.00');
});
