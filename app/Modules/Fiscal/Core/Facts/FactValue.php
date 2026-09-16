<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

use App\Modules\Fiscal\Core\Evidence\EvidenceReference;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class FactValue
{
    public function __construct(
        public string $key,
        public mixed $value,
        public FactSourceType $sourceType,
        public LocalDate $effectiveFrom,
        public ?LocalDate $effectiveTo,
        public DateTimeImmutable $recordedAt,
        public ?EvidenceReference $evidence = null,
        public string $confidence = 'HIGH',
    ) {
        if ($effectiveTo !== null && $effectiveTo->isBefore($effectiveFrom)) {
            throw new InvalidArgumentException('Fact effective_to cannot be before effective_from.');
        }
    }

    public function isEffectiveOn(LocalDate $date): bool
    {
        if ($date->isBefore($this->effectiveFrom)) {
            return false;
        }

        return $this->effectiveTo === null || ! $date->isAfter($this->effectiveTo);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'source_type' => $this->sourceType->value,
            'effective_from' => $this->effectiveFrom->value(),
            'effective_to' => $this->effectiveTo?->value(),
            'recorded_at' => $this->recordedAt->format(DATE_ATOM),
            'evidence' => $this->evidence?->toArray(),
            'confidence' => $this->confidence,
        ];
    }
}
