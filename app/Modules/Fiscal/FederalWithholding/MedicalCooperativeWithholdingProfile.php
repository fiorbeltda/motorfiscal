<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class MedicalCooperativeWithholdingProfile
{
    private const TYPES = [
        'MEMBER_PF_SERVICE', 'MEMBER_PJ_SERVICE', 'THIRD_PARTY_PJ_SERVICE',
        'HOSPITAL_SUBORDINATED', 'LABORATORY_SUBORDINATED',
        'HOSPITAL_INDEPENDENT', 'LABORATORY_INDEPENDENT',
        'PREESTABLISHED_PLAN', 'POSTESTABLISHED_MEMBER_PF_SERVICE',
        'COPAYMENT_MEMBER_PF_SERVICE', 'ADMINISTRATION_FEE',
        'REIMBURSEMENT', 'MATERIAL', 'NONMEMBER_PF_SERVICE',
    ];

    private const PJ_TYPES = [
        'MEMBER_PJ_SERVICE', 'THIRD_PARTY_PJ_SERVICE',
        'HOSPITAL_INDEPENDENT', 'LABORATORY_INDEPENDENT',
    ];

    private const PF_TYPES = [
        'MEMBER_PF_SERVICE', 'POSTESTABLISHED_MEMBER_PF_SERVICE', 'COPAYMENT_MEMBER_PF_SERVICE',
    ];

    /** @return array{irrf: FederalWithholdingDecision, pcc: PccWithholdingResult, additional: list<FederalWithholdingDecision>} */
    public function evaluate(FactBag $facts): array
    {
        if ($facts->value('payment.occurred') !== true) {
            if ($facts->has('payment.accounting_credit_date')) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED,
                    'MEDICAL_COOPERATIVE_CREDIT_WITHOUT_PAYMENT_COMPONENT_BASIS_REQUIRED');
            }

            return $this->unresolved(FederalWithholdingStatus::PENDING_TRIGGER, 'MEDICAL_COOPERATIVE_PAYMENT_NOT_OCCURRED');
        }
        $required = ['payment.date', 'payment.gross_amount', 'cooperative.medical_role',
            'cooperative.health_plan_price_model', 'cooperative.invoice_components',
            'cooperative.invoice_components_evidence_confirmed'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_INVOICE_FACTS_MISSING', $missing);
        }
        if ($facts->value('cooperative.invoice_components_evidence_confirmed') !== true) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_INVOICE_UNPROVEN');
        }
        $date = (string) $facts->value('payment.date');
        $creditDate = $facts->value('payment.accounting_credit_date');
        $irDate = is_string($creditDate) && $creditDate < $date ? $creditDate : $date;
        if ($date >= '2027-01-01') {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_2027_COMPONENT_RULES_REQUIRED');
        }
        $role = (string) $facts->value('cooperative.medical_role');
        $priceModel = (string) $facts->value('cooperative.health_plan_price_model');
        if (($role === 'SINGULAR_INTERMEDIARY' && $priceModel !== 'NONE')
            || ($role === 'PLAN_OPERATOR' && $priceModel === 'NONE')) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_ROLE_PLAN_CONFLICT');
        }
        $gross = DecimalValue::fromString((string) $facts->value('payment.gross_amount'))->rounded(2);
        if ($gross->isNegative()) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_PAYMENT_INVALID');
        }
        $rows = $facts->value('cooperative.invoice_components');
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_COMPONENTS_INVALID');
        }

        $sum = DecimalValue::fromString('0');
        $pfBase = DecimalValue::fromString('0');
        $feeBase = DecimalValue::fromString('0');
        $operatorPccBase = DecimalValue::fromString('0');
        $pjBases = [];
        $pjDocuments = [];
        $seen = [];
        $downstream = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['line_id'] ?? null) || $row['line_id'] === ''
                || isset($seen[$row['line_id']]) || ! is_string($row['document_id'] ?? null)
                || $row['document_id'] === '' || ! in_array($row['type'] ?? null, self::TYPES, true)
                || ! is_string($row['amount'] ?? null)
                || preg_match('/^\d+(?:\.\d{1,2})?$/', $row['amount']) !== 1) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_COMPONENT_INVALID');
            }
            $seen[$row['line_id']] = true;
            $amount = DecimalValue::fromString($row['amount'])->rounded(2);
            $sum = $sum->plus($amount);
            $type = $row['type'];
            if (in_array($type, self::PF_TYPES, true)) {
                if ($type !== 'MEMBER_PF_SERVICE' && $role !== 'PLAN_OPERATOR') {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_COMPONENT_ROLE_CONFLICT');
                }
                if ($role === 'PLAN_OPERATOR' && $priceModel === 'PREESTABLISHED') {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_PRICE_MODEL_CONFLICT');
                }
                if ($type === 'POSTESTABLISHED_MEMBER_PF_SERVICE' && ! in_array($priceModel, ['POSTESTABLISHED', 'MIXED'], true)) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_PRICE_MODEL_CONFLICT');
                }
                $pfBase = $pfBase->plus($amount);
                if ($role === 'PLAN_OPERATOR') {
                    $operatorPccBase = $operatorPccBase->plus($amount);
                }

                continue;
            }
            if (in_array($type, self::PJ_TYPES, true)) {
                $taxId = $row['beneficiary_tax_id'] ?? null;
                if (! is_string($taxId) || preg_match('/^\d{14}$/', $taxId) !== 1
                    || ! in_array($row['beneficiary_tax_regime'] ?? null,
                        ['LUCRO_REAL', 'LUCRO_PRESUMIDO', 'SIMPLES_NACIONAL', 'MEI'], true)) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_PJ_BENEFICIARY_MISSING');
                }
                if (in_array($row['beneficiary_tax_regime'], ['SIMPLES_NACIONAL', 'MEI'], true)
                    && ($row['simples_declaration_received'] ?? null) !== true) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_PJ_SIMPLES_EVIDENCE_MISSING');
                }
                if (isset($pjBases[$taxId]) && $pjBases[$taxId]['tax_regime'] !== $row['beneficiary_tax_regime']) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_PJ_REGIME_CONFLICT');
                }
                $pjBases[$taxId] ??= ['amount' => DecimalValue::fromString('0'), 'tax_regime' => $row['beneficiary_tax_regime']];
                $pjBases[$taxId]['amount'] = $pjBases[$taxId]['amount']->plus($amount);
                $pjDocuments[$taxId][] = ['line_id' => $row['line_id'], 'document_id' => $row['document_id']];

                continue;
            }
            if (in_array($type, ['HOSPITAL_SUBORDINATED', 'LABORATORY_SUBORDINATED'], true)
                && (($row['institutional_subordination_confirmed'] ?? null) !== true
                    || ($row['performed_on_premises'] ?? null) !== true)) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_HOSPITAL_EXCLUSION_UNPROVEN');
            }
            if ($type === 'PREESTABLISHED_PLAN'
                && ($role !== 'PLAN_OPERATOR' || ! in_array($priceModel, ['PREESTABLISHED', 'MIXED'], true))) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_PREESTABLISHED_PLAN_CONFLICT');
            }
            if ($type === 'ADMINISTRATION_FEE') {
                $feeBase = $feeBase->plus($amount);
            }
            if (in_array($type, ['REIMBURSEMENT', 'MATERIAL'], true)
                && (($row['pass_through_evidence_confirmed'] ?? null) !== true
                    || ! is_string($row['nonwithholding_legal_basis'] ?? null)
                    || $row['nonwithholding_legal_basis'] === '')) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_PASS_THROUGH_UNPROVEN');
            }
            if ($type === 'NONMEMBER_PF_SERVICE') {
                $downstream[] = ['line_id' => $row['line_id'], 'action' => 'SEPARATE_INDIVIDUAL_PAYMENT_SCENARIO'];
            }
        }
        if ($sum->compareTo($gross) !== 0) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'MEDICAL_COOPERATIVE_INVOICE_PAYMENT_CONFLICT');
        }

        $zero = DecimalValue::fromString('0');
        $threshold = DecimalValue::fromString('10.00');
        $irAmount = $zero;
        $irLines = [];
        foreach ([['code' => '3280', 'base' => $pfBase], ['code' => '8045', 'base' => $feeBase]] as $group) {
            $base = $group['base'];
            if ($base->compareTo($zero) === 0) {
                continue;
            }
            $amount = $this->percent($base, $group['code'] === '3280' ? '1.50' : '1.50');
            if ($amount->compareTo($threshold) <= 0) {
                continue;
            }
            $irAmount = $irAmount->plus($amount);
            $irLines[] = ['line_id' => $group['code'], 'document_id' => null,
                'revenue_code' => $group['code'], 'taxes' => ['IRRF' => ['amount' => $amount->value()]]];
        }
        $irBasis = $pfBase->plus($feeBase);
        $ir = new FederalWithholdingDecision(FederalTax::IRRF,
            $irAmount->compareTo($zero) > 0 ? FederalWithholdingStatus::WITHHOLD
                : ($irBasis->compareTo($zero) > 0 ? FederalWithholdingStatus::DISPENSED_THRESHOLD : FederalWithholdingStatus::NOT_APPLICABLE),
            $irBasis->value(), null, $irAmount->value(), count($irLines) === 1 ? $irLines[0]['revenue_code'] : null,
            'PAYER', 'PAYMENT_OR_CREDIT', $irDate,
            ['MEDICAL_COOPERATIVE_IRRF_BY_COMPONENT'], ['MAFON 2025, código 3280', 'SC Cosit 15/2018'], [],
            ['line_calculations' => $irLines, 'downstream_actions' => $downstream]);

        $pccDecisions = [];
        $pccTotal = $zero;
        foreach (['CSLL' => ['rate' => '1.00', 'code' => '5987'],
            'COFINS' => ['rate' => '3.00', 'code' => '5960'],
            'PIS' => ['rate' => '0.65', 'code' => '5979']] as $taxName => $config) {
            $amount = $this->percent($operatorPccBase, $config['rate']);
            $pccTotal = $pccTotal->plus($amount);
            $pccDecisions[] = new FederalWithholdingDecision(FederalTax::from($taxName),
                $amount->compareTo($zero) > 0 ? FederalWithholdingStatus::WITHHOLD : FederalWithholdingStatus::NOT_APPLICABLE,
                $operatorPccBase->value(), $config['rate'], $amount->value(),
                $amount->compareTo($zero) > 0 ? $config['code'] : null,
                'PAYER', 'PAYMENT', $date, ['MEDICAL_COOPERATIVE_OPERATOR_COMPONENT'],
                ['SC Cosit 529/2017', 'SC Disit 2002/2021']);
        }
        if ($pccTotal->compareTo($threshold) <= 0) {
            $pccTotal = $zero;
            $pccDecisions = array_map(static fn (FederalWithholdingDecision $decision): FederalWithholdingDecision => $decision->status === FederalWithholdingStatus::WITHHOLD
                    ? new FederalWithholdingDecision($decision->tax, FederalWithholdingStatus::DISPENSED_THRESHOLD,
                        $decision->basis, $decision->rate, '0.00', null, 'PAYER', 'PAYMENT', $date,
                        ['MEDICAL_COOPERATIVE_PCC_BELOW_TEN'], $decision->legalBasis)
                    : $decision, $pccDecisions);
        }
        $pcc = new PccWithholdingResult($pccTotal->compareTo($zero) > 0 ? 'WITHHOLD' : 'NOT_WITHHELD',
            $pccDecisions, $gross->value(), $pccTotal->value(), $gross->minus($pccTotal)->value(),
            $pccTotal->compareTo($zero) > 0 ? '5952' : null, substr($date, 0, 7),
            'LAST_BUSINESS_DAY_SECOND_TEN_DAY_PERIOD_NEXT_MONTH', ['MEDICAL_COOPERATIVE_OPERATOR_COMPONENTS']);

        $additional = [];
        foreach ($pjBases as $taxId => $pj) {
            if (in_array($pj['tax_regime'], ['SIMPLES_NACIONAL', 'MEI'], true)) {
                continue;
            }
            $base = $pj['amount'];
            $details = ['beneficiary_tax_id' => (string) $taxId, 'source_documents' => $pjDocuments[$taxId]];
            $pjPccTotal = $this->percent($base, '4.65');
            foreach (['IRRF' => ['rate' => '1.50', 'code' => '1708'],
                'CSLL' => ['rate' => '1.00', 'code' => '5987'],
                'COFINS' => ['rate' => '3.00', 'code' => '5960'],
                'PIS' => ['rate' => '0.65', 'code' => '5979']] as $taxName => $config) {
                $amount = $this->percent($base, $config['rate']);
                if ($amount->compareTo($zero) === 0) {
                    continue;
                }
                if (($taxName === 'IRRF' && $amount->compareTo($threshold) <= 0)
                    || ($taxName !== 'IRRF' && $pjPccTotal->compareTo($threshold) <= 0)) {
                    continue;
                }
                $additional[] = new FederalWithholdingDecision(FederalTax::from($taxName),
                    FederalWithholdingStatus::WITHHOLD, $base->value(), $config['rate'], $amount->value(),
                    $config['code'], 'PAYER', $taxName === 'IRRF' ? 'PAYMENT_OR_CREDIT' : 'PAYMENT',
                    $taxName === 'IRRF' ? $irDate : $date,
                    ['MEDICAL_COOPERATIVE_PJ_BENEFICIARY'], ['SC Cosit 15/2018', 'SC Cosit 529/2017'], [], $details);
            }
        }

        return ['irrf' => $ir, 'pcc' => $pcc, 'additional' => $additional];
    }

    private function percent(DecimalValue $base, string $rate): DecimalValue
    {
        return $base->multipliedBy(DecimalValue::fromString($rate))
            ->dividedBy(DecimalValue::fromString('100'), 8)->rounded(2);
    }

    /**
     * @param  list<string>  $missing
     * @return array{irrf: FederalWithholdingDecision, pcc: PccWithholdingResult, additional: list<FederalWithholdingDecision>}
     */
    private function unresolved(FederalWithholdingStatus $status, string $reason, array $missing = []): array
    {
        $ir = new FederalWithholdingDecision(FederalTax::IRRF, $status, null, null, null, null,
            'PAYER', 'PAYMENT_OR_CREDIT', null, [$reason], ['SC Cosit 15/2018', 'SC Cosit 529/2017'], $missing);
        $decisions = [];
        foreach ([FederalTax::PIS, FederalTax::COFINS, FederalTax::CSLL] as $tax) {
            $decisions[] = new FederalWithholdingDecision($tax, $status, null, null, null, null,
                'PAYER', 'PAYMENT', null, [$reason], ['SC Cosit 529/2017'], $missing);
        }

        return ['irrf' => $ir, 'pcc' => new PccWithholdingResult($status->value, $decisions,
            null, null, null, null, null, 'LAST_BUSINESS_DAY_SECOND_TEN_DAY_PERIOD_NEXT_MONTH',
            [$reason], $missing), 'additional' => []];
    }
}
