<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

final readonly class FederalWithholdingEvaluation
{
    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $missingFacts
     * @param  list<FederalWithholdingDecision>  $additionalDecisions
     */
    public function __construct(
        public string $status,
        public FederalWithholdingDecision $irrf,
        public PccWithholdingResult $pcc,
        public FederalWithholdingDecision $inss,
        public ?string $grossPayment,
        public ?string $totalWithholding,
        public ?string $netPayment,
        public array $reasonCodes,
        public array $missingFacts,
        /** @var array<string, mixed>|null */
        public ?array $obligations = null,
        public array $additionalDecisions = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = [
            'status' => $this->status,
            'gross_payment' => $this->grossPayment,
            'total_withholding' => $this->totalWithholding,
            'net_payment' => $this->netPayment,
            'irrf' => $this->irrf->toArray(),
            'pcc' => $this->pcc->toArray(),
            'inss' => $this->inss->toArray(),
            'reason_codes' => $this->reasonCodes,
            'missing_facts' => $this->missingFacts,
        ];
        if ($this->obligations !== null) {
            $result['obligations'] = $this->obligations;
        }
        if ($this->additionalDecisions !== []) {
            $result['additional_decisions'] = array_map(
                static fn (FederalWithholdingDecision $decision): array => $decision->toArray(),
                $this->additionalDecisions,
            );
        }

        return $result;
    }
}
