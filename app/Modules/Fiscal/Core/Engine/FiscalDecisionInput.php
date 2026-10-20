<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Engine;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;

final readonly class FiscalDecisionInput
{
    public function __construct(
        public string $scenarioId,
        public FiscalDecisionContext $context,
    ) {}
}
