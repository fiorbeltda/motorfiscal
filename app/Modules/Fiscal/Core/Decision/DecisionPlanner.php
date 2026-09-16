<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Decision;

final class DecisionPlanner
{
    /** @param list<DecisionNodeDefinition> $nodes */
    public function plan(array $nodes): ExecutionPlan
    {
        $byId = [];
        $incoming = [];
        $dependents = [];

        foreach ($nodes as $node) {
            if (isset($byId[$node->id])) {
                throw new DecisionPlanningException("Duplicate decision node: {$node->id}");
            }
            $byId[$node->id] = $node;
            $incoming[$node->id] = count($node->dependsOn);
        }

        foreach ($nodes as $node) {
            foreach ($node->dependsOn as $dependency) {
                if (! isset($byId[$dependency])) {
                    throw new DecisionPlanningException("Node {$node->id} depends on unknown node {$dependency}.");
                }
                $dependents[$dependency][] = $node->id;
            }
        }

        $queue = array_keys(array_filter($incoming, fn (int $count): bool => $count === 0));
        sort($queue, SORT_STRING);
        $ordered = [];

        while ($queue !== []) {
            $id = array_shift($queue);
            $ordered[] = $byId[$id];

            foreach ($dependents[$id] ?? [] as $dependent) {
                $incoming[$dependent]--;
                if ($incoming[$dependent] === 0) {
                    $queue[] = $dependent;
                    sort($queue, SORT_STRING);
                }
            }
        }

        if (count($ordered) !== count($nodes)) {
            $cyclic = array_keys(array_filter($incoming, fn (int $count): bool => $count > 0));
            sort($cyclic, SORT_STRING);
            throw new DecisionPlanningException('Decision graph contains a cycle: '.implode(', ', $cyclic));
        }

        return new ExecutionPlan($ordered);
    }
}
