<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class FederalWithholdingEngine
{
    public function __construct(
        private IrrfWithholdingEngine $irrf = new IrrfWithholdingEngine,
        private PccWithholdingEngine $pcc = new PccWithholdingEngine,
        private InssWithholdingEngine $inss = new InssWithholdingEngine,
        private ?PublicAdministrationRuleSet $public = null,
        private ?FederalObligationProjector $obligations = null,
        private MedicalCooperativeWithholdingProfile $medicalCooperative = new MedicalCooperativeWithholdingProfile,
        private PublicCooperativeWithholdingProfile $publicCooperative = new PublicCooperativeWithholdingProfile,
    ) {}

    public function evaluate(FactBag $facts): FederalWithholdingEvaluation
    {
        $originalPayer = $facts->get('payer.person_type');
        $foundationShare = $facts->value('public.foundation_maintainer_public_revenue_share');
        $foundationOutsideArticle2A = $facts->value('payer.public_entity_type') === 'PUBLIC_FOUNDATION'
            && $facts->value('public.foundation_autarchic_nature') === false
            && is_string($foundationShare)
            && $facts->has('public.foundation_qualification_source_id')
            && DecimalValue::fromString($foundationShare)->compareTo(DecimalValue::fromString('0')) >= 0
            && DecimalValue::fromString($foundationShare)->compareTo(DecimalValue::fromString('50')) <= 0;
        $privatePublicEntity = $originalPayer !== null
            && $facts->hasDefinition('public.foundation_autarchic_nature')
            && in_array($originalPayer->value, ['STATE_PUBLIC_ADMINISTRATION', 'DISTRICT_PUBLIC_ADMINISTRATION', 'MUNICIPAL_PUBLIC_ADMINISTRATION'], true)
            && (in_array($facts->value('payer.public_entity_type'), ['PUBLIC_COMPANY', 'MIXED_CAPITAL_COMPANY'], true)
                || $foundationOutsideArticle2A);
        if ($privatePublicEntity) {
            $facts = $facts->withReplaced(new FactValue('payer.person_type', 'PRIVATE_LEGAL_ENTITY',
                FactSourceType::DERIVED, $originalPayer->effectiveFrom, $originalPayer->effectiveTo,
                $originalPayer->recordedAt));
        }
        $payerType = $facts->value('payer.person_type');
        $additional = [];
        if (is_string($payerType) && str_contains($payerType, 'PUBLIC_ADMINISTRATION')
            && $facts->value('provider.person_type') === 'FOREIGN_LEGAL_ENTITY') {
            $irrf = $this->irrf->evaluate($facts);
            $pccDecisions = [];
            foreach ([FederalTax::PIS, FederalTax::COFINS, FederalTax::CSLL] as $tax) {
                $pccDecisions[] = new FederalWithholdingDecision($tax, FederalWithholdingStatus::NOT_APPLICABLE,
                    null, null, '0.00', null, 'PAYER', 'PAYMENT', null,
                    ['PUBLIC_FOREIGN_BENEFICIARY_OUTSIDE_IN1234_ARTICLE3'], ['IN RFB 1.234/2012, art. 35']);
            }
            $pcc = new PccWithholdingResult('NOT_APPLICABLE', $pccDecisions,
                null, '0.00', null, null, null, 'NONE',
                ['PUBLIC_FOREIGN_BENEFICIARY_OUTSIDE_IN1234_ARTICLE3']);
        } elseif ($facts->hasDefinition('cooperative.invoice_components')
            && $facts->value('provider.person_type') === 'COOPERATIVE'
            && in_array($facts->value('cooperative.provider_type'), ['WORK', 'MEDICAL'], true)
            && is_string($payerType) && str_contains($payerType, 'PUBLIC_ADMINISTRATION')) {
            $cooperative = $this->publicCooperative->evaluate($facts);
            $irrf = $cooperative['irrf'];
            $pcc = $cooperative['pcc'];
            $additional = $cooperative['additional'];
        } elseif ($facts->hasDefinition('cooperative.medical_role')
            && $facts->value('provider.person_type') === 'COOPERATIVE'
            && $facts->value('cooperative.provider_type') === 'MEDICAL'
            && in_array($payerType, ['PRIVATE_LEGAL_ENTITY', 'COOPERATIVE'], true)) {
            $medical = $this->medicalCooperative->evaluate($facts);
            $irrf = $medical['irrf'];
            $pcc = $medical['pcc'];
            $additional = $medical['additional'];
        } elseif (is_string($payerType) && str_contains($payerType, 'PUBLIC_ADMINISTRATION') && $this->public !== null) {
            $public = $this->public->evaluate($facts);
            $irrf = $public['irrf'];
            $pcc = $public['pcc'];
        } else {
            $irrf = $this->irrf->evaluate($facts);
            $pcc = $this->pcc->evaluate($facts);
        }
        $inss = $this->inss->evaluate($facts);
        $missing = array_values(array_unique([
            ...$irrf->missingFacts,
            ...$pcc->missingFacts,
            ...$inss->missingFacts,
        ]));
        $reasons = array_values(array_unique([
            ...$irrf->reasonCodes,
            ...$pcc->reasonCodes,
            ...$inss->reasonCodes,
        ]));
        foreach ($additional as $decision) {
            $missing = array_values(array_unique([...$missing, ...$decision->missingFacts]));
            $reasons = array_values(array_unique([...$reasons, ...$decision->reasonCodes]));
        }
        if ($privatePublicEntity) {
            $reasons[] = 'PUBLIC_SUBNATIONAL_ENTERPRISE_PRIVATE_WITHHOLDING_PROFILE';
        }

        $grossValue = $facts->value('payment.gross_amount');
        $gross = is_string($grossValue) ? DecimalValue::fromString($grossValue)->rounded(2) : null;
        $total = DecimalValue::fromString('0');
        if ($irrf->status === FederalWithholdingStatus::WITHHOLD && $irrf->amount !== null) {
            $total = $total->plus(DecimalValue::fromString($irrf->amount));
        }
        if ($pcc->totalWithholding !== null) {
            $total = $total->plus(DecimalValue::fromString($pcc->totalWithholding));
        }
        if ($inss->status === FederalWithholdingStatus::WITHHOLD && $inss->amount !== null) {
            $total = $total->plus(DecimalValue::fromString($inss->amount));
        }
        foreach ($additional as $decision) {
            if ($decision->status === FederalWithholdingStatus::WITHHOLD && $decision->amount !== null) {
                $total = $total->plus(DecimalValue::fromString($decision->amount));
            }
        }

        $actionRequired = $missing !== []
            || $irrf->status === FederalWithholdingStatus::ACTION_REQUIRED
            || $pcc->status === 'ACTION_REQUIRED'
            || $inss->status === FederalWithholdingStatus::ACTION_REQUIRED;
        foreach ($additional as $decision) {
            $actionRequired = $actionRequired || $decision->status === FederalWithholdingStatus::ACTION_REQUIRED;
        }
        if ($gross !== null && $total->compareTo($gross) > 0) {
            $actionRequired = true;
            $reasons[] = 'FEDERAL_WITHHOLDING_EXCEEDS_GROSS_AMOUNT';
        }
        $pendingTrigger = $irrf->status === FederalWithholdingStatus::PENDING_TRIGGER
            || $pcc->status === FederalWithholdingStatus::PENDING_TRIGGER->value
            || $inss->status === FederalWithholdingStatus::PENDING_TRIGGER;
        $obligationProjection = $this->obligations?->project($facts, $irrf, $pcc, $inss, $additional);
        foreach (($obligationProjection['items'] ?? []) as $item) {
            if (($item['status'] ?? null) === 'ACTION_REQUIRED') {
                $actionRequired = true;
                $reasons[] = (string) ($item['reason_code'] ?? 'FEDERAL_OBLIGATION_ACTION_REQUIRED');
                array_push($missing, ...($item['missing_facts'] ?? []), ...($item['collection_missing_facts'] ?? []));
            }
        }

        return new FederalWithholdingEvaluation(
            $actionRequired ? 'ACTION_REQUIRED' : ($pendingTrigger ? 'PENDING_TRIGGER' : 'RESOLVED'),
            $irrf,
            $pcc,
            $inss,
            $gross?->value(),
            $gross === null ? null : $total->rounded(2)->value(),
            $gross === null ? null : $gross->minus($total)->rounded(2)->value(),
            array_values(array_unique($reasons)),
            array_values(array_unique($missing)),
            $obligationProjection,
            $additional,
        );
    }
}
