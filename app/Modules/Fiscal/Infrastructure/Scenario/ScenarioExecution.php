<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

use App\Modules\Fiscal\Core\Engine\FiscalEvaluationResult;

final readonly class ScenarioExecution
{
    /** @param array<string, mixed> $knowledge */
    public function __construct(
        public LoadedScenario $scenario,
        public FiscalEvaluationResult $result,
        public array $knowledge,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'snapshot_version' => '1',
            'input' => $this->scenario->source,
            'knowledge' => $this->knowledge,
            'result' => $this->result->toArray(),
        ];
    }
}
