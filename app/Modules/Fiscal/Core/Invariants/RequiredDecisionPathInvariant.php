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
        private ?string $effectiveFrom = null,
        private ?string $effectiveTo = null,
        private ?string $exemptWhenPath = null,
        private ?string $exemptWhenValue = null,
    ) {}

    public function id(): string
    {
        return $this->invariantId;
    }

    public function validate(array $decision, FiscalDecisionContext $context): InvariantResult
    {
        $date = $context->competenceDate->value();
        if (($this->effectiveFrom !== null && $date < $this->effectiveFrom)
            || ($this->effectiveTo !== null && $date > $this->effectiveTo)) {
            return new InvariantResult($this->id(), true, InvariantSeverity::INFO, 'Invariant is outside its effective period.');
        }
        $cursor = $decision;
        if ($this->exemptWhenPath !== null && $this->exemptWhenValue !== null) {
            $condition = $decision;
            foreach (explode('.', $this->exemptWhenPath) as $segment) {
                if (! is_array($condition) || ! array_key_exists($segment, $condition)) {
                    $condition = null;
                    break;
                }
                $condition = $condition[$segment];
            }
            if ($condition === $this->exemptWhenValue) {
                return new InvariantResult($this->id(), true, InvariantSeverity::INFO, 'Decision path is inapplicable to the evidenced treatment.');
            }
        }

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
