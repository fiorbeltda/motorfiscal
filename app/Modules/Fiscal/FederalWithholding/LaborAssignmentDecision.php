<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

final readonly class LaborAssignmentDecision
{
    /**
     * @param  list<string>  $missingFacts
     * @param  list<string>  $reasonCodes
     */
    public function __construct(
        public string $classification,
        public bool $laborAssignment,
        public array $missingFacts,
        public array $reasonCodes,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'classification' => $this->classification,
            'labor_assignment' => $this->laborAssignment,
            'missing_facts' => $this->missingFacts,
            'reason_codes' => $this->reasonCodes,
        ];
    }
}
