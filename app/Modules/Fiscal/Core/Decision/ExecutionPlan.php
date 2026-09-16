<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Decision;

final readonly class ExecutionPlan
{
    /** @param list<DecisionNodeDefinition> $nodes */
    public function __construct(public array $nodes) {}

    /** @return list<string> */
    public function nodeIds(): array
    {
        return array_map(fn (DecisionNodeDefinition $node): string => $node->id, $this->nodes);
    }
}
