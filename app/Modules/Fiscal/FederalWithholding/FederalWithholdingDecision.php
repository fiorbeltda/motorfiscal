<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

final readonly class FederalWithholdingDecision
{
    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $legalBasis
     * @param  list<string>  $missingFacts
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public FederalTax $tax,
        public FederalWithholdingStatus $status,
        public ?string $basis,
        public ?string $rate,
        public ?string $amount,
        public ?string $revenueCode,
        public string $responsibleParty,
        public string $trigger,
        public ?string $triggerDate,
        public array $reasonCodes,
        public array $legalBasis,
        public array $missingFacts = [],
        public array $details = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tax' => $this->tax->value,
            'status' => $this->status->value,
            'basis' => $this->basis,
            'rate' => $this->rate,
            'amount' => $this->amount,
            'revenue_code' => $this->revenueCode,
            'responsible_party' => $this->responsibleParty,
            'trigger' => $this->trigger,
            'trigger_date' => $this->triggerDate,
            'reason_codes' => $this->reasonCodes,
            'legal_basis' => $this->legalBasis,
            'missing_facts' => $this->missingFacts,
            'details' => $this->details,
        ];
    }
}
