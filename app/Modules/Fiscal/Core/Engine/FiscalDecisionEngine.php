<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Engine;

interface FiscalDecisionEngine
{
    public function evaluate(FiscalDecisionInput $input): FiscalEvaluationResult;
}
