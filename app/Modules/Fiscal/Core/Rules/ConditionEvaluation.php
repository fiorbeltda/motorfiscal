<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

final readonly class ConditionEvaluation
{
    /**
     * @param  list<string>  $missingFacts
     * @param  list<string>  $errors
     */
    public function __construct(
        public EvaluationTruth $truth,
        public array $missingFacts = [],
        public array $errors = [],
    ) {}
}
