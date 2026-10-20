<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class IssRateDecision
{
    /** @param list<string> $missingFacts */
    public function __construct(
        public ?DecimalValue $rate,
        public string $source,
        public string $reasonCode,
        public array $missingFacts = [],
        public ?string $referencePeriod = null,
        public string $confidence = 'DETERMINISTIC',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rate' => $this->rate?->value(),
            'source' => $this->source,
            'reference_period' => $this->referencePeriod,
            'reason_code' => $this->reasonCode,
            'missing_facts' => $this->missingFacts,
            'confidence' => $this->confidence,
        ];
    }
}
