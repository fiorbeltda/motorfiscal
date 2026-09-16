<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;

final readonly class RuleEvaluator
{
    public function __construct(private ConditionEvaluator $conditions) {}

    public function evaluate(CompiledRule $rule, FiscalDecisionContext $context): RuleEvaluationResult
    {
        if (! $rule->isEffectiveOn($context->competenceDate)) {
            return new RuleEvaluationResult(
                $rule->id,
                RuleMatchStatus::NOT_MATCHED,
                EvaluationTruth::FALSE,
            );
        }

        $result = $this->conditions->evaluate($rule->condition, $context->facts);

        return new RuleEvaluationResult(
            ruleId: $rule->id,
            status: match ($result->truth) {
                EvaluationTruth::TRUE => RuleMatchStatus::MATCHED,
                EvaluationTruth::FALSE => RuleMatchStatus::NOT_MATCHED,
                EvaluationTruth::UNKNOWN => RuleMatchStatus::NEEDS_FACTS,
                EvaluationTruth::ERROR => RuleMatchStatus::ERROR,
            },
            truth: $result->truth,
            missingFacts: $result->missingFacts,
            errors: $result->errors,
            effects: $result->truth === EvaluationTruth::TRUE ? $rule->effects : [],
        );
    }
}
