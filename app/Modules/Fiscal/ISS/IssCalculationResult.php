<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

final readonly class IssCalculationResult
{
    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $missingFacts
     */
    public function __construct(
        public bool $valid,
        public ?string $grossAmount,
        public ?string $deductionAmount,
        public ?string $taxableBase,
        public ?string $rate,
        public ?string $taxAmount,
        public array $reasonCodes,
        public array $missingFacts = [],
        public bool $actionRequired = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'gross_amount' => $this->grossAmount,
            'deduction_amount' => $this->deductionAmount,
            'taxable_base' => $this->taxableBase,
            'rate' => $this->rate,
            'tax_amount' => $this->taxAmount,
            'currency' => 'BRL',
            'reason_codes' => $this->reasonCodes,
            'missing_facts' => $this->missingFacts,
            'action_required' => $this->actionRequired,
        ];
    }
}
