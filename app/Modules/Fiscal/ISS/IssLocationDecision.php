<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

final readonly class IssLocationDecision
{
    /** @param list<string> $missingFacts */
    public function __construct(
        public IssLocationStrategy $strategy,
        public ?LocationIdentifier $location,
        public string $reasonCode,
        public array $missingFacts = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'strategy' => $this->strategy->value,
            'municipality_ibge' => $this->location?->ibgeCode,
            'reason_code' => $this->reasonCode,
            'missing_facts' => $this->missingFacts,
        ];
    }
}
