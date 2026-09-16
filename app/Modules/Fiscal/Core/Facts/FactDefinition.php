<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

use App\Modules\Fiscal\Core\Shared\LocalDate;

final readonly class FactDefinition
{
    /** @param list<string> $allowedValues */
    public function __construct(
        public string $key,
        public FactValueType $type,
        public bool $material = true,
        public array $allowedValues = [],
    ) {}

    public function accepts(mixed $value): bool
    {
        return match ($this->type) {
            FactValueType::BOOLEAN => is_bool($value),
            FactValueType::STRING, FactValueType::CODE => is_string($value),
            FactValueType::INTEGER => is_int($value),
            FactValueType::DECIMAL => is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1,
            FactValueType::DATE => is_string($value) && $this->isValidDate($value),
            FactValueType::ENUM => is_string($value) && in_array($value, $this->allowedValues, true),
        };
    }

    private function isValidDate(string $value): bool
    {
        try {
            LocalDate::fromString($value);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
