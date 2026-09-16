<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Shared;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final readonly class DecimalValue
{
    private function __construct(private BigDecimal $value) {}

    public static function fromString(string $value): self
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Invalid decimal value: {$value}");
        }

        return new self(BigDecimal::of($value));
    }

    public function multipliedBy(self $other): self
    {
        return new self($this->value->multipliedBy($other->value));
    }

    public function dividedBy(self $other, int $scale = 10): self
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Decimal scale cannot be negative.');
        }

        return new self($this->value->dividedBy($other->value, $scale, RoundingMode::HalfUp));
    }

    public function rounded(int $scale): self
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Decimal scale cannot be negative.');
        }

        return new self($this->value->toScale($scale, RoundingMode::HalfUp));
    }

    public function compareTo(self $other): int
    {
        return $this->value->compareTo($other->value);
    }

    public function value(): string
    {
        return (string) $this->value;
    }

    public function __toString(): string
    {
        return $this->value();
    }
}
