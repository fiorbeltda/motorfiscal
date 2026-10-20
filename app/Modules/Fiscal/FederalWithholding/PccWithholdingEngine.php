<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final class PccWithholdingEngine
{
    private const ELIGIBLE_CATEGORIES = [
        'CLEANING', 'CONSERVATION', 'MAINTENANCE', 'SECURITY', 'SURVEILLANCE',
        'VALUE_TRANSPORT', 'LABOR_LEASING', 'CREDIT_ADVISORY', 'MARKET_ADVISORY',
        'CREDIT_MANAGEMENT', 'RISK_SELECTION', 'ACCOUNTS_PAYABLE_MANAGEMENT',
        'ACCOUNTS_RECEIVABLE_MANAGEMENT', 'PROFESSIONAL_SERVICE',
    ];

    private const COMPONENTS = [
        'CSLL' => ['rate' => '1.00', 'code' => '5987'],
        'COFINS' => ['rate' => '3.00', 'code' => '5960'],
        'PIS' => ['rate' => '0.65', 'code' => '5979'],
    ];

    public function evaluate(FactBag $facts): PccWithholdingResult
    {
        if ($facts->value('payment.occurred') !== true) {
            return $this->uniformResult(FederalWithholdingStatus::PENDING_TRIGGER, 'PCC_PAYMENT_NOT_OCCURRED');
        }
        $required = ['payment.date', 'payer.person_type', 'provider.person_type'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return new PccWithholdingResult(
                'ACTION_REQUIRED',
                [],
                null,
                null,
                null,
                null,
                null,
                'LAST_BUSINESS_DAY_SECOND_TEN_DAY_PERIOD_NEXT_MONTH',
                ['PCC_FACTS_MISSING'],
                $missing,
            );
        }

        $paymentDate = (string) $facts->value('payment.date');
        $payerType = (string) $facts->value('payer.person_type');
        if (in_array($payerType, ['FEDERAL_PUBLIC_ADMINISTRATION', 'STATE_PUBLIC_ADMINISTRATION', 'DISTRICT_PUBLIC_ADMINISTRATION', 'MUNICIPAL_PUBLIC_ADMINISTRATION'], true)) {
            return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_PUBLIC_PAYER_REQUIRES_SPECIAL_RULESET');
        }
        if (! in_array($payerType, ['PRIVATE_LEGAL_ENTITY', 'COOPERATIVE'], true)) {
            return $this->uniformResult(FederalWithholdingStatus::NOT_APPLICABLE, 'PCC_PAYER_OUTSIDE_PRIVATE_LEGAL_ENTITY_SCOPE');
        }
        $providerCooperative = $facts->value('provider.person_type') === 'COOPERATIVE';
        if (! in_array($facts->value('provider.person_type'), ['PRIVATE_LEGAL_ENTITY', 'COOPERATIVE'], true)) {
            return $this->uniformResult(FederalWithholdingStatus::NOT_APPLICABLE, 'PCC_RECIPIENT_OUTSIDE_PRIVATE_LEGAL_ENTITY_SCOPE');
        }
        $required = ['payment.gross_amount', 'payer.tax_regime', 'provider.tax_regime', 'federal.service_category'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_FACTS_MISSING', $missing);
        }
        $gross = DecimalValue::fromString((string) $facts->value('payment.gross_amount'));
        if ($gross->isNegative()) {
            return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_PAYMENT_AMOUNT_INVALID', ['payment.gross_amount']);
        }
        $basis = $gross;
        if ($providerCooperative) {
            if ($facts->hasDefinition('cooperative.act_type')
                && $facts->value('cooperative.provider_type') === 'PRODUCTION'
                && $facts->value('federal.service_category') === 'OTHER') {
                $missing = $facts->missing(['cooperative.act_type', 'cooperative.act_legal_basis']);
                if ($missing !== []) {
                    return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED,
                        'PCC_PRODUCTION_COOPERATIVE_CLASSIFICATION_MISSING', $missing);
                }

                return $this->uniformResult(FederalWithholdingStatus::NOT_APPLICABLE,
                    'PCC_PRODUCTION_COOPERATIVE_GOODS_OUTSIDE_ARTICLE_30');
            }
            if (! $facts->hasDefinition('cooperative.pcc_taxable_base')) {
                return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_SPECIAL_PROFILE_REQUIRED');
            }
            if ($facts->value('cooperative.provider_type') === 'TRANSPORT'
                && $facts->hasDefinition('cooperative.transport_kind')
                && $facts->value('federal.service_category') === 'COOPERATIVE_PERSONAL_SERVICE') {
                $missing = $facts->missing(['cooperative.transport_kind', 'cooperative.transport_invoice_split_confirmed']);
                if ($missing !== []) {
                    return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_TRANSPORT_FACTS_MISSING', $missing);
                }
                if ($facts->value('cooperative.transport_invoice_split_confirmed') !== true) {
                    return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_TRANSPORT_SPLIT_UNPROVEN');
                }

                return $this->uniformResult(FederalWithholdingStatus::NOT_APPLICABLE, 'PCC_CARGO_PASSENGER_TRANSPORT_OUTSIDE_ARTICLE_30');
            }
            $missing = $facts->missing(['cooperative.provider_type', 'cooperative.pcc_taxable_base', 'cooperative.pcc_basis_evidence_confirmed']);
            if ($missing !== []) {
                return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_BASE_FACTS_MISSING', $missing);
            }
            if ($facts->value('cooperative.provider_type') !== 'WORK') {
                return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_TYPE_RULE_REQUIRED');
            }
            $basis = DecimalValue::fromString((string) $facts->value('cooperative.pcc_taxable_base'));
            if ($basis->isNegative() || $basis->compareTo($gross) > 0
                || $facts->value('cooperative.pcc_basis_evidence_confirmed') !== true) {
                return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_BASE_UNPROVEN');
            }
            if ($facts->hasDefinition('cooperative.act_type')) {
                $missing = $facts->missing(['cooperative.act_type', 'cooperative.act_legal_basis',
                    'cooperative.invoice_components', 'cooperative.invoice_components_evidence_confirmed']);
                if ($missing !== []) {
                    return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_ACT_AND_COMPONENTS_MISSING', $missing);
                }
                if ($facts->value('cooperative.invoice_components_evidence_confirmed') !== true) {
                    return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_COMPONENTS_UNPROVEN');
                }
                $rows = $facts->value('cooperative.invoice_components');
                if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
                    return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_COMPONENTS_INVALID');
                }
                $sum = DecimalValue::fromString('0');
                $taxable = DecimalValue::fromString('0');
                $seen = [];
                foreach ($rows as $row) {
                    if (! is_array($row) || ! is_string($row['line_id'] ?? null) || $row['line_id'] === ''
                        || isset($seen[$row['line_id']]) || ! is_string($row['document_id'] ?? null)
                        || $row['document_id'] === '' || ! is_string($row['amount'] ?? null)
                        || preg_match('/^\d+(?:\.\d{1,2})?$/', $row['amount']) !== 1
                        || ! is_bool($row['pcc_taxable'] ?? null)
                        || ! is_string($row['pcc_treatment_legal_basis'] ?? null)
                        || $row['pcc_treatment_legal_basis'] === '') {
                        return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_COMPONENT_INVALID');
                    }
                    $seen[$row['line_id']] = true;
                    $amount = DecimalValue::fromString($row['amount']);
                    $sum = $sum->plus($amount);
                    if ($row['pcc_taxable']) {
                        $taxable = $taxable->plus($amount);
                    }
                }
                if ($sum->compareTo($gross) !== 0 || $taxable->compareTo($basis) !== 0) {
                    return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COOPERATIVE_COMPONENT_RECONCILIATION_FAILED');
                }
            }
        }
        if (in_array($facts->value('payer.tax_regime'), ['SIMPLES_NACIONAL', 'MEI'], true)) {
            return $this->uniformResult(FederalWithholdingStatus::NOT_APPLICABLE, 'PCC_SIMPLES_PAYER_DISPENSED');
        }
        if (in_array($facts->value('provider.tax_regime'), ['SIMPLES_NACIONAL', 'MEI'], true)) {
            $evidenceFacts = ['provider.revenue_is_own', 'provider.simples_declaration_received'];
            $evidenceMissing = $facts->missing($evidenceFacts);
            if ($evidenceMissing !== []) {
                return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_SIMPLES_RECIPIENT_EVIDENCE_MISSING', $evidenceMissing);
            }
            if ($facts->value('provider.revenue_is_own') === true && $facts->value('provider.simples_declaration_received') === true) {
                return $this->uniformResult(FederalWithholdingStatus::EXEMPT, 'PCC_SIMPLES_RECIPIENT_OWN_REVENUE');
            }
        }

        $category = (string) $facts->value('federal.service_category');
        if ($category === 'UNKNOWN' || $category === 'MIXED') {
            return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_SERVICE_CLASSIFICATION_REQUIRED', ['federal.service_category']);
        }
        if (! in_array($category, self::ELIGIBLE_CATEGORIES, true)
            && ! ($providerCooperative && $category === 'COOPERATIVE_PERSONAL_SERVICE')) {
            return $this->uniformResult(FederalWithholdingStatus::NOT_APPLICABLE, 'PCC_SERVICE_OUTSIDE_ARTICLE_30');
        }

        $activeComponents = $paymentDate >= '2027-01-01'
            ? ($providerCooperative ? [] : ['CSLL'])
            : ($providerCooperative ? ['COFINS', 'PIS'] : ['CSLL', 'COFINS', 'PIS']);
        $statusFacts = array_map(static fn (string $tax): string => 'federal.'.strtolower($tax).'_status', $activeComponents);
        $statusMissing = $facts->missing($statusFacts);
        if ($statusMissing !== []) {
            return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COMPONENT_STATUS_MISSING', $statusMissing);
        }

        $decisions = [];
        $total = DecimalValue::fromString('0');
        $withheldCount = 0;
        foreach (self::COMPONENTS as $taxName => $configuration) {
            $tax = FederalTax::from($taxName);
            if (! in_array($taxName, $activeComponents, true)) {
                $reason = $providerCooperative && $taxName === 'CSLL' ? 'PCC_COOPERATIVE_CSLL_EXCLUDED' : 'PCC_COMPONENT_SUNSET';
                $decisions[] = $this->decision($tax, FederalWithholdingStatus::NOT_APPLICABLE, $basis, null, '0.00', null, $paymentDate, $reason,
                    $providerCooperative && $taxName === 'CSLL' ? ['Lei 10.833/2003, art. 32, I'] : []);

                continue;
            }
            $componentStatus = (string) $facts->value('federal.'.strtolower($taxName).'_status');
            if ($componentStatus !== 'APPLICABLE') {
                $mappedStatus = match ($componentStatus) {
                    'EXEMPT' => FederalWithholdingStatus::EXEMPT,
                    'ZERO_RATE' => FederalWithholdingStatus::ZERO_RATE,
                    'SUSPENDED' => FederalWithholdingStatus::SUSPENDED,
                    default => FederalWithholdingStatus::ACTION_REQUIRED,
                };
                $basisFact = 'federal.'.strtolower($taxName).'_exception_legal_basis';
                if ($mappedStatus !== FederalWithholdingStatus::ACTION_REQUIRED && ! $facts->has($basisFact)) {
                    return $this->uniformResult(FederalWithholdingStatus::ACTION_REQUIRED, 'PCC_COMPONENT_EXCEPTION_BASIS_MISSING', [$basisFact]);
                }
                $decisions[] = $this->decision(
                    $tax,
                    $mappedStatus,
                    $basis,
                    '0',
                    '0.00',
                    null,
                    $paymentDate,
                    'PCC_COMPONENT_'.$componentStatus,
                    [(string) $facts->value($basisFact)],
                );

                continue;
            }

            $amount = $basis
                ->multipliedBy(DecimalValue::fromString($configuration['rate']))
                ->dividedBy(DecimalValue::fromString('100'), 8)
                ->rounded(2);
            $total = $total->plus($amount);
            $withheldCount++;
            $decisions[] = $this->decision($tax, FederalWithholdingStatus::WITHHOLD, $basis, $configuration['rate'], $amount->value(), $configuration['code'], $paymentDate, 'PCC_COMPONENT_WITHHOLD');
        }

        $thresholdApplies = $facts->value('payer.siafi_electronic_collection') !== true;
        if ($thresholdApplies && $total->compareTo(DecimalValue::fromString('10.00')) <= 0) {
            $decisions = array_map(
                fn (FederalWithholdingDecision $decision): FederalWithholdingDecision => $decision->status === FederalWithholdingStatus::WITHHOLD
                    ? $this->decision($decision->tax, FederalWithholdingStatus::DISPENSED_THRESHOLD, $basis, $decision->rate, '0.00', null, $paymentDate, 'PCC_TOTAL_NOT_ABOVE_TEN')
                    : $decision,
                $decisions,
            );
            $total = DecimalValue::fromString('0.00');
            $withheldCount = 0;
        }

        $collectionCode = $withheldCount === 3 ? '5952' : null;
        if ($withheldCount === 1) {
            foreach ($decisions as $decision) {
                if ($decision->status === FederalWithholdingStatus::WITHHOLD) {
                    $collectionCode = $decision->revenueCode;
                }
            }
        }

        return new PccWithholdingResult(
            $withheldCount > 0 ? ($withheldCount === count($activeComponents) ? 'WITHHOLD' : 'PARTIAL') : 'NOT_WITHHELD',
            $decisions,
            $gross->rounded(2)->value(),
            $total->rounded(2)->value(),
            $gross->minus($total)->rounded(2)->value(),
            $collectionCode,
            substr($paymentDate, 0, 7),
            'LAST_BUSINESS_DAY_SECOND_TEN_DAY_PERIOD_NEXT_MONTH',
            [$providerCooperative ? 'PCC_COOPERATIVE_CSLL_EXCLUDED' : ($paymentDate >= '2027-01-01' ? 'PCC_2027_CSLL_ONLY' : 'PCC_2026_COMBINED_RULE_APPLIED')],
        );
    }

    /** @param list<string> $missingFacts */
    private function uniformResult(FederalWithholdingStatus $status, string $reasonCode, array $missingFacts = []): PccWithholdingResult
    {
        $decisions = array_map(
            fn (FederalTax $tax): FederalWithholdingDecision => new FederalWithholdingDecision(
                $tax,
                $status,
                null,
                null,
                null,
                null,
                'PAYER',
                'PAYMENT',
                null,
                [$reasonCode],
                ['Lei 10.833/2003, arts. 30 e 31'],
                $missingFacts,
            ),
            [FederalTax::PIS, FederalTax::COFINS, FederalTax::CSLL],
        );

        return new PccWithholdingResult(
            $status === FederalWithholdingStatus::ACTION_REQUIRED ? 'ACTION_REQUIRED' : $status->value,
            $decisions,
            null,
            null,
            null,
            null,
            null,
            'LAST_BUSINESS_DAY_SECOND_TEN_DAY_PERIOD_NEXT_MONTH',
            [$reasonCode],
            $missingFacts,
        );
    }

    /** @param list<string> $additionalLegalBasis */
    private function decision(
        FederalTax $tax,
        FederalWithholdingStatus $status,
        DecimalValue $gross,
        ?string $rate,
        ?string $amount,
        ?string $revenueCode,
        string $paymentDate,
        string $reasonCode,
        array $additionalLegalBasis = [],
    ): FederalWithholdingDecision {
        return new FederalWithholdingDecision(
            $tax,
            $status,
            $gross->rounded(2)->value(),
            $rate,
            $amount,
            $revenueCode,
            'PAYER',
            'PAYMENT',
            $paymentDate,
            [$reasonCode],
            ['Lei 10.833/2003, arts. 30 e 31', 'IN SRF 459/2004', ...$additionalLegalBasis],
        );
    }
}
