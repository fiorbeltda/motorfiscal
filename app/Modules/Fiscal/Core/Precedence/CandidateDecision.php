<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Precedence;

final readonly class CandidateDecision
{
    public function __construct(
        public string $ruleId,
        public string $decisionKey,
        public mixed $value,
    ) {}
}
