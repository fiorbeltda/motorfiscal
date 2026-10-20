<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Classification;

final readonly class ServiceClassificationDecision
{
    public function __construct(
        public ?CanonicalService $service,
        public string $reasonCode,
    ) {}

    public function isClassified(): bool
    {
        return $this->service !== null;
    }
}
