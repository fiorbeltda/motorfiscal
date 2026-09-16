<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Invariants;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;

interface FiscalInvariant
{
    public function id(): string;

    /** @param array<string, mixed> $decision */
    public function validate(array $decision, FiscalDecisionContext $context): InvariantResult;
}
