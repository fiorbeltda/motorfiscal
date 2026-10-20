<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

final readonly class IssIncidenceDecision
{
    public function __construct(
        public string $incidence,
        public string $reasonCode,
    ) {}

    /** @return array{value: string, reason_code: string} */
    public function toArray(): array
    {
        return ['value' => $this->incidence, 'reason_code' => $this->reasonCode];
    }
}
