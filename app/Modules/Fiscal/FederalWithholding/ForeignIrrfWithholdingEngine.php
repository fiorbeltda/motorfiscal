<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final class ForeignIrrfWithholdingEngine
{
    private const CATEGORIES = [
        'GENERAL_SERVICE' => ['rate' => '25.00', 'reason' => 'IRRF_FOREIGN_GENERAL_SERVICE_25'],
        'TECHNICAL_OR_ADMINISTRATIVE_ASSISTANCE' => ['rate' => '15.00', 'reason' => 'IRRF_FOREIGN_TECHNICAL_SERVICE_15'],
        'SOFTWARE_CUSTOM_DEVELOPMENT' => ['rate' => '15.00', 'reason' => 'IRRF_FOREIGN_CUSTOM_SOFTWARE_15'],
        'SOFTWARE_LICENSE_ROYALTY' => ['rate' => '15.00', 'reason' => 'IRRF_FOREIGN_SOFTWARE_ROYALTY_15'],
    ];

    public function evaluate(FactBag $facts): FederalWithholdingDecision
    {
        $triggerDate = $this->triggerDate($facts);
        if ($triggerDate === null) {
            return $this->result(FederalWithholdingStatus::PENDING_TRIGGER, null, null, null, null, null, ['IRRF_FOREIGN_TRIGGER_NOT_OCCURRED']);
        }
        $missing = $facts->missing([
            'payer.person_type',
            'federal.foreign_income_category',
            'federal.foreign_taxable_base',
            'provider.favored_tax_jurisdiction',
            'federal.foreign_treaty_applicable',
            'federal.foreign_revenue_code',
        ]);
        if ($missing !== []) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_FOREIGN_FACTS_MISSING'], $missing);
        }
        if ($facts->value('payer.person_type') === 'FOREIGN') {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', null, $triggerDate, ['IRRF_FOREIGN_PAYER_OUTSIDE_BRAZILIAN_SOURCE_SCOPE']);
        }

        $category = (string) $facts->value('federal.foreign_income_category');
        $configuration = self::CATEGORIES[$category] ?? null;
        if ($configuration === null) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_FOREIGN_INCOME_CLASSIFICATION_UNSUPPORTED']);
        }
        $basis = DecimalValue::fromString((string) $facts->value('federal.foreign_taxable_base'));
        if ($basis->isNegative()) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_FOREIGN_BASE_INVALID']);
        }

        $rate = $facts->value('provider.favored_tax_jurisdiction') === true ? '25.00' : $configuration['rate'];
        $reason = $facts->value('provider.favored_tax_jurisdiction') === true
            ? 'IRRF_FOREIGN_FAVORED_JURISDICTION_25'
            : $configuration['reason'];
        $legalBasis = ['RIR/2018, remessas ao exterior'];
        if ($facts->value('federal.foreign_treaty_applicable') === true) {
            $treatyMissing = $facts->missing([
                'federal.foreign_treaty_validated',
                'federal.foreign_treaty_rate',
                'federal.foreign_treaty_legal_basis',
            ]);
            if ($treatyMissing !== []) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, $basis->value(), null, null, null, $triggerDate, ['IRRF_FOREIGN_TREATY_FACTS_MISSING'], $treatyMissing);
            }
            if ($facts->value('federal.foreign_treaty_validated') !== true) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, $basis->value(), null, null, null, $triggerDate, ['IRRF_FOREIGN_TREATY_NOT_VALIDATED']);
            }
            $rate = (string) $facts->value('federal.foreign_treaty_rate');
            $legalBasis[] = (string) $facts->value('federal.foreign_treaty_legal_basis');
            $reason = 'IRRF_FOREIGN_TREATY_RATE_APPLIED';
        }

        $rateDecimal = DecimalValue::fromString($rate);
        if ($rateDecimal->isNegative() || $rateDecimal->compareTo(DecimalValue::fromString('100')) > 0) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, $basis->value(), null, null, null, $triggerDate, ['IRRF_FOREIGN_RATE_INVALID']);
        }
        $amount = $basis->multipliedBy($rateDecimal)->dividedBy(DecimalValue::fromString('100'), 8)->rounded(2);

        return new FederalWithholdingDecision(
            FederalTax::IRRF,
            $amount->compareTo(DecimalValue::fromString('0')) === 0 ? FederalWithholdingStatus::ZERO_RATE : FederalWithholdingStatus::WITHHOLD,
            $basis->rounded(2)->value(),
            $rateDecimal->value(),
            $amount->value(),
            (string) $facts->value('federal.foreign_revenue_code'),
            'PAYER',
            'PAYMENT_CREDIT_DELIVERY_EMPLOYMENT_OR_REMITTANCE',
            $triggerDate,
            [$reason],
            $legalBasis,
            [],
            [
                'income_category' => $category,
                'favored_tax_jurisdiction' => $facts->value('provider.favored_tax_jurisdiction'),
                'reporting' => ['event' => 'R-4020', 'beneficiary' => 'FOREIGN_LEGAL_ENTITY'],
            ],
        );
    }

    private function triggerDate(FactBag $facts): ?string
    {
        $dates = [];
        if ($facts->value('payment.occurred') === true && is_string($facts->value('payment.date'))) {
            $dates[] = $facts->value('payment.date');
        }
        foreach (['payment.accounting_credit_date', 'payment.remittance_date', 'payment.delivery_or_employment_date'] as $fact) {
            if (is_string($facts->value($fact))) {
                $dates[] = $facts->value($fact);
            }
        }
        sort($dates, SORT_STRING);

        return $dates[0] ?? null;
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
        ?string $code,
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
            $code,
            'PAYER',
            'PAYMENT_CREDIT_DELIVERY_EMPLOYMENT_OR_REMITTANCE',
            $triggerDate,
            $reasonCodes,
            ['RIR/2018, remessas ao exterior'],
            $missingFacts,
        );
    }
}
