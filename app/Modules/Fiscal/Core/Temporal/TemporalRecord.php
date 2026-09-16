<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Temporal;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TemporalRecord
{
    public function __construct(
        public string $id,
        public LocalDate $effectiveFrom,
        public ?LocalDate $effectiveTo,
        public DateTimeImmutable $recordedAt,
        public ?DateTimeImmutable $supersededAt,
        public mixed $payload,
    ) {
        if ($effectiveTo !== null && $effectiveTo->isBefore($effectiveFrom)) {
            throw new InvalidArgumentException('Temporal record has an inverted effective interval.');
        }

        if ($supersededAt !== null && $supersededAt < $recordedAt) {
            throw new InvalidArgumentException('Temporal record was superseded before being recorded.');
        }
    }

    public function wasEffectiveOn(LocalDate $date): bool
    {
        return ! $date->isBefore($this->effectiveFrom)
            && ($this->effectiveTo === null || ! $date->isAfter($this->effectiveTo));
    }

    public function wasKnownAt(DateTimeImmutable $instant): bool
    {
        return $this->recordedAt <= $instant
            && ($this->supersededAt === null || $this->supersededAt > $instant);
    }
}
