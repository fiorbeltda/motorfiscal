<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Invariants;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;

final readonly class RequiredDecisionPathInvariant implements FiscalInvariant
{
    public function __construct(
        private string $invariantId,
        private string $path,
        private InvariantSeverity $severity = InvariantSeverity::BLOCKING,
    ) {}

    public function id(): string
    {
        return $this->invariantId;
    }

    public function validate(array $decision, FiscalDecisionContext $context): InvariantResult
    {
        $cursor = $decision;

        foreach (explode('.', $this->path) as $segment) {
            if (! array_key_exists($segment, $cursor)) {
                return new InvariantResult($this->id(), false, $this->severity, "Missing decision path: {$this->path}");
            }
            $value = $cursor[$segment];
            if ($value === null || $value === '') {
                return new InvariantResult($this->id(), false, $this->severity, "Empty decision path: {$this->path}");
            }
            $cursor = is_array($value) ? $value : ['value' => $value];
        }

        return new InvariantResult($this->id(), true, InvariantSeverity::INFO, "Decision path {$this->path} is present.");
    }
}
