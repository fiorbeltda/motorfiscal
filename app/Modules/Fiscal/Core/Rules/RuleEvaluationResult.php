<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

final readonly class RuleEvaluationResult
{
    /**
     * @param  list<string>  $missingFacts
     * @param  list<string>  $errors
     * @param  list<RuleEffect>  $effects
     */
    public function __construct(
        public string $ruleId,
        public RuleMatchStatus $status,
        public EvaluationTruth $truth,
        public array $missingFacts = [],
        public array $errors = [],
        public array $effects = [],
    ) {}
}
