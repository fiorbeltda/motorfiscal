<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final class IndividualIrrfWithholdingEngine
{
    public function evaluate(FactBag $facts): FederalWithholdingDecision
    {
        if ($facts->value('payment.occurred') !== true || ! is_string($facts->value('payment.date'))) {
            return $this->result(FederalWithholdingStatus::PENDING_TRIGGER, null, null, null, null, ['IRRF_PF_PAYMENT_NOT_OCCURRED']);
        }
        $paymentDate = (string) $facts->value('payment.date');
        if ($paymentDate < '2026-01-01' || $paymentDate > '2026-12-31') {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $paymentDate, ['IRRF_PF_PROGRESSIVE_TABLE_VERSION_MISSING']);
        }
        $missing = $facts->missing([
            'payer.person_type',
            'payment.gross_amount',
            'federal.pf_monthly_taxable_income',
            'federal.pf_monthly_taxable_base',
            'federal.pf_service_relationship',
        ]);
        if ($missing !== []) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $paymentDate, ['IRRF_PF_FACTS_MISSING'], $missing);
        }
        if ($facts->value('payer.person_type') !== 'PRIVATE_LEGAL_ENTITY') {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $paymentDate, ['IRRF_PF_PRIVATE_PAYER_SCOPE_NOT_MET']);
        }

        return $this->calculate($paymentDate,
            (string) $facts->value('federal.pf_monthly_taxable_income'),
            (string) $facts->value('federal.pf_monthly_taxable_base'),
            (string) $facts->value('federal.pf_service_relationship'));
    }

    public function calculate(string $paymentDate, string $monthlyIncome, string $monthlyBase,
        string $relationship): FederalWithholdingDecision
    {
        if ($paymentDate < '2026-01-01' || $paymentDate > '2026-12-31') {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $paymentDate, ['IRRF_PF_PROGRESSIVE_TABLE_VERSION_MISSING']);
        }
        $income = DecimalValue::fromString($monthlyIncome);
        $basis = DecimalValue::fromString($monthlyBase);
        if ($income->isNegative() || $basis->isNegative() || $basis->compareTo($income) > 0) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $paymentDate, ['IRRF_PF_MONTHLY_BASE_INVALID']);
        }

        [$rate, $deduction] = $this->bracket($basis);
        $preliminary = $basis
            ->multipliedBy(DecimalValue::fromString($rate))
            ->dividedBy(DecimalValue::fromString('100'), 8)
            ->minus(DecimalValue::fromString($deduction));
        if ($preliminary->isNegative()) {
            $preliminary = DecimalValue::fromString('0');
        }

        $reduction = DecimalValue::fromString('0');
        if ($income->compareTo(DecimalValue::fromString('5000.00')) <= 0) {
            $maximumReduction = DecimalValue::fromString('312.89');
            $reduction = $preliminary->compareTo($maximumReduction) < 0 ? $preliminary : $maximumReduction;
        } elseif ($income->compareTo(DecimalValue::fromString('7350.00')) <= 0) {
            $reduction = DecimalValue::fromString('978.62')
                ->minus($income->multipliedBy(DecimalValue::fromString('0.133145')));
            if ($reduction->isNegative()) {
                $reduction = DecimalValue::fromString('0');
            }
            if ($reduction->compareTo($preliminary) > 0) {
                $reduction = $preliminary;
            }
        }

        $amount = $preliminary->minus($reduction)->rounded(2);
        $status = $amount->compareTo(DecimalValue::fromString('0')) === 0
            ? FederalWithholdingStatus::ZERO_RATE
            : FederalWithholdingStatus::WITHHOLD;

        return new FederalWithholdingDecision(
            FederalTax::IRRF,
            $status,
            $basis->rounded(2)->value(),
            $rate,
            $amount->value(),
            $status === FederalWithholdingStatus::WITHHOLD ? '0588' : null,
            'PAYER',
            'PAYMENT',
            $paymentDate,
            [$status === FederalWithholdingStatus::WITHHOLD ? 'IRRF_PF_2026_PROGRESSIVE_WITHHOLDING' : 'IRRF_PF_2026_REDUCTION_ZEROED_TAX'],
            ['Lei 7.713/1988', 'Tabela mensal do IRPF 2026'],
            [],
            [
                'monthly_taxable_income' => $income->rounded(2)->value(),
                'bracket_deduction' => $deduction,
                'tax_before_2026_reduction' => $preliminary->rounded(2)->value(),
                'monthly_2026_reduction' => $reduction->rounded(2)->value(),
                'dependent_monthly_deduction_reference' => '189.59',
                'simplified_monthly_discount_max_reference' => '607.20',
                'reporting' => [
                    'event' => $relationship === 'EMPLOYMENT_OR_WORK_RELATIONSHIP' ? 'ESOCIAL' : 'R-4010',
                    'period_basis' => 'PAYMENT_DATE',
                ],
            ],
        );
    }

    /** @return array{string, string} */
    private function bracket(DecimalValue $basis): array
    {
        if ($basis->compareTo(DecimalValue::fromString('2428.80')) <= 0) {
            return ['0.00', '0.00'];
        }
        if ($basis->compareTo(DecimalValue::fromString('2826.65')) <= 0) {
            return ['7.50', '182.16'];
        }
        if ($basis->compareTo(DecimalValue::fromString('3751.05')) <= 0) {
            return ['15.00', '394.16'];
        }
        if ($basis->compareTo(DecimalValue::fromString('4664.68')) <= 0) {
            return ['22.50', '675.49'];
        }

        return ['27.50', '908.73'];
    }

    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $missingFacts
     */
    private function result(
        FederalWithholdingStatus $status,
        ?string $basis,
        ?string $rate,
        ?string $amount,
        ?string $triggerDate,
        array $reasonCodes,
        array $missingFacts = [],
    ): FederalWithholdingDecision {
        return new FederalWithholdingDecision(
            FederalTax::IRRF,
            $status,
            $basis,
            $rate,
            $amount,
            null,
            'PAYER',
            'PAYMENT',
            $triggerDate,
            $reasonCodes,
            ['Lei 7.713/1988', 'Tabela mensal do IRPF 2026'],
            $missingFacts,
        );
    }
}
