<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final class IrrfWithholdingEngine
{
    /** @var array<string, array{rate: string, code: string, reason: string}> */
    private const DOMESTIC_CATEGORIES = [
        'PROFESSIONAL_SERVICE' => ['rate' => '1.50', 'code' => '1708', 'reason' => 'IRRF_PROFESSIONAL_15'],
        'CLEANING' => ['rate' => '1.00', 'code' => '1708', 'reason' => 'IRRF_CLEANING_1'],
        'CONSERVATION' => ['rate' => '1.00', 'code' => '1708', 'reason' => 'IRRF_CONSERVATION_1'],
        'SECURITY' => ['rate' => '1.00', 'code' => '1708', 'reason' => 'IRRF_SECURITY_1'],
        'SURVEILLANCE' => ['rate' => '1.00', 'code' => '1708', 'reason' => 'IRRF_VIGILANCE_1'],
        'LABOR_LEASING' => ['rate' => '1.00', 'code' => '1708', 'reason' => 'IRRF_LABOR_SUPPLY_1'],
        'CREDIT_ADVISORY' => ['rate' => '1.50', 'code' => '5944', 'reason' => 'IRRF_CREDIT_ADVISORY_15'],
        'MARKET_ADVISORY' => ['rate' => '1.50', 'code' => '5944', 'reason' => 'IRRF_MARKETING_ADVISORY_15'],
        'CREDIT_MANAGEMENT' => ['rate' => '1.50', 'code' => '5944', 'reason' => 'IRRF_CREDIT_MANAGEMENT_15'],
        'RISK_SELECTION' => ['rate' => '1.50', 'code' => '5944', 'reason' => 'IRRF_RISK_SELECTION_15'],
        'ACCOUNTS_PAYABLE_MANAGEMENT' => ['rate' => '1.50', 'code' => '5944', 'reason' => 'IRRF_AP_AR_MANAGEMENT_15'],
        'ACCOUNTS_RECEIVABLE_MANAGEMENT' => ['rate' => '1.50', 'code' => '5944', 'reason' => 'IRRF_AP_AR_MANAGEMENT_15'],
        'COMMISSION' => ['rate' => '1.50', 'code' => '8045', 'reason' => 'IRRF_COMMISSION_15'],
        'BROKERAGE' => ['rate' => '1.50', 'code' => '8045', 'reason' => 'IRRF_BROKERAGE_15'],
        'ADVERTISING' => ['rate' => '1.50', 'code' => '8045', 'reason' => 'IRRF_ADVERTISING_15'],
        'COOPERATIVE_PERSONAL_SERVICE' => ['rate' => '1.50', 'code' => '3280', 'reason' => 'IRRF_COOPERATIVE_PERSONAL_SERVICE_15'],
        'SOFTWARE_PROGRAMMING' => ['rate' => '1.50', 'code' => '1708', 'reason' => 'IRRF_SOFTWARE_PROGRAMMING_15'],
    ];

    public function __construct(
        private readonly IndividualIrrfWithholdingEngine $individual = new IndividualIrrfWithholdingEngine,
        private readonly ForeignIrrfWithholdingEngine $foreign = new ForeignIrrfWithholdingEngine,
    ) {}

    public function evaluate(FactBag $facts): FederalWithholdingDecision
    {
        $partyMissing = $facts->missing(['payer.person_type', 'provider.person_type']);
        if ($partyMissing !== []) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, null, ['IRRF_PARTY_FACTS_MISSING'], $partyMissing);
        }
        $payerType = (string) $facts->value('payer.person_type');
        $providerType = (string) $facts->value('provider.person_type');
        if ($providerType === 'FOREIGN_LEGAL_ENTITY') {
            return $this->foreign->evaluate($facts);
        }
        if (str_contains($payerType, 'PUBLIC_ADMINISTRATION')) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, null, ['IRRF_PUBLIC_PROFILE_REQUIRED']);
        }
        if ($providerType === 'INDIVIDUAL') {
            return $this->individual->evaluate($facts);
        }

        $triggerDate = $this->triggerDate($facts);
        if ($triggerDate === null) {
            return $this->result(FederalWithholdingStatus::PENDING_TRIGGER, null, null, null, null, null, ['IRRF_PAYMENT_OR_CREDIT_NOT_OCCURRED']);
        }

        $required = ['provider.tax_regime', 'payment.gross_amount', 'federal.service_category'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_FACTS_MISSING'], $missing);
        }
        $category = (string) $facts->value('federal.service_category');
        if ($providerType === 'COOPERATIVE' && $facts->hasDefinition('cooperative.act_type')
            && $facts->value('cooperative.provider_type') === 'PRODUCTION'
            && $category === 'OTHER') {
            $missing = $facts->missing(['cooperative.act_type', 'cooperative.act_legal_basis']);
            if ($missing !== []) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate,
                    ['IRRF_PRODUCTION_COOPERATIVE_CLASSIFICATION_MISSING'], $missing);
            }

            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', null, $triggerDate,
                ['IRRF_PRODUCTION_COOPERATIVE_GOODS_OUTSIDE_SERVICE_WITHHOLDING']);
        }
        if ($providerType === 'COOPERATIVE' && $category !== 'COOPERATIVE_PERSONAL_SERVICE') {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_COOPERATIVE_SERVICE_CLASSIFICATION_REQUIRED'], ['federal.service_category']);
        }
        if ($providerType === 'COOPERATIVE' && $facts->hasDefinition('cooperative.provider_type')) {
            $missing = $facts->missing(['cooperative.provider_type']);
            if ($missing !== []) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_COOPERATIVE_TYPE_REQUIRED'], $missing);
            }
            if (! in_array($facts->value('cooperative.provider_type'), ['WORK', 'TRANSPORT'], true)) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_COOPERATIVE_TYPE_RULE_REQUIRED']);
            }
        }
        if (! in_array($payerType, ['PRIVATE_LEGAL_ENTITY', 'COOPERATIVE'], true)
            || ! in_array($providerType, ['PRIVATE_LEGAL_ENTITY', 'COOPERATIVE'], true)) {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', null, $triggerDate, ['IRRF_DOMESTIC_PJ_SCOPE_NOT_MET']);
        }

        if ($providerType === 'PRIVATE_LEGAL_ENTITY'
            && in_array($facts->value('provider.tax_regime'), ['SIMPLES_NACIONAL', 'MEI'], true)) {
            $evidenceMissing = $facts->missing(['provider.revenue_is_own', 'provider.simples_declaration_received']);
            if ($evidenceMissing !== []) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_SIMPLE_BENEFICIARY_EVIDENCE_MISSING'], $evidenceMissing);
            }
            if ($facts->value('provider.revenue_is_own') === true && $facts->value('provider.simples_declaration_received') === true) {
                return $this->result(FederalWithholdingStatus::EXEMPT, null, null, '0.00', null, $triggerDate, ['IRRF_SIMPLE_BENEFICIARY_EXEMPT']);
            }
        }

        if ($category === 'SOFTWARE_LICENSE_ONLY') {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', null, $triggerDate, ['IRRF_SOFTWARE_LICENSE_NO_PROGRAMMING_NO_WITHHOLD']);
        }
        if (in_array($category, ['UNKNOWN', 'MIXED'], true)) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_SERVICE_CLASSIFICATION_REQUIRED'], ['federal.service_category']);
        }
        $configuration = self::DOMESTIC_CATEGORIES[$category] ?? null;
        if ($configuration === null) {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', null, $triggerDate, ['IRRF_SERVICE_OUTSIDE_DOMESTIC_WITHHOLDING_LIST']);
        }

        $basis = DecimalValue::fromString((string) $facts->value('payment.gross_amount'));
        if ($basis->isNegative()) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_PAYMENT_AMOUNT_INVALID'], ['payment.gross_amount']);
        }
        if (in_array($category, ['ADVERTISING', 'COOPERATIVE_PERSONAL_SERVICE'], true)) {
            if ($category === 'COOPERATIVE_PERSONAL_SERVICE'
                && $providerType === 'COOPERATIVE'
                && $facts->value('cooperative.provider_type') === 'TRANSPORT'
                && $facts->hasDefinition('cooperative.transport_kind')) {
                $transportMissing = $facts->missing(['cooperative.transport_kind', 'cooperative.transport_service_amount', 'cooperative.transport_invoice_split_confirmed']);
                if ($transportMissing !== []) {
                    return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_COOPERATIVE_TRANSPORT_FACTS_MISSING'], $transportMissing);
                }
                $transportAmount = DecimalValue::fromString((string) $facts->value('cooperative.transport_service_amount'));
                if ($facts->value('cooperative.transport_invoice_split_confirmed') !== true
                    || $transportAmount->isNegative() || $transportAmount->compareTo($basis) > 0) {
                    return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_COOPERATIVE_TRANSPORT_SPLIT_UNPROVEN']);
                }
                $share = $facts->value('cooperative.transport_kind') === 'CARGO' ? '10' : '60';
                $basis = $transportAmount->multipliedBy(DecimalValue::fromString($share))
                    ->dividedBy(DecimalValue::fromString('100'), 8)->rounded(2);
            } else {
                if (! $facts->has('federal.irrf_taxable_base')) {
                    return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_SPECIAL_BASE_MISSING'], ['federal.irrf_taxable_base']);
                }
                $basis = DecimalValue::fromString((string) $facts->value('federal.irrf_taxable_base'));
            }
        }
        $responsibleParty = 'PAYER';
        if (in_array($category, ['COMMISSION', 'BROKERAGE', 'ADVERTISING'], true)) {
            if (! $facts->has('federal.irrf_collection_mode')) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, null, $triggerDate, ['IRRF_COLLECTION_MODE_MISSING'], ['federal.irrf_collection_mode']);
            }
            $responsibleParty = $facts->value('federal.irrf_collection_mode') === 'BENEFICIARY_SELF_WITHHOLDS' ? 'BENEFICIARY' : 'PAYER';
        }
        $amount = $basis
            ->multipliedBy(DecimalValue::fromString($configuration['rate']))
            ->dividedBy(DecimalValue::fromString('100'), 8)
            ->rounded(2);
        if ($amount->compareTo(DecimalValue::fromString('10.00')) <= 0) {
            return new FederalWithholdingDecision(
                FederalTax::IRRF,
                FederalWithholdingStatus::DISPENSED_THRESHOLD,
                $basis->rounded(2)->value(),
                $configuration['rate'],
                '0.00',
                null,
                $responsibleParty,
                'PAYMENT_OR_CREDIT',
                $triggerDate,
                ['IRRF_BELOW_10'],
                ['Lei 9.430/1996, art. 67'],
            );
        }

        return new FederalWithholdingDecision(
            FederalTax::IRRF,
            FederalWithholdingStatus::WITHHOLD,
            $basis->rounded(2)->value(),
            $configuration['rate'],
            $amount->value(),
            $configuration['code'],
            $responsibleParty,
            'PAYMENT_OR_CREDIT',
            $triggerDate,
            [$configuration['reason']],
            ['RIR/2018, arts. 714 e 716', 'Lei 10.833/2003, art. 29'],
        );
    }

    private function triggerDate(FactBag $facts): ?string
    {
        $dates = [];
        if ($facts->value('payment.occurred') === true && is_string($facts->value('payment.date'))) {
            $dates[] = $facts->value('payment.date');
        }
        if (is_string($facts->value('payment.accounting_credit_date'))) {
            $dates[] = $facts->value('payment.accounting_credit_date');
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
            'PAYMENT_OR_CREDIT',
            $triggerDate,
            $reasonCodes,
            ['RIR/2018'],
            $missingFacts,
        );
    }
}
