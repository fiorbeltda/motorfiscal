<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Decision;

final class ExecutionPlanCursor
{
    private int $position = 0;

    private ?string $activeNode = null;

    /** @param list<string> $nodeIds */
    public function __construct(private readonly array $nodeIds) {}

    public function start(string $nodeId): void
    {
        if ($this->activeNode !== null) {
            throw new DecisionPlanningException("Decision node {$this->activeNode} has not completed.");
        }
        $expected = $this->nodeIds[$this->position] ?? null;
        if ($expected !== $nodeId) {
            throw new DecisionPlanningException("Decision plan expected {$expected}, received {$nodeId}.");
        }
        $this->activeNode = $nodeId;
    }

    public function complete(string $nodeId): void
    {
        if ($this->activeNode !== $nodeId) {
            throw new DecisionPlanningException("Decision node {$nodeId} is not active.");
        }
        $this->activeNode = null;
        $this->position++;
    }

    public function isComplete(): bool
    {
        return $this->activeNode === null && $this->position === count($this->nodeIds);
    }
}
