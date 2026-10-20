<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Engine;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

final readonly class FiscalEvaluationResult
{
    /**
     * @param  array<string, mixed>  $decision
     * @param  list<array<string, mixed>>  $questions
     * @param  list<string>  $reasonCodes
     * @param  list<array<string, mixed>>  $trace
     * @param  list<string>  $missingFacts
     */
    public function __construct(
        public DecisionStatus $status,
        public string $scenarioId,
        public array $decision,
        public array $questions,
        public array $reasonCodes,
        public array $trace,
        public array $missingFacts = [],
    ) {}

    /** @return array<string, mixed> */
    public function deterministicPayload(): array
    {
        return [
            'status' => $this->status->value,
            'scenario_id' => $this->scenarioId,
            'decision' => $this->decision,
            'questions' => $this->questions,
            'missing_facts' => $this->missingFacts,
            'reason_codes' => $this->reasonCodes,
            'trace' => $this->trace,
        ];
    }

    public function hash(): string
    {
        return CanonicalJson::hash($this->deterministicPayload());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...$this->deterministicPayload(), 'decision_hash' => $this->hash()];
    }
}
