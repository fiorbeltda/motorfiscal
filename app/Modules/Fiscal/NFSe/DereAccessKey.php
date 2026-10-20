<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use InvalidArgumentException;

/** Structural key construction only; cadastral and codBC catalog qualification are separate. */
final readonly class DereAccessKey
{
    public function group(string $declarantRoot, string $country, string $registrationType, string $registration, string $baseCode, LocalDate $first, LocalDate $last, int $series): string
    {
        if (! preg_match('/^[0-9A-Z]{8}$/', $declarantRoot) || ! preg_match('/^[0-9A-Z]{2}$/', $country)
            || ! in_array($registrationType, ['1', '2', '3', '4', '5', '9'], true)
            || ! preg_match('/^[0-9A-Z]+$/', $registration) || ! preg_match('/^[1-9][0-9A-Z]{3}$/', $baseCode)) {
            throw new InvalidArgumentException('Invalid DeRE key identifier structure.');
        }
        $size = match ($registrationType) {
            '1', '4' => 14, '2', '5' => 11, default => null,
        };
        if (($size !== null && strlen($registration) !== $size)
            || (in_array($registrationType, ['2', '5'], true) && ! ctype_digit($registration))) {
            throw new InvalidArgumentException('Registration length or CPF structure conflicts with registration type.');
        }
        if ($first->value() > $last->value() || substr($first->value(), 0, 7) !== substr($last->value(), 0, 7)
            || substr($first->value(), 0, 2) !== '20' || $series < 1 || $series > 9999) {
            throw new InvalidArgumentException('DeRE grouping requires an ordered single-month period and series1..9999.');
        }
        $registration = str_pad(substr($registration, -20), 20, '0', STR_PAD_LEFT);
        $period = str_replace('-', '', substr($first->value(), 0, 7)).substr($first->value(), 8, 2).substr($last->value(), 8, 2);
        $prefix = $declarantRoot.$country.$registrationType.$registration.$baseCode.$period.str_pad((string) $series, 4, '0', STR_PAD_LEFT);

        return $prefix.$this->digit($prefix).'000';
    }

    public function operation(string $groupKey, int $sequence): string
    {
        if (! $this->isStructurallyValid($groupKey) || substr($groupKey, -3) !== '000' || $sequence < 1 || $sequence > 999) {
            throw new InvalidArgumentException('Operation key requires a valid mother key and sequence1..999.');
        }

        return substr($groupKey, 0, 50).str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    public function isStructurallyValid(string $key): bool
    {
        if (! preg_match('/^[0-9A-Z]{10}[1-59][0-9A-Z]{20}[1-9][0-9A-Z]{3}20[0-9]{2}(0[1-9]|1[0-2])[0-9]{12}$/', $key)
            || strlen($key) !== 53 || (int) substr($key, 45, 4) === 0
            || substr($key, 49, 1) !== (string) $this->digit(substr($key, 0, 49))) {
            return false;
        }
        try {
            $month = substr($key, 35, 4).'-'.substr($key, 39, 2).'-';
            $first = LocalDate::fromString($month.substr($key, 41, 2));
            $last = LocalDate::fromString($month.substr($key, 43, 2));

            return $first->value() <= $last->value();
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function digit(string $prefix): int
    {
        $sum = 0;
        for ($index = 0; $index < 49; $index++) {
            $sum += (ord($prefix[48 - $index]) - 48) * (2 + $index % 8);
        }
        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
