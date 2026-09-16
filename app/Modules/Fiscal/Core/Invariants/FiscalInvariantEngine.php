<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Invariants;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;

final readonly class FiscalInvariantEngine
{
    /** @param list<FiscalInvariant> $invariants */
    public function __construct(private array $invariants) {}

    /** @param array<string, mixed> $decision */
    public function validate(array $decision, FiscalDecisionContext $context): InvariantValidationResult
    {
        return new InvariantValidationResult(array_map(
            fn (FiscalInvariant $invariant): InvariantResult => $invariant->validate($decision, $context),
            $this->invariants,
        ));
    }
}
