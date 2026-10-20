<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

/** Artigos 26 e 27 da IN RFB 1.234/2012: cada fatura e beneficiário permanece identificado. */
final readonly class PublicCooperativeWithholdingProfile
{
    public function __construct(private IndividualIrrfWithholdingEngine $individual = new IndividualIrrfWithholdingEngine) {}

    /** @return array{irrf: FederalWithholdingDecision, pcc: PccWithholdingResult, additional: list<FederalWithholdingDecision>} */
    public function evaluate(FactBag $facts): array
    {
        if ($facts->value('payment.occurred') !== true) {
            return $this->unresolved(FederalWithholdingStatus::PENDING_TRIGGER, 'PUBLIC_COOPERATIVE_PAYMENT_NOT_OCCURRED');
        }
        $required = ['payment.date', 'payment.gross_amount', 'cooperative.provider_type',
            'cooperative.invoice_components', 'cooperative.invoice_components_evidence_confirmed',
            'provider.head_office_tax_id'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_FACTS_MISSING', $missing);
        }
        $date = (string) $facts->value('payment.date');
        if ($date >= '2027-01-01') {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_2027_REVALIDATION_REQUIRED');
        }
        if ($facts->value('cooperative.invoice_components_evidence_confirmed') !== true) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_INVOICE_UNPROVEN');
        }
        $type = $facts->value('cooperative.provider_type');
        if (! in_array($type, ['WORK', 'MEDICAL'], true)) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_LEGAL_TYPE_OUTSIDE_ARTICLES_26_27');
        }
        $payerType = $facts->value('payer.person_type');
        $federal = $payerType === 'FEDERAL_PUBLIC_ADMINISTRATION';
        $subnational = in_array($payerType, ['STATE_PUBLIC_ADMINISTRATION', 'DISTRICT_PUBLIC_ADMINISTRATION', 'MUNICIPAL_PUBLIC_ADMINISTRATION'], true);
        if (! $federal && ! $subnational) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_PAYER_SCOPE_CONFLICT');
        }
        $withholdContributions = $federal;
        if ($subnational) {
            $entity = $facts->value('payer.public_entity_type');
            if (! in_array($entity, ['DIRECT', 'AUTARCHY', 'PUBLIC_FOUNDATION'], true)) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_ENTITY_OUTSIDE_ARTICLE2A');
            }
            if ($entity === 'PUBLIC_FOUNDATION') {
                $missing = $facts->missing(['public.foundation_autarchic_nature', 'public.foundation_qualification_source_id']);
                if ($missing !== []) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_FOUNDATION_FACTS_MISSING', $missing);
                }
                if ($facts->value('public.foundation_autarchic_nature') !== true) {
                    $missing = $facts->missing(['public.foundation_maintainer_public_revenue_share']);
                    if ($missing !== []) {
                        return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_FOUNDATION_SHARE_MISSING', $missing);
                    }
                    if (DecimalValue::fromString((string) $facts->value('public.foundation_maintainer_public_revenue_share'))
                        ->compareTo(DecimalValue::fromString('50')) <= 0) {
                        return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_FOUNDATION_OUTSIDE_ARTICLE2A');
                    }
                }
            }
            $missing = $facts->missing(['public.article33_convention_exists']);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_CONVENTION_STATUS_MISSING', $missing);
            }
            $withholdContributions = $facts->value('public.article33_convention_exists') === true;
            if ($withholdContributions) {
                $missing = $facts->missing(['public.article33_convention_source_id', 'public.article33_convention_valid_from', 'public.article33_convention_valid_to']);
                if ($missing !== []) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_CONVENTION_FACTS_MISSING', $missing);
                }
                if ($date < $facts->value('public.article33_convention_valid_from')
                    || $date > $facts->value('public.article33_convention_valid_to')) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_CONVENTION_OUTSIDE_VALIDITY');
                }
            }
        }
        $rows = $facts->value('cooperative.invoice_components');
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_COMPONENTS_INVALID');
        }
        $zero = DecimalValue::fromString('0');
        $sum = $zero;
        $pfBase = $zero;
        $other = [];
        $nonmemberIndividualDecisions = [];
        $seen = [];
        $seenIndividuals = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['line_id'] ?? null) || $row['line_id'] === ''
                || isset($seen[$row['line_id']]) || ! is_string($row['document_id'] ?? null)
                || $row['document_id'] === '' || ! is_string($row['amount'] ?? null)
                || preg_match('/^\d+(?:\.\d{1,2})?$/', $row['amount']) !== 1
                || ! is_string($row['type'] ?? null)) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_COMPONENT_INVALID');
            }
            $seen[$row['line_id']] = true;
            $amount = DecimalValue::fromString($row['amount'])->rounded(2);
            $sum = $sum->plus($amount);
            if (in_array($row['type'], ['MEMBER_PF_SERVICE', 'POSTESTABLISHED_MEMBER_PF_SERVICE', 'COPAYMENT_MEMBER_PF_SERVICE'], true)) {
                if ($type !== 'MEDICAL' && $row['type'] !== 'MEMBER_PF_SERVICE') {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_COMPONENT_TYPE_CONFLICT');
                }
                $pfBase = $pfBase->plus($amount);

                continue;
            }
            if ($row['type'] === 'NONMEMBER_PF_SERVICE') {
                $cpf = $row['beneficiary_tax_id'] ?? null;
                if (! is_string($cpf) || preg_match('/^\d{11}$/', $cpf) !== 1
                    || isset($seenIndividuals[$cpf])
                    || ! is_string($row['monthly_taxable_income'] ?? null)
                    || ! is_string($row['monthly_taxable_base'] ?? null)
                    || ! in_array($row['relationship'] ?? null,
                        ['EMPLOYMENT_OR_WORK_RELATIONSHIP', 'NO_WORK_RELATIONSHIP'], true)) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_NONMEMBER_PF_FACTS_MISSING');
                }
                $seenIndividuals[$cpf] = true;
                $individual = $this->individual->calculate($date, $row['monthly_taxable_income'],
                    $row['monthly_taxable_base'], $row['relationship']);
                if ($individual->status === FederalWithholdingStatus::ACTION_REQUIRED) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_NONMEMBER_PF_TAX_BASE_INVALID');
                }
                $nonmemberIndividualDecisions[] = new FederalWithholdingDecision(FederalTax::IRRF,
                    $individual->status, $individual->basis, $individual->rate, $individual->amount,
                    $subnational ? null : $individual->revenueCode, 'PAYER', 'PAYMENT', $date,
                    ['PUBLIC_COOPERATIVE_NONMEMBER_PF_PROGRESSIVE'], $individual->legalBasis, [],
                    [...$individual->details,
                        ...($subnational ? ['collection_destination' => 'LOCAL_TREASURY',
                            'federal_revenue_code_reference' => $individual->revenueCode] : []),
                        'beneficiary_tax_id' => $cpf,
                        'beneficiary_person_type' => 'INDIVIDUAL',
                        'source_documents' => [['line_id' => $row['line_id'], 'document_id' => $row['document_id']]]]);

                continue;
            }
            $componentType = $row['type'];
            if (in_array($componentType, ['REIMBURSEMENT', 'MATERIAL'], true)) {
                if (! in_array($row['underlying_service_type'] ?? null,
                    ['THIRD_PARTY_PJ_SERVICE', 'THIRD_PARTY_PJ_GENERAL_SERVICE',
                        'THIRD_PARTY_PJ_MATERIAL_SERVICE', 'HOSPITAL_INDEPENDENT', 'LABORATORY_INDEPENDENT'], true)
                    || ! is_string($row['underlying_service_legal_basis'] ?? null)
                    || $row['underlying_service_legal_basis'] === '') {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED,
                        'PUBLIC_COOPERATIVE_REIMBURSEMENT_UNDERLYING_SERVICE_REQUIRED');
                }
                $componentType = $row['underlying_service_type'];
            }
            $code = match ($componentType) {
                'MEMBER_PJ_SERVICE', 'THIRD_PARTY_PJ_SERVICE', 'THIRD_PARTY_PJ_GENERAL_SERVICE' => '6190',
                'THIRD_PARTY_PJ_MATERIAL_SERVICE', 'HOSPITAL_INDEPENDENT', 'LABORATORY_INDEPENDENT' => '6147',
                'ADMINISTRATION_FEE' => '6190',
                default => null,
            };
            if ($code === null || ($type !== 'MEDICAL' && in_array($componentType, ['HOSPITAL_INDEPENDENT', 'LABORATORY_INDEPENDENT'], true))) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_COMPONENT_LEGAL_CLASSIFICATION_REQUIRED');
            }
            if ($code === '6147' && ($row['qualified_material_or_hospital_service_confirmed'] ?? null) !== true) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_6147_EVIDENCE_REQUIRED');
            }
            $beneficiary = $row['type'] === 'ADMINISTRATION_FEE'
                ? $facts->value('provider.head_office_tax_id') : ($row['beneficiary_tax_id'] ?? null);
            if (! is_string($beneficiary) || preg_match('/^\d{14}$/', $beneficiary) !== 1) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_BENEFICIARY_CNPJ_REQUIRED');
            }
            $other[] = ['amount' => $amount, 'code' => $code, 'beneficiary' => $beneficiary,
                'line_id' => $row['line_id'], 'document_id' => $row['document_id']];
        }
        $gross = DecimalValue::fromString((string) $facts->value('payment.gross_amount'))->rounded(2);
        if ($sum->compareTo($gross) !== 0) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_PAYMENT_COMPONENTS_CONFLICT');
        }
        $potential = $this->percent($pfBase, '1.50');
        if ($withholdContributions) {
            foreach (['1.00', '3.00', '0.65'] as $rate) {
                $potential = $potential->plus($this->percent($pfBase, $rate));
            }
        }
        foreach ($other as $row) {
            $potential = $potential->plus($this->percent($row['amount'], $row['code'] === '6147' ? '1.20' : '4.80'));
            if ($withholdContributions) {
                foreach (['1.00', '3.00', '0.65'] as $rate) {
                    $potential = $potential->plus($this->percent($row['amount'], $rate));
                }
            }
        }
        $dispensed = $facts->value('payer.siafi_electronic_collection') !== true
            && $potential->compareTo(DecimalValue::fromString('10.00')) < 0;
        $irAmount = $dispensed ? $zero : $this->percent($pfBase, '1.50');
        $ir = $this->decision(FederalTax::IRRF, $pfBase, '1.50', $irAmount, '3280', $date,
            'PUBLIC_COOPERATIVE_PERSONAL_SERVICE', [], $dispensed, $subnational);
        $pccDecisions = [];
        $pccTotal = $zero;
        foreach (['CSLL' => '1.00', 'COFINS' => '3.00', 'PIS' => '0.65'] as $tax => $rate) {
            $amount = $dispensed || ! $withholdContributions ? $zero : $this->percent($pfBase, $rate);
            $pccTotal = $pccTotal->plus($amount);
            $pccDecisions[] = $this->decision(FederalTax::from($tax), $pfBase, $rate, $amount,
                $federal ? '8863' : match ($tax) {
                    'CSLL' => '4397', 'COFINS' => '4407', 'PIS' => '4409'
                },
                $date, $withholdContributions ? 'PUBLIC_COOPERATIVE_PERSONAL_SERVICE_CONTRIBUTIONS' : 'PUBLIC_ARTICLE33_CONVENTION_ABSENT',
                [], $dispensed, false, ! $withholdContributions);
        }
        $additional = [];
        foreach ($other as $row) {
            $rates = $row['code'] === '6147'
                ? ['IRRF' => '1.20', 'CSLL' => '1.00', 'COFINS' => '3.00', 'PIS' => '0.65']
                : ['IRRF' => '4.80', 'CSLL' => '1.00', 'COFINS' => '3.00', 'PIS' => '0.65'];
            $details = ['beneficiary_tax_id' => $row['beneficiary'],
                'source_documents' => [['line_id' => $row['line_id'], 'document_id' => $row['document_id']]]];
            foreach ($rates as $tax => $rate) {
                $amount = $dispensed || ($tax !== 'IRRF' && ! $withholdContributions)
                    ? $zero : $this->percent($row['amount'], $rate);
                $additional[] = $this->decision(FederalTax::from($tax), $row['amount'], $rate,
                    $amount, $federal || $tax === 'IRRF' ? $row['code'] : match ($tax) {
                        'CSLL' => '4397', 'COFINS' => '4407', 'PIS' => '4409'
                    },
                    $date, 'PUBLIC_COOPERATIVE_SEGREGATED_BENEFICIARY', $details, $dispensed,
                    $subnational && $tax === 'IRRF', $tax !== 'IRRF' && ! $withholdContributions);
            }
        }
        foreach ($nonmemberIndividualDecisions as $decision) {
            $additional[] = $decision;
        }
        $pccStatus = ! $withholdContributions || $pfBase->compareTo($zero) === 0 ? 'NOT_APPLICABLE'
            : ($dispensed ? 'DISPENSED_THRESHOLD' : ($pccTotal->compareTo($zero) > 0 ? 'WITHHOLD' : 'NOT_APPLICABLE'));

        return ['irrf' => $ir,
            'pcc' => new PccWithholdingResult($pccStatus, $pccDecisions, $gross->value(),
                $pccTotal->value(), $gross->minus($pccTotal)->value(),
                $pccTotal->compareTo($zero) > 0 ? ($federal ? '8863' : '4085') : null, substr($date, 0, 7),
                'DAY_20_NEXT_MONTH_PREVIOUS_BANKING_DAY', ['PUBLIC_COOPERATIVE_ARTICLE_26_27']),
            'additional' => $additional];
    }

    /** @param array<string, mixed> $details */
    private function decision(FederalTax $tax, DecimalValue $basis, string $rate, DecimalValue $amount,
        string $code, string $date, string $reason, array $details, bool $dispensed,
        bool $localIr = false, bool $notApplicable = false): FederalWithholdingDecision
    {
        $status = $notApplicable || $basis->compareTo(DecimalValue::fromString('0')) === 0
            ? FederalWithholdingStatus::NOT_APPLICABLE
            : ($dispensed ? FederalWithholdingStatus::DISPENSED_THRESHOLD
                : FederalWithholdingStatus::WITHHOLD);
        if ($localIr) {
            $details['collection_destination'] = 'LOCAL_TREASURY';
            $details['federal_revenue_code_reference'] = $code;
        }

        return new FederalWithholdingDecision($tax, $status, $basis->value(), $rate, $amount->value(),
            $status === FederalWithholdingStatus::WITHHOLD && ! $localIr ? $code : null,
            'PAYER', 'PAYMENT', $date, [$reason], ['IN RFB 1.234/2012, arts. 24, 26 e 27'], [], $details);
    }

    private function percent(DecimalValue $base, string $rate): DecimalValue
    {
        return $base->multipliedBy(DecimalValue::fromString($rate))
            ->dividedBy(DecimalValue::fromString('100'), 8)->rounded(2);
    }

    /** @param list<string> $missing
     * @return array{irrf: FederalWithholdingDecision, pcc: PccWithholdingResult, additional: list<FederalWithholdingDecision>}
     */
    private function unresolved(FederalWithholdingStatus $status, string $reason, array $missing = []): array
    {
        $ir = new FederalWithholdingDecision(FederalTax::IRRF, $status, null, null, null, null,
            'PAYER', 'PAYMENT', null, [$reason], ['IN RFB 1.234/2012, arts. 26 e 27'], $missing);
        $parts = [];
        foreach ([FederalTax::PIS, FederalTax::COFINS, FederalTax::CSLL] as $tax) {
            $parts[] = new FederalWithholdingDecision($tax, $status, null, null, null, null,
                'PAYER', 'PAYMENT', null, [$reason], ['IN RFB 1.234/2012, art. 24'], $missing);
        }

        return ['irrf' => $ir, 'pcc' => new PccWithholdingResult($status->value, $parts,
            null, null, null, null, null, 'PUBLIC_COLLECTION_RULE_REQUIRED', [$reason], $missing),
            'additional' => []];
    }
}
