<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;

final readonly class MunicipalParameterRecord
{
    public function __construct(
        public string $municipalityIbge,
        public string $ctribnacCode,
        public LocalDate $effectiveFrom,
        public ?LocalDate $effectiveTo,
        public ?DecimalValue $rate,
        public ?IssResponsibility $responsibility,
        public string $sourceVersion,
        public string $sourceType,
        public string $sourceId,
    ) {}

    public function isEffectiveOn(LocalDate $date): bool
    {
        return ! $date->isBefore($this->effectiveFrom)
            && ($this->effectiveTo === null || ! $date->isAfter($this->effectiveTo));
    }
}
