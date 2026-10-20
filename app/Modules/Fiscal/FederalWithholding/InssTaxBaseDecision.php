<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

final readonly class InssTaxBaseDecision
{
    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $missingFacts
     */
    public function __construct(
        public string $status,
        public ?string $grossAmount,
        public ?string $materialsEquipmentExclusion,
        public ?string $otherDeductions,
        public ?string $taxableBase,
        public array $reasonCodes,
        public array $missingFacts = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'gross_amount' => $this->grossAmount,
            'materials_equipment_exclusion' => $this->materialsEquipmentExclusion,
            'other_deductions' => $this->otherDeductions,
            'taxable_base' => $this->taxableBase,
            'reason_codes' => $this->reasonCodes,
            'missing_facts' => $this->missingFacts,
        ];
    }
}
