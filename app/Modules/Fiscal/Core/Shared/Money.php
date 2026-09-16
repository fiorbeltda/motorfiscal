<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Shared;

final readonly class Money
{
    private function __construct(
        private DecimalValue $amount,
        private string $currency,
    ) {}

    public static function brl(string $amount): self
    {
        return new self(DecimalValue::fromString($amount)->rounded(2), 'BRL');
    }

    public function percentage(DecimalValue $rate): self
    {
        $factor = $rate->dividedBy(DecimalValue::fromString('100'));

        return new self($this->amount->multipliedBy($factor)->rounded(2), $this->currency);
    }

    public function amount(): string
    {
        return $this->amount->value();
    }

    public function currency(): string
    {
        return $this->currency;
    }
}
