<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class InssWithholdingEngine
{
    private const ARTICLE_111_CATEGORIES = [
        'CLEANING_CONSERVATION_JANITORIAL',
        'SECURITY_SURVEILLANCE',
        'CIVIL_CONSTRUCTION',
        'RURAL_SERVICES',
        'DATA_ENTRY',
        'DATA_PREPARATION',
        'ELECTRONIC_SECURITY_MONITORING',
    ];

    private const ARTICLE_112_CATEGORIES = [
        'FINISHING', 'PACKAGING', 'CONDITIONING', 'COLLECTION', 'WASTE_COLLECTION_RECYCLING',
        'PANTRY', 'HOTEL_SERVICES', 'PUBLIC_UTILITY_CUTOFF_CONNECTION', 'DISTRIBUTION',
        'TRAINING_EDUCATION', 'DOCUMENT_DELIVERY', 'METER_CONNECTION', 'METER_READING',
        'MAINTENANCE', 'ASSEMBLY', 'MACHINE_EQUIPMENT_VEHICLE_OPERATION',
        'TOLL_TRANSPORT_TERMINAL_OPERATION', 'PASSENGER_TRANSPORT_OPERATION',
        'DOORMAN_RECEPTION_ELEVATOR_OPERATOR', 'MATERIAL_RECEIPT_SORTING_MOVEMENT',
        'SALES_EVENT_PROMOTION', 'SECRETARIAL_OFFICE', 'HEALTH', 'TELEPHONY_TELEMARKETING',
    ];

    public function __construct(
        private LaborAssignmentClassifier $laborClassifier = new LaborAssignmentClassifier,
        private InssTaxBaseEngine $taxBaseEngine = new InssTaxBaseEngine,
        private ConstructionPrevidenciaryProfile $construction = new ConstructionPrevidenciaryProfile,
    ) {}

    public function evaluate(FactBag $facts): FederalWithholdingDecision
    {
        if (! $facts->has('invoice.issue_date')) {
            return $this->result(FederalWithholdingStatus::PENDING_TRIGGER, null, null, null, null, ['INSS_INVOICE_NOT_ISSUED']);
        }
        $invoiceDate = (string) $facts->value('invoice.issue_date');
        $required = [
            'payer.person_type',
            'provider.person_type',
            'inss.service_group',
            'inss.service_category',
            'inss.execution_mode',
        ];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_FACTS_MISSING'], $missing);
        }

        $payerType = (string) $facts->value('payer.person_type');
        $providerType = (string) $facts->value('provider.person_type');
        if (in_array($payerType, ['INDIVIDUAL', 'FOREIGN'], true)) {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate, ['INSS_PAYER_OUTSIDE_DOMESTIC_ENTERPRISE_SCOPE']);
        }
        if (in_array($providerType, ['INDIVIDUAL', 'FOREIGN_LEGAL_ENTITY'], true)) {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate, ['INSS_PROVIDER_OUTSIDE_DOMESTIC_LEGAL_ENTITY_SCOPE']);
        }
        if ($providerType === 'COOPERATIVE') {
            if (! $facts->hasDefinition('cooperative.provider_type')) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_COOPERATIVE_SPECIAL_PROFILE_REQUIRED']);
            }
            $missing = $facts->missing(['cooperative.provider_type']);
            if ($missing !== []) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_COOPERATIVE_TYPE_REQUIRED'], $missing);
            }
            if ($facts->value('cooperative.provider_type') === 'WORK') {
                return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate,
                    ['INSS_COOP_15_INVOICE_UNCONSTITUTIONAL', 'INSS_COOP_GENERIC_11_NOT_AUTOMATIC'],
                    details: ['cooperative_member_contribution' => 'SEPARATE_20_PERCENT_PROFILE', 'legal_basis' => 'STF Tema 166 / RE 595.838']);
            }

            if (in_array($facts->value('cooperative.provider_type'), ['MEDICAL', 'TRANSPORT'], true)) {
                return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate,
                    ['INSS_COOPERATIVE_INVOICE_15_NOT_WITHHELD', 'INSS_COOPERATIVE_MEMBER_CONTRIBUTIONS_SEPARATE'],
                    details: ['cooperative_member_contribution' => 'SEPARATE_INDIVIDUAL_PROFILE',
                        'legal_basis' => 'STF Tema 166 / RE 595.838']);
            }

            if ($facts->hasDefinition('cooperative.act_type')
                && $facts->value('inss.service_group') === 'OUTSIDE_EXHAUSTIVE_LIST'
                && in_array($facts->value('cooperative.provider_type'), ['PRODUCTION', 'CREDIT', 'OTHER'], true)) {
                return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate,
                    ['INSS_COOPERATIVE_OPERATION_OUTSIDE_SERVICE_WITHHOLDING_LIST']);
            }

            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_COOPERATIVE_SPECIAL_PROFILE_REQUIRED']);
        }
        if ($facts->value('provider.social_security_immunity_qualified') === true) {
            return $this->result(FederalWithholdingStatus::EXEMPT, null, null, '0.00', $invoiceDate, ['INSS_QUALIFIED_BENEFICENT_ENTITY_IMMUNITY']);
        }

        $group = (string) $facts->value('inss.service_group');
        if ($group === 'OUTSIDE_EXHAUSTIVE_LIST') {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate, ['INSS_SERVICE_OUTSIDE_EXHAUSTIVE_LIST']);
        }
        $category = (string) $facts->value('inss.service_category');
        if (($group === 'ARTICLE_111' && ! in_array($category, self::ARTICLE_111_CATEGORIES, true))
            || ($group === 'ARTICLE_112' && ! in_array($category, self::ARTICLE_112_CATEGORIES, true))) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_SERVICE_GROUP_CATEGORY_CONFLICT']);
        }
        if ($category === 'ELECTRONIC_SECURITY_MONITORING') {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate, ['INSS_ELECTRONIC_MONITORING_EXCLUDED']);
        }
        $construction = null;
        if ($facts->hasDefinition('construction.previdenciary_object_type')) {
            $construction = $this->construction->classify($facts);
            if ($construction['status'] === 'ACTION_REQUIRED') {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate,
                    [$construction['reason']], $construction['missing'], ['construction' => $construction]);
            }
        }
        if ($facts->value('inss.execution_mode') === 'TOTAL_CONSTRUCTION') {
            if (! $facts->has('inss.total_construction_optional_withholding')) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_TOTAL_CONSTRUCTION_OPTION_MISSING'], ['inss.total_construction_optional_withholding']);
            }
            if ($facts->value('inss.total_construction_optional_withholding') !== true) {
                return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate, ['INSS_TOTAL_CONSTRUCTION_SOLIDARITY_PROFILE']);
            }
        }

        $labor = $this->laborClassifier->classify($facts);
        if ($labor->missingFacts !== [] || $labor->classification === 'ACTION_REQUIRED') {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, $labor->reasonCodes, $labor->missingFacts);
        }
        if ($group === 'ARTICLE_112' && ! $labor->laborAssignment) {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate, ['INSS_ARTICLE_112_REQUIRES_LABOR_ASSIGNMENT']);
        }
        if ($group === 'ARTICLE_111'
            && ! in_array($facts->value('inss.execution_mode'), ['LABOR_ASSIGNMENT', 'CONTRACT_FOR_RESULT', 'TOTAL_CONSTRUCTION', 'PARTIAL_CONSTRUCTION', 'SUBCONTRACT'], true)) {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate, ['INSS_ARTICLE_111_EXECUTION_MODE_NOT_COVERED']);
        }

        if (! $facts->has('provider.tax_regime')) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_PROVIDER_TAX_REGIME_MISSING'], ['provider.tax_regime']);
        }

        if ($facts->value('provider.tax_regime') === 'SIMPLES_NACIONAL') {
            if (! $facts->has('provider.simples_annex')) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_SIMPLES_ANNEX_MISSING'], ['provider.simples_annex']);
            }
            if ($facts->value('provider.simples_annex') !== 'ANNEX_IV') {
                return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate, ['INSS_SIMPLES_NON_ANNEX_IV_NOT_WITHHELD']);
            }
        }
        if ($facts->value('provider.tax_regime') === 'MEI') {
            return $this->result(FederalWithholdingStatus::NOT_APPLICABLE, null, null, '0.00', $invoiceDate, ['INSS_MEI_NOT_WITHHELD']);
        }

        if (! $facts->has('inss.statutory_dispense_claimed')) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_STATUTORY_DISPENSE_CLAIM_STATUS_MISSING'], ['inss.statutory_dispense_claimed']);
        }
        $dispense = $this->statutoryDispense($facts, $invoiceDate);
        if ($dispense !== null) {
            return $dispense;
        }

        $baseDecision = $this->taxBaseEngine->calculate($facts);
        if ($baseDecision->status !== 'RESOLVED' || $baseDecision->taxableBase === null) {
            return $this->result(
                FederalWithholdingStatus::ACTION_REQUIRED,
                $baseDecision->grossAmount,
                null,
                null,
                $invoiceDate,
                $baseDecision->reasonCodes,
                $baseDecision->missingFacts,
                ['tax_base' => $baseDecision->toArray()],
            );
        }
        $basis = DecimalValue::fromString($baseDecision->taxableBase);

        $inCprbTransition = $invoiceDate >= '2025-01-01' && $invoiceDate <= '2027-12-31';
        if ($inCprbTransition && ! $facts->has('provider.cprb_applicable')) {
            return $this->result(
                FederalWithholdingStatus::ACTION_REQUIRED,
                $basis->value(),
                null,
                null,
                $invoiceDate,
                ['INSS_CPRB_STATUS_MISSING'],
                ['provider.cprb_applicable'],
                ['tax_base' => $baseDecision->toArray()],
            );
        }
        $baseRate = $inCprbTransition && $facts->value('provider.cprb_applicable') === true
            ? DecimalValue::fromString('3.50')
            : DecimalValue::fromString('11.00');
        $additional = $facts->has('inss.special_retirement_additional_rate')
            ? DecimalValue::fromString((string) $facts->value('inss.special_retirement_additional_rate'))
            : DecimalValue::fromString('0');
        if (! in_array($additional->value(), ['0', '0.00', '2', '2.00', '3', '3.00', '4', '4.00'], true)) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, $basis->value(), null, null, $invoiceDate, ['INSS_SPECIAL_RETIREMENT_RATE_INVALID'], details: ['tax_base' => $baseDecision->toArray()]);
        }
        $rate = $baseRate->plus($additional);
        $grossRetention = $basis->multipliedBy($rate)->dividedBy(DecimalValue::fromString('100'), 8)->rounded(2);
        $offset = $facts->has('inss.subcontractor_withholding_offset')
            ? DecimalValue::fromString((string) $facts->value('inss.subcontractor_withholding_offset'))
            : DecimalValue::fromString('0');
        if ($offset->isNegative() || $offset->compareTo($grossRetention) > 0) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, $basis->value(), $rate->value(), null, $invoiceDate, ['INSS_SUBCONTRACTOR_OFFSET_INVALID'], details: ['tax_base' => $baseDecision->toArray()]);
        }
        if ($offset->compareTo(DecimalValue::fromString('0')) > 0
            && $facts->value('inss.subcontractor_offset_evidence_confirmed') !== true) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, $basis->value(), $rate->value(), null, $invoiceDate, ['INSS_SUBCONTRACTOR_OFFSET_EVIDENCE_REQUIRED'], ['inss.subcontractor_offset_evidence_confirmed'], ['tax_base' => $baseDecision->toArray()]);
        }
        $amount = $grossRetention->minus($offset)->rounded(2);

        return new FederalWithholdingDecision(
            FederalTax::INSS,
            FederalWithholdingStatus::WITHHOLD,
            $basis->value(),
            $rate->value(),
            $amount->value(),
            null,
            'PAYER',
            'INVOICE_ISSUANCE',
            $invoiceDate,
            [$baseRate->value() === '3.5' || $baseRate->value() === '3.50' ? 'INSS_CPRB_RETENTION_35' : 'INSS_RETENTION_11'],
            ['Lei 8.212/1991, art. 31', 'IN RFB 2.110/2022, arts. 108 a 145'],
            [],
            [
                'tax_base' => $baseDecision->toArray(),
                'gross_withholding_before_subcontract_offset' => $grossRetention->value(),
                'subcontractor_offset' => $offset->rounded(2)->value(),
                'tax_period' => substr($invoiceDate, 0, 7),
                'due_rule' => str_contains($payerType, 'PUBLIC_ADMINISTRATION') && $facts->value('payer.siafi_electronic_collection') !== true
                    ? 'DAY_20_MONTH_AFTER_EXPENDITURE_LIQUIDATION_PREVIOUS_BUSINESS_DAY_IF_NEEDED'
                    : 'DAY_20_MONTH_AFTER_INVOICE_PREVIOUS_BUSINESS_DAY_IF_NEEDED',
                'reporting' => [
                    'payer_event' => 'R-2010',
                    'provider_event' => 'R-2020',
                    'period_basis' => 'INVOICE_ISSUE_DATE',
                    'dctfweb' => true,
                ],
                'total_construction_optional_withholding' => $facts->value('inss.execution_mode') === 'TOTAL_CONSTRUCTION',
                'construction' => $construction,
            ],
        );
    }

    private function statutoryDispense(FactBag $facts, string $invoiceDate): ?FederalWithholdingDecision
    {
        if ($facts->value('inss.statutory_dispense_claimed') !== true) {
            return null;
        }
        $missing = $facts->missing(['inss.statutory_dispense_type', 'inss.dispense_declaration_received']);
        if ($missing !== []) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_STATUTORY_DISPENSE_FACTS_MISSING'], $missing);
        }
        if ($facts->value('inss.dispense_declaration_received') !== true) {
            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_STATUTORY_DISPENSE_DECLARATION_REQUIRED'], ['inss.dispense_declaration_received']);
        }

        $type = (string) $facts->value('inss.statutory_dispense_type');
        if ($type === 'NO_EMPLOYEES_OWNER_PERSONAL_SERVICE') {
            $missing = $facts->missing([
                'provider.has_employees',
                'inss.service_performed_personally_by_owner_or_partner',
                'provider.previous_month_gross_revenue',
                'inss.max_social_security_contribution_salary',
            ]);
            if ($missing !== []) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_OWNER_PERSONAL_SERVICE_DISPENSE_FACTS_MISSING'], $missing);
            }
            $revenue = DecimalValue::fromString((string) $facts->value('provider.previous_month_gross_revenue'));
            $salaryCeiling = DecimalValue::fromString((string) $facts->value('inss.max_social_security_contribution_salary'));
            if ($revenue->isNegative() || $salaryCeiling->isNegative()) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_OWNER_PERSONAL_SERVICE_DISPENSE_VALUES_INVALID']);
            }
            $limit = $salaryCeiling
                ->multipliedBy(DecimalValue::fromString('2'));
            if ($facts->value('provider.has_employees') === false
                && $facts->value('inss.service_performed_personally_by_owner_or_partner') === true
                && $revenue->compareTo($limit) <= 0) {
                return $this->result(
                    FederalWithholdingStatus::EXEMPT,
                    null,
                    null,
                    '0.00',
                    $invoiceDate,
                    ['INSS_OWNER_PERSONAL_SERVICE_STATUTORY_DISPENSE'],
                    details: ['previous_month_revenue' => $revenue->value(), 'revenue_limit' => $limit->value()],
                );
            }

            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_OWNER_PERSONAL_SERVICE_DISPENSE_CONDITIONS_NOT_MET']);
        }

        if ($type === 'REGULATED_PROFESSION_OR_TRAINING') {
            $missing = $facts->missing([
                'inss.regulated_profession_or_training_service',
                'inss.service_performed_personally_by_owner_or_partner',
                'inss.employees_in_execution',
                'inss.execution_has_other_individual_contributors',
            ]);
            if ($missing !== []) {
                return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_REGULATED_SERVICE_DISPENSE_FACTS_MISSING'], $missing);
            }
            if ($facts->value('inss.regulated_profession_or_training_service') === true
                && $facts->value('inss.service_performed_personally_by_owner_or_partner') === true
                && $facts->value('inss.employees_in_execution') === false
                && $facts->value('inss.execution_has_other_individual_contributors') === false) {
                return $this->result(FederalWithholdingStatus::EXEMPT, null, null, '0.00', $invoiceDate, ['INSS_REGULATED_SERVICE_STATUTORY_DISPENSE']);
            }

            return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_REGULATED_SERVICE_DISPENSE_CONDITIONS_NOT_MET']);
        }

        return $this->result(FederalWithholdingStatus::ACTION_REQUIRED, null, null, null, $invoiceDate, ['INSS_STATUTORY_DISPENSE_TYPE_INVALID']);
    }

    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $missingFacts
     * @param  array<string, mixed>  $details
     */
    private function result(
        FederalWithholdingStatus $status,
        ?string $basis,
        ?string $rate,
        ?string $amount,
        ?string $triggerDate,
        array $reasonCodes,
        array $missingFacts = [],
        array $details = [],
    ): FederalWithholdingDecision {
        return new FederalWithholdingDecision(
            FederalTax::INSS,
            $status,
            $basis,
            $rate,
            $amount,
            null,
            'PAYER',
            'INVOICE_ISSUANCE',
            $triggerDate,
            $reasonCodes,
            ['Lei 8.212/1991, art. 31', 'IN RFB 2.110/2022'],
            $missingFacts,
            $details,
        );
    }
}
