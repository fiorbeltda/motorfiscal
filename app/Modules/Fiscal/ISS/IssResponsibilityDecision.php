<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

final readonly class IssResponsibilityDecision
{
    /**
     * @param  list<string>  $missingFacts
     * @param  list<string>  $legalBasis
     */
    public function __construct(
        public ?IssResponsibility $responsibleParty,
        public bool $withholdingRequired,
        public ?string $tpRetIssqn,
        public ?string $nationalRule,
        public ?string $municipalRule,
        public string $reasonCode,
        public array $missingFacts = [],
        public ?bool $withholdingPerformed = null,
        public IssWithholdingDecision $decision = IssWithholdingDecision::MANUAL_REVIEW,
        public string $ruleScope = 'UNRESOLVED',
        public array $legalBasis = [],
        public string $confidence = 'REVIEW_REQUIRED',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'taxpayer' => 'PROVIDER',
            'responsible_party' => $this->responsibleParty?->value,
            'withholding_required' => $this->withholdingRequired,
            'withholding_performed' => $this->withholdingPerformed,
            'decision' => $this->decision->value,
            'tpRetISSQN' => $this->tpRetIssqn,
            'national_rule' => $this->nationalRule,
            'municipal_rule' => $this->municipalRule,
            'reason_code' => $this->reasonCode,
            'rule_scope' => $this->ruleScope,
            'legal_basis' => $this->legalBasis,
            'confidence' => $this->confidence,
            'missing_facts' => $this->missingFacts,
        ];
    }
}
