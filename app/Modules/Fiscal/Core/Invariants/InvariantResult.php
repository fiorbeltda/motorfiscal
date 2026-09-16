<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Invariants;

final readonly class InvariantResult
{
    public function __construct(
        public string $invariantId,
        public bool $valid,
        public InvariantSeverity $severity,
        public string $message,
    ) {}
}
