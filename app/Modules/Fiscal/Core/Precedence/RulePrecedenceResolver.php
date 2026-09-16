<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Precedence;

final class RulePrecedenceResolver
{
    /**
     * @param  list<CandidateDecision>  $candidates
     * @param  list<RuleRelation>  $relations
     */
    public function resolve(array $candidates, array $relations): PrecedenceResult
    {
        if ($candidates === []) {
            return new PrecedenceResult(PrecedenceStatus::EMPTY, null);
        }

        $candidateRules = array_fill_keys(array_map(fn (CandidateDecision $candidate): string => $candidate->ruleId, $candidates), true);
        $subordinates = [];

        foreach ($relations as $relation) {
            if (isset($candidateRules[$relation->dominantRuleId], $candidateRules[$relation->subordinateRuleId])) {
                $subordinates[$relation->subordinateRuleId] = true;
            }
        }

        $remaining = array_values(array_filter(
            $candidates,
            fn (CandidateDecision $candidate): bool => ! isset($subordinates[$candidate->ruleId]),
        ));

        if (count($remaining) !== 1) {
            return new PrecedenceResult(PrecedenceStatus::CONFLICT, null, $remaining);
        }

        return new PrecedenceResult(PrecedenceStatus::RESOLVED, $remaining[0], $remaining);
    }
}
