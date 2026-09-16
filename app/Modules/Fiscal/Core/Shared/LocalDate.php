<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Shared;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class LocalDate
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Invalid local date: {$value}");
        }

        return new self($value);
    }

    public function toDateTimeImmutable(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->value.' 00:00:00');
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
