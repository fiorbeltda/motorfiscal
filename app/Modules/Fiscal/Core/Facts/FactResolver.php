<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;

interface FactResolver
{
    public function supports(string $factKey): bool;

    /** @return list<string> */
    public function dependencies(string $factKey): array;

    public function resolve(string $factKey, FiscalDecisionContext $context): FactResolutionResult;
}
