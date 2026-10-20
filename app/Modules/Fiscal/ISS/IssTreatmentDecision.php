<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

final readonly class IssTreatmentDecision
{
    public function __construct(
        public string $treatment,
        public string $reasonCode,
    ) {}

    /** @return array{value: string, reason_code: string} */
    public function toArray(): array
    {
        return ['value' => $this->treatment, 'reason_code' => $this->reasonCode];
    }
}
