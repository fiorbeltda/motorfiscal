<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

final readonly class PccWithholdingResult
{
    /**
     * @param  list<FederalWithholdingDecision>  $decisions
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $missingFacts
     */
    public function __construct(
        public string $status,
        public array $decisions,
        public ?string $grossPayment,
        public ?string $totalWithholding,
        public ?string $netPayment,
        public ?string $collectionCode,
        public ?string $taxPeriod,
        public string $dueRule,
        public array $reasonCodes = [],
        public array $missingFacts = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'trigger' => 'PAYMENT',
            'gross_payment' => $this->grossPayment,
            'total_withholding' => $this->totalWithholding,
            'net_payment' => $this->netPayment,
            'collection_code' => $this->collectionCode,
            'tax_period' => $this->taxPeriod,
            'due_rule' => $this->dueRule,
            'reporting' => ['efd_reinf_event' => 'R-4020', 'dctfweb' => true],
            'decisions' => array_map(static fn (FederalWithholdingDecision $decision): array => $decision->toArray(), $this->decisions),
            'reason_codes' => $this->reasonCodes,
            'missing_facts' => $this->missingFacts,
        ];
    }
}
