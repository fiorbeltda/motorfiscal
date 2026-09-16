<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Questions;

use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;

final readonly class QuestionPlanner
{
    /** @param list<QuestionDefinition> $definitions */
    public function __construct(
        private FactDefinitionRegistry $facts,
        private array $definitions,
    ) {}

    /** @param list<string> $missingFactKeys */
    public function plan(array $missingFactKeys): QuestionPlan
    {
        $byFact = [];
        foreach ($this->definitions as $definition) {
            $byFact[$definition->factKey] = $definition;
        }

        $questions = [];
        $unmapped = [];

        foreach (array_values(array_unique($missingFactKeys)) as $factKey) {
            if (! $this->facts->has($factKey) || ! $this->facts->get($factKey)->material) {
                continue;
            }

            if (isset($byFact[$factKey])) {
                $questions[] = $byFact[$factKey];
            } else {
                $unmapped[] = $factKey;
            }
        }

        usort($questions, fn (QuestionDefinition $left, QuestionDefinition $right): int => [$left->rank, $left->factKey] <=> [$right->rank, $right->factKey]);
        sort($unmapped, SORT_STRING);

        return new QuestionPlan($questions, $unmapped);
    }
}
