<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Invariants;

final readonly class InvariantValidationResult
{
    /** @param list<InvariantResult> $results */
    public function __construct(public array $results) {}

    public function hasBlockingFailure(): bool
    {
        return $this->hasFailureWithSeverity(InvariantSeverity::BLOCKING);
    }

    public function requiresAction(): bool
    {
        return $this->hasFailureWithSeverity(InvariantSeverity::ACTION_REQUIRED);
    }

    private function hasFailureWithSeverity(InvariantSeverity $severity): bool
    {
        foreach ($this->results as $result) {
            if (! $result->valid && $result->severity === $severity) {
                return true;
            }
        }

        return false;
    }
}
