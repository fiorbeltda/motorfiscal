<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

final readonly class ConsumptionReformEngine
{
    /** @param array<string, mixed> $codes */
    public function __construct(private TemporalRateCatalog $rates, private array $codes, private TransitionCatalog $transitions) {}

    /** @return array<string, mixed> */
    public function evaluate(FactBag $facts, LocalDate $competence, DateTimeImmutable $knownAt): array
    {
        $trace = [['stage' => 'TEMPORAL', 'competence_date' => $competence->value(), 'known_at' => $knownAt->format(DATE_ATOM)]];
        if (DocumentIssIntegration134::required($this->codes)) {
            $issPolicy = (new DocumentIssIntegration134)->qualifyPolicy($this->codes, $knownAt);
            $trace[] = ['stage' => 'DOCUMENT_ISS_POLICY134', ...$issPolicy];
            if ($issPolicy['status'] !== 'RESOLVED') {
                return $this->stop($issPolicy['status'], [$issPolicy['reason']], [], $trace);
            }
        }
        if (DocumentSchedule131::required($this->codes)) {
            $schedulePolicy = (new DocumentSchedule131)->qualify($this->codes, $knownAt);
            $trace[] = ['stage' => 'DOCUMENT_SCHEDULE_POLICY131', ...$schedulePolicy];
            if ($schedulePolicy['status'] !== 'RESOLVED') {
                return $this->stop($schedulePolicy['status'], [$schedulePolicy['reason']], [], $trace);
            }
        }
        $importResponsibility129 = ImportResponsibility129::required($this->codes);
        if ($importResponsibility129) {
            $importPolicy = (new ImportResponsibility129)->qualify($this->codes, $knownAt);
            $trace[] = ['stage' => 'IMPORT_POLICY129', ...$importPolicy];
            if ($importPolicy['status'] !== 'RESOLVED') {
                return $this->stop($importPolicy['status'], [$importPolicy['reason']], [], $trace);
            }
        }
        if (DpsForeignPolicy128::required($this->codes)) {
            $foreignPolicy = (new DpsForeignPolicy128)->qualify($this->codes, $knownAt);
            $trace[] = ['stage' => 'DPS_FOREIGN_POLICY128', ...$foreignPolicy];
            if ($foreignPolicy['status'] !== 'RESOLVED') {
                return $this->stop($foreignPolicy['status'], [$foreignPolicy['reason']], [], $trace);
            }
        }
        if (DpsLayoutPolicy127::required($this->codes)) {
            $schemaPolicy = (new DpsLayoutPolicy127)->qualify($this->codes, $knownAt);
            $trace[] = ['stage' => 'DPS_LAYOUT_POLICY127', ...$schemaPolicy];
            if ($schemaPolicy['status'] !== 'RESOLVED') {
                return $this->stop($schemaPolicy['status'], [$schemaPolicy['reason']], [], $trace);
            }
        }
        if (CIndOpReference126::required($this->codes)) {
            $referencePolicy = (new CIndOpReference126)->qualify($this->codes, $knownAt);
            $trace[] = ['stage' => 'CINDOP_POLICY126', ...$referencePolicy];
            if ($referencePolicy['status'] !== 'RESOLVED') {
                return $this->stop($referencePolicy['status'], [$referencePolicy['reason']], [], $trace);
            }
        }
        $datedDocuments = $facts->value('rtc.assess_export_dated_documents') === true;
        $documentPrices = $facts->value('rtc.assess_export_document_prices') === true;
        if ($documentPrices && ($datedDocuments || $facts->value('rtc.assess_export_iss_results') === true
            || $facts->value('rtc.assess_export_sp_results') === true || $facts->value('rtc.assess_export_dps_identity') === true)) {
            return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_PRICE112_SEPARATE_LEGACY_CONTEXT_REQUIRED'], [], $trace);
        }
        if ($datedDocuments && ($facts->value('rtc.assess_export_iss_results') === true
            || $facts->value('rtc.assess_export_sp_results') === true || $facts->value('rtc.assess_export_dps_identity') === true)) {
            return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_DATED108_SEPARATE_LEGACY_CONTEXT_REQUIRED'], [], $trace);
        }
        if ($facts->value('rtc.assess_export_dps_identity') === true
            && ($facts->value('rtc.assess_export_iss_results') !== true || $facts->value('rtc.assess_export_sp_results') !== true)) {
            return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_DPS104_PRIOR_ISS_ASSESSMENTS_REQUIRED'],
                array_values(array_filter(['rtc.assess_export_iss_results', 'rtc.assess_export_sp_results'],
                    fn (string $key): bool => $facts->value($key) !== true)), $trace);
        }
        if ($facts->value('rtc.assess_export_sp_results') === true && $facts->value('rtc.assess_export_iss_results') !== true) {
            return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_SP98_NATIONAL_RESULT_ASSESSMENT_REQUIRED'], ['rtc.assess_export_iss_results'], $trace);
        }
        if ($facts->value('rtc.assess_export_iss_results') === true || $datedDocuments || $documentPrices) {
            $share = $facts->value('rtc.export_share_percent');
            if ($facts->value('rtc.market') !== 'EXPORT' || $facts->value('rtc.operation_kind') !== 'DIGITAL'
                || ! is_string($share) || preg_match('/\A[0-9]{1,3}(?:\.[0-9]{1,8})?\z/', $share) !== 1
                || DecimalValue::fromString($share)->compareTo(DecimalValue::fromString('0')) <= 0
                || DecimalValue::fromString($share)->compareTo(DecimalValue::fromString('100')) >= 0) {
                return $this->stop('ACTION_REQUIRED', [$documentPrices ? 'RTC_EXPORT_PRICE112_PARTIAL_EXPORT_ROUTE_REQUIRED'
                    : ($datedDocuments ? 'RTC_EXPORT_DATED108_PARTIAL_EXPORT_ROUTE_REQUIRED' : 'RTC_EXPORT_ISS96_PARTIAL_EXPORT_ROUTE_REQUIRED')], [], $trace);
            }
            if ($facts->value('rtc.assess_tax_point_events') === true || $facts->value('rtc.assess_advance_origin') === true
                || $facts->value('rtc.reconcile_recorded_advances') === true) {
                return $this->stop('ACTION_REQUIRED', [$documentPrices ? 'RTC_EXPORT_PRICE112_SEPARATE_TEMPORAL_CONTEXT_REQUIRED'
                    : ($datedDocuments ? 'RTC_EXPORT_DATED108_SEPARATE_TEMPORAL_CONTEXT_REQUIRED' : 'RTC_EXPORT_ISS96_SEPARATE_TEMPORAL_CONTEXT_REQUIRED')], [], $trace);
            }
        }
        $taxPoint = null;
        $advanceOrigin = null;
        if ($facts->value('rtc.assess_advance_origin') === true) {
            $policy = $this->transitions->taxPointPolicy();
            if ($policy === null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_ADVANCE_ORIGIN_POLICY_NOT_MATERIALIZED'], [], $trace);
            }
            $advanceOrigin = (new AdvanceContext88)->qualify($facts->canonicalValues(), $knownAt, $policy);
            $trace[] = ['stage' => 'ADVANCE_ORIGIN', ...$advanceOrigin];
            if ($advanceOrigin['status'] !== 'RESOLVED') {
                return $this->stop('ACTION_REQUIRED', [$advanceOrigin['reason']], $advanceOrigin['missing_facts'], $trace);
            }
        }
        if ($facts->value('rtc.assess_tax_point_events') === true) {
            $policy = $this->transitions->taxPointPolicy();
            if ($policy === null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_TAX_POINT_POLICY_NOT_MATERIALIZED'], [], $trace);
            }
            $taxPoint = (new TaxPointContext86)->qualify($facts->canonicalValues(), $knownAt, $policy);
            $trace[] = ['stage' => 'TAX_POINT_EVENTS', ...$taxPoint];
            if ($taxPoint['status'] !== 'RESOLVED') {
                return $this->stop('ACTION_REQUIRED', [$taxPoint['reason']], $taxPoint['missing_facts'], $trace);
            }
        }
        $recordedAdvanceRequested = $facts->value('rtc.reconcile_recorded_advances') === true;
        if ($recordedAdvanceRequested && ($taxPoint === null || $taxPoint['requires_advance_reconciliation'] !== true)) {
            return $this->stop('ACTION_REQUIRED', ['RTC_RECORDED_ADVANCE_FINAL_CONTEXT_REQUIRED'], [], $trace);
        }
        $date = (string) ($facts->value('rtc.operation_date') ?? $competence->value());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->stop('INVALID', ['RTC_OPERATION_DATE_INVALID'], [], $trace);
        }
        $on = LocalDate::fromString($date);
        if ($on->value() !== $competence->value()) {
            $trace[] = ['stage' => 'RELEVANT_DATE', 'operation_date' => $date, 'competence_date' => $competence->value()];
        }
        if ($date < '2026-01-01') {
            return $this->stop('INVALID', ['RTC_BEFORE_LEGAL_EFFECT'], [], $trace);
        }
        $transition = $this->transitions->resolve($on, $knownAt);
        if ($transition['status'] !== 'RESOLVED' || ! isset($transition['row'])) {
            return $this->stop($transition['status'] === 'CONFLICT' ? 'INVALID' : 'ACTION_REQUIRED', [$transition['reason']], [], $trace);
        }
        $transitionRow = $transition['row'];
        $trace[] = ['stage' => 'TRANSITION', 'phase' => $transitionRow['phase'], 'source_article' => $transitionRow['source_article'],
            'catalog_version' => $this->transitions->version()];

        $required = ['rtc.operation_date', 'rtc.operation_kind', 'rtc.buyer_relation', 'rtc.delivery_mode', 'rtc.consideration', 'rtc.market', 'rtc.supplier_regime', 'rtc.supplier_id',
            'rtc.acquirer_id', 'rtc.recipient_id', 'rtc.acquirer_country', 'rtc.recipient_country',
            'rtc.destination_basis', 'rtc.document_id', 'rtc.gross_amount', 'rtc.legacy_tax_inclusion',
            'rtc.legacy_tax_evidence_id', 'rtc.base_adjustments',
            'rtc.tax_treatment', 'rtc.collection_method', 'rtc.credit_context'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', ['RTC_MATERIAL_FACTS_MISSING'], $missing, $trace);
        }
        $creditInput122 = CreditInputPolicy122::required($this->codes);
        $materialInput119 = MaterialInputPolicy119::required($this->codes) || $creditInput122;
        if ($materialInput119) {
            $inputPolicy = (new MaterialInputPolicy119)->qualify($this->codes, $knownAt);
            $trace[] = ['stage' => 'MATERIAL_INPUT_POLICY119', ...$inputPolicy];
            if ($inputPolicy['status'] !== 'RESOLVED') {
                return $this->stop('ACTION_REQUIRED', [$inputPolicy['reason']], [], $trace);
            }
            $adjustmentInput = (new OrdinaryAdjustmentInput119)->qualify($facts->value('rtc.base_adjustments'));
            $trace[] = ['stage' => 'ADJUSTMENT_INPUT119', ...$adjustmentInput];
            if ($adjustmentInput['status'] !== 'RESOLVED') {
                return $this->stop($adjustmentInput['status'], [$adjustmentInput['reason']], [], $trace);
            }
        }
        $kind = (string) $facts->value('rtc.operation_kind');
        $consideration = (string) $facts->value('rtc.consideration');
        $market = (string) $facts->value('rtc.market');
        $regime = (string) $facts->value('rtc.supplier_regime');
        $supplierCreditPeriod = null;
        if ($facts->value('rtc.assess_supplier_credit_period') === true) {
            $supplierCreditPeriod = (new SupplierCreditPeriodEngine)->resolve($facts, $on, $knownAt,
                $this->codes['supplier_credit_period'] ?? []);
            $trace[] = ['stage' => 'SUPPLIER_CREDIT_PERIOD', ...$supplierCreditPeriod];
            if ($supplierCreditPeriod['status'] !== 'RESOLVED') {
                return $this->stop($supplierCreditPeriod['status'], [$supplierCreditPeriod['reason']], $supplierCreditPeriod['missing_facts'], $trace);
            }
        }
        $monthlyAssessment = null;
        if ($facts->value('rtc.assess_monthly_period') === true) {
            $monthlyAssessment = (new MonthlyAssessmentEngine)->resolve($facts, $on, $knownAt,
                $this->codes['monthly_assessment'] ?? []);
            $trace[] = ['stage' => 'MONTHLY_ASSESSMENT', ...$monthlyAssessment];
            if ($monthlyAssessment['status'] !== 'RESOLVED') {
                return $this->stop($monthlyAssessment['status'], [$monthlyAssessment['reason']], $monthlyAssessment['missing_facts'], $trace);
            }
        }
        $inputCreditTreatment = null;
        if ($creditInput122) {
            $creditPolicy = (new CreditInputPolicy122)->qualify($this->codes, $knownAt);
            $trace[] = ['stage' => 'CREDIT_INPUT_POLICY122', ...$creditPolicy];
            if ($creditPolicy['status'] !== 'RESOLVED') {
                return $this->stop($creditPolicy['status'], [$creditPolicy['reason']], [], $trace);
            }
            if ($facts->value('rtc.credit_context') === 'INPUT' && $facts->value('rtc.acquirer_regime') === 'REGULAR'
                && ! ($kind === 'FINANCIAL' && isset($this->codes['financial_operation']['credit']))) {
                $creditInput = (new CreditInput122)->qualify($facts->canonicalValues(), $market);
                $trace[] = ['stage' => 'CREDIT_INPUT122', ...$creditInput];
                if ($creditInput['status'] !== 'RESOLVED') {
                    return $this->stop($creditInput['status'], [$creditInput['reason']], $creditInput['missing_facts'], $trace);
                }
            }
        }
        if ($facts->value('rtc.credit_context') === 'INPUT' && isset($this->codes['input_credit_treatment'])
            && $facts->value('rtc.acquirer_regime') === 'REGULAR'
            && ! ($kind === 'FINANCIAL' && isset($this->codes['financial_operation']['credit']))) {
            $inputCreditTreatment = (new InputCreditTreatmentQualifier)->resolve($facts, $on, $knownAt, $this->codes['input_credit_treatment']);
            $trace[] = ['stage' => 'INPUT_CREDIT_TREATMENT', ...$inputCreditTreatment];
            if ($inputCreditTreatment['status'] !== 'RESOLVED') {
                return $this->stop($inputCreditTreatment['status'], [$inputCreditTreatment['reason']], $inputCreditTreatment['missing_facts'], $trace);
            }
        }
        $providerRegime = $facts->value('provider.tax_regime');
        if (($market === 'IMPORT' && $regime !== 'FOREIGN') || ($market !== 'IMPORT' && $regime === 'FOREIGN')) {
            return $this->stop('INVALID', ['RTC_SUPPLIER_REGIME_MARKET_CONFLICT'], [], $trace);
        }
        if (($regime === 'MEI' && $providerRegime !== null && $providerRegime !== 'MEI')
            || ($regime === 'SIMPLES_NACIONAL' && $providerRegime !== null && $providerRegime !== 'SIMPLES_NACIONAL')
            || ($regime === 'REGULAR' && in_array($providerRegime, ['MEI', 'SIMPLES_NACIONAL'], true))) {
            return $this->stop('INVALID', ['RTC_SUPPLIER_REGIME_CONFLICT'], [], $trace);
        }
        if (($facts->value('rtc.buyer_relation') === 'B2C' && $facts->value('customer.person_type') === 'PJ')
            || ($facts->value('rtc.buyer_relation') === 'B2B' && $facts->value('customer.person_type') === 'PF')) {
            return $this->stop('INVALID', ['RTC_BUYER_RELATION_CONFLICT'], [], $trace);
        }
        if ($this->transitions->qualifiesDonations()
            && $facts->value('rtc.non_onerous_legal_basis') === 'LC214_ART6_VIII') {
            if ($consideration !== 'NON_ONEROUS') {
                return $this->stop('INVALID', ['RTC_DONATION_CONSIDERATION_CONFLICT'], [], $trace);
            }
            if ($market !== 'DOMESTIC' || $regime !== 'REGULAR') {
                return $this->stop('ACTION_REQUIRED', ['RTC_DONATION_OTHER_REGIME_NOT_MATERIALIZED'], [], $trace);
            }
            $donation = (new DonationQualifier)->resolve($facts);
            if ($donation['status'] !== 'RESOLVED') {
                return $this->stop($donation['status'], [$donation['reason']], $donation['missing_facts'], $trace);
            }
            if (! isset($donation['treatment'], $donation['source_article'])) {
                return $this->stop('INVALID', ['RTC_DONATION_QUALIFICATION_INCOMPLETE'], [], $trace);
            }
            $trace[] = ['stage' => 'DONATION_INCIDENCE', 'source_article' => $donation['source_article'],
                'source_hash' => $this->transitions->sourceHash(), 'treatment' => $donation['treatment'],
                'counterbenefit_evidence_id' => $facts->value('rtc.donation_counterbenefit_evidence_id'),
                'credit_eligibility_evidence_id' => $facts->value('rtc.donation_input_credit_evidence_id'),
                'credit_annulment_evidence_id' => $facts->value('rtc.donation_credit_annulment_evidence_id')];
            if ($donation['treatment'] === 'MARKET_VALUE_TAXATION' && $this->transitions->calculatesDonationNominal()) {
                return $this->donationNominal($facts, $on, $knownAt, $trace);
            }

            return $this->stop('ACTION_REQUIRED', [
                $donation['treatment'] === 'MARKET_VALUE_TAXATION'
                    ? 'RTC_DONATION_MARKET_CALCULATION_AND_DOCUMENT_NOT_MATERIALIZED'
                    : 'RTC_DONATION_DOCUMENT_OBLIGATION_NOT_MATERIALIZED',
            ], [], $trace);
        }
        $platformDecision = null;
        if ($this->transitions->qualifiesPlatformResponsibility()) {
            $platformDecision = (new PlatformResponsibilityEngine)->resolve($facts);
            if ($platformDecision['status'] !== 'RESOLVED') {
                return $this->stop($platformDecision['status'], [$platformDecision['reason']],
                    $platformDecision['missing_facts'], $trace);
            }
            if ($facts->value('rtc.platform_id') !== null) {
                $platformDecision['source_hash'] = $this->transitions->sourceHash();
                $trace[] = ['stage' => 'PLATFORM_RESPONSIBILITY', 'source_article' => $platformDecision['source_article'],
                    'source_hash' => $platformDecision['source_hash'],
                    'responsibility' => $platformDecision['responsibility'],
                    'triggers' => $platformDecision['triggers'] ?? [],
                    'qualification_evidence_id' => $facts->value('rtc.platform_qualification_evidence_id')];
            }
        }
        $personalBelowMarketCandidate = $this->transitions->qualifiesEmployeeBelowMarket()
            && $consideration === 'ONEROUS'
            && ($facts->value('rtc.non_onerous_legal_basis') === 'LC214_ART5_I_B_1'
                || ($this->transitions->qualifiesPersonalUseRecipients()
                    && $facts->value('rtc.non_onerous_legal_basis') === 'LC214_ART5_I_B_2')
                || ($this->transitions->qualifiesAcquiredPersonalUse()
                    && in_array($facts->value('rtc.non_onerous_legal_basis'),
                        ['LC214_ART5_I_A_1', 'LC214_ART5_I_A_2', 'LC214_ART5_I_A_3', 'LC214_ART5_I_A_4'], true)));
        if ($consideration === 'NON_ONEROUS' && $facts->value('rtc.non_onerous_legal_basis') === null) {
            return $this->stop('ACTION_REQUIRED', ['RTC_NON_ONEROUS_HYPOTHESIS_UNPROVEN'], ['rtc.non_onerous_legal_basis'], $trace);
        }
        $personalUseRecipient = false;
        if ($consideration === 'NON_ONEROUS' || $personalBelowMarketCandidate) {
            if ($market !== 'DOMESTIC' || $regime !== 'REGULAR') {
                return $this->stop('ACTION_REQUIRED', ['RTC_NON_ONEROUS_STRATEGY_NOT_MATERIALIZED'], [], $trace);
            }
            if ($this->transitions->qualifiesPersonalUseRecipients()) {
                $recipient = (new PersonalUseRecipientQualifier)->resolve($facts, $this->transitions->qualifiesAcquiredPersonalUse());
                if ($recipient['status'] !== 'VALID') {
                    return $this->stop($recipient['status'], [$recipient['reason']], $recipient['missing_facts'], $trace);
                }
                $acquiredPersonalUse = $this->transitions->qualifiesAcquiredPersonalUse()
                    && in_array($facts->value('rtc.non_onerous_legal_basis'),
                        ['LC214_ART5_I_A_1', 'LC214_ART5_I_A_2', 'LC214_ART5_I_A_3', 'LC214_ART5_I_A_4'], true);
                if ($this->transitions->requiresPersonalUseSupplyOrigin()) {
                    $originMissing = $facts->missing(['rtc.personal_use_supply_origin', 'rtc.personal_use_origin_evidence_id']);
                    if ($originMissing !== []) {
                        return $this->stop('ACTION_REQUIRED', ['RTC_PERSONAL_USE_SUPPLY_ORIGIN_UNPROVEN'], $originMissing, $trace);
                    }
                    $expectedOrigin = $acquiredPersonalUse ? 'ACQUIRED_FROM_THIRD_PARTY' : 'SELF_PRODUCED_OR_PROVIDED';
                    if ($facts->value('rtc.personal_use_supply_origin') !== $expectedOrigin) {
                        return $this->stop('INVALID', ['RTC_PERSONAL_USE_LEGAL_BASIS_ORIGIN_CONFLICT'], [], $trace);
                    }
                    $trace[] = ['stage' => 'PERSONAL_USE_SUPPLY_ORIGIN',
                        'source_article' => $acquiredPersonalUse ? 'LC214 art. 5º, I, a' : 'LC214 art. 5º, I, b',
                        'origin' => $facts->value('rtc.personal_use_supply_origin'),
                        'evidence_id' => $facts->value('rtc.personal_use_origin_evidence_id'),
                        'source_hash' => $this->transitions->sourceHash()];
                }
                if ($acquiredPersonalUse) {
                    $creditMissing = $facts->missing(['rtc.personal_use_input_credit_eligible',
                        'rtc.personal_use_input_credit_evidence_id']);
                    if ($creditMissing !== []) {
                        return $this->stop('ACTION_REQUIRED', ['RTC_PERSONAL_USE_INPUT_CREDIT_UNPROVEN'], $creditMissing, $trace);
                    }
                    if ($facts->value('rtc.personal_use_input_credit_eligible') !== true) {
                        return $this->stop('INVALID', ['RTC_PERSONAL_USE_INPUT_CREDIT_BASIS_CONFLICT'], [], $trace);
                    }
                    $trace[] = ['stage' => 'PERSONAL_USE_INPUT_CREDIT', 'source_article' => 'LC214 art. 5º, I, a',
                        'eligibility_evidence_id' => $facts->value('rtc.personal_use_input_credit_evidence_id'),
                        'source_hash' => $this->transitions->sourceHash()];
                }
                $personalUseRecipient = true;
                $trace[] = ['stage' => 'NON_ONEROUS_INCIDENCE', 'source_article' => $recipient['source_article'] ?? null,
                    'recipient_relationship' => $recipient['relationship'] ?? null, 'anchor_role' => $recipient['anchor_role'] ?? null,
                    'relationship_evidence_id' => $facts->value('rtc.recipient_relationship_evidence_id'),
                    'anchor_evidence_id' => $facts->value('rtc.recipient_anchor_evidence_id'),
                    'business_use_evidence_id' => $facts->value('rtc.business_use_evidence_id')];
            } else {
                if ($facts->value('rtc.non_onerous_legal_basis') !== 'LC214_ART5_I_B_1'
                    || $facts->value('rtc.recipient_relationship') !== 'EMPLOYEE') {
                    return $this->stop('ACTION_REQUIRED', ['RTC_NON_ONEROUS_STRATEGY_NOT_MATERIALIZED'], [], $trace);
                }
                $nonOnerousMissing = $facts->missing(['rtc.recipient_relationship_evidence_id',
                    'rtc.business_use_predominant', 'rtc.business_use_evidence_id',
                    'rtc.market_value_brl', 'rtc.market_value_evidence_id']);
                if ($nonOnerousMissing !== []) {
                    return $this->stop('ACTION_REQUIRED', ['RTC_NON_ONEROUS_EVIDENCE_MISSING'], $nonOnerousMissing, $trace);
                }
                if ($facts->value('rtc.business_use_predominant') === true) {
                    return $this->stop('ACTION_REQUIRED', ['RTC_NON_ONEROUS_BUSINESS_USE_EXCEPTION'], [], $trace);
                }
                $personalUseRecipient = true;
                $trace[] = ['stage' => 'NON_ONEROUS_INCIDENCE', 'source_article' => 'LC214 art. 5º, I, b, 1, §§8º-9º',
                    'recipient_relationship' => 'EMPLOYEE', 'relationship_evidence_id' => $facts->value('rtc.recipient_relationship_evidence_id'),
                    'business_use_evidence_id' => $facts->value('rtc.business_use_evidence_id')];
            }
        }
        if ($market === 'EXPORT' && ($facts->value('rtc.foreign_consumption_proven') !== true || $facts->value('rtc.export_evidence_id') === null)) {
            return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_CONSUMPTION_UNPROVEN'], ['rtc.foreign_consumption_proven', 'rtc.export_evidence_id'], $trace);
        }
        if ($market === 'EXPORT') {
            if ($kind !== 'DIGITAL' || $facts->value('rtc.delivery_mode') !== 'DIGITAL'
                || $facts->value('service.canonical_id') !== 'SOFTWARE_DEVELOPMENT') {
                return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_SERVICE_STRATEGY_NOT_MATERIALIZED'], [], $trace);
            }
            if ($facts->value('rtc.supplier_country') === null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_SUPPLIER_COUNTRY_MISSING'], ['rtc.supplier_country'], $trace);
            }
            if ($facts->value('rtc.supplier_country') !== 'BR' || $facts->value('rtc.acquirer_country') === 'BR'
                || $facts->value('rtc.recipient_country') === 'BR') {
                return $this->stop('INVALID', ['RTC_EXPORT_FOREIGN_ROLES_CONFLICT'], [], $trace);
            }
            if (! in_array($facts->value('rtc.destination_basis'), ['ACQUIRER_DOMICILE', 'RECIPIENT_DOMICILE'], true)) {
                return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_SPECIFIC_LOCATION_NOT_MATERIALIZED'], [], $trace);
            }
            if ($facts->value('rtc.export_share_percent') === null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_SHARE_MISSING'], ['rtc.export_share_percent'], $trace);
            }
            $share = DecimalValue::fromString((string) $facts->value('rtc.export_share_percent'));
            if ($share->isNegative() || $share->compareTo(DecimalValue::fromString('100')) > 0) {
                return $this->stop('INVALID', ['RTC_EXPORT_SHARE_INVALID'], [], $trace);
            }
            if ($share->compareTo(DecimalValue::fromString('100')) < 0) {
                if ($share->compareTo(DecimalValue::fromString('0')) === 0) {
                    return $this->stop('INVALID', ['RTC_EXPORT_SHARE_ZERO'], [], $trace);
                }
                $allocationMissing = $facts->missing(['rtc.export_allocation_method', 'rtc.export_allocation_evidence_id',
                    'rtc.domestic_consumption_evidence_id', 'rtc.gross_currency']);
                if ($allocationMissing !== []) {
                    return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_ALLOCATION_UNPROVEN'], $allocationMissing, $trace);
                }
                $exportGross = (string) $facts->value('rtc.gross_amount');
                if ($facts->value('rtc.gross_currency') !== 'BRL') {
                    $policy = $this->codes['ordinary_partial_export_fx'] ?? null;
                    if (! is_array($policy)) {
                        return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_CURRENCY_CONVERSION_NOT_MATERIALIZED'], [], $trace);
                    }
                    $fx = (new PartialExportFxTransactionQualifier)->qualify($facts->canonicalValues(), $date, $knownAt, $policy);
                    $trace[] = ['stage' => 'PARTIAL_EXPORT_FX_TRANSACTION', ...$fx];
                    if (($fx['status'] ?? null) !== 'LOCAL_PARTIAL_EXPORT_FX_TRANSACTION_BOUND') {
                        $missing = is_array($fx['missing_facts'] ?? null) ? array_values(array_filter($fx['missing_facts'], static fn (mixed $key): bool => is_string($key))) : [];
                        $reason = $fx['reason'] ?? 'RTC_PARTIAL_EXPORT_FX_TRANSACTION_UNPROVEN';

                        return $this->stop('ACTION_REQUIRED', [is_string($reason) ? $reason : 'RTC_PARTIAL_EXPORT_FX_TRANSACTION_UNPROVEN'], $missing, $trace);
                    }
                    $exportGross = $fx['converted_gross_brl'];
                }
                $gross = DecimalValue::fromString($exportGross);
                if ($gross->isNegative()) {
                    return $this->stop('INVALID', ['RTC_NEGATIVE_GROSS'], [], $trace);
                }
                if ($facts->value('operation.amount') !== null
                    && $gross->compareTo(DecimalValue::fromString((string) $facts->value('operation.amount'))) !== 0
                    && ! $facts->has('rtc.operation_amount_reconciliation_id')) {
                    return $this->stop('ACTION_REQUIRED', ['RTC_GROSS_OPERATION_RECONCILIATION_MISSING'],
                        ['rtc.operation_amount_reconciliation_id'], $trace);
                }
                $gross = $gross->rounded(2);
                $foreignGross = $gross->multipliedBy($share)->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2);
                $domesticGross = $gross->minus($foreignGross)->rounded(2);
                $trace[] = ['stage' => 'PARTIAL_EXPORT_GROSS_ALLOCATION', 'source_article' => 'LC214 art. 80, §5º',
                    'total_gross_brl' => $gross->value(), 'foreign_share_percent' => $share->value(),
                    'foreign_gross_brl' => $foreignGross->value(), 'domestic_gross_brl' => $domesticGross->value(),
                    'method' => $facts->value('rtc.export_allocation_method'),
                    'allocation_evidence_id' => $facts->value('rtc.export_allocation_evidence_id'),
                    'domestic_consumption_evidence_id' => $facts->value('rtc.domestic_consumption_evidence_id')];

                if ($this->transitions->qualifiesPartialExport()) {
                    return $this->partialExportDomesticLeg($facts, $on, $knownAt, $domesticGross, $foreignGross, $trace);
                }

                return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_DOMESTIC_LEG_NOT_MATERIALIZED'], [], $trace);
            }
        }
        if ($market === 'DOMESTIC' && $facts->value('rtc.acquirer_country') !== 'BR'
            && $facts->value('rtc.recipient_country') !== 'BR'
            && $facts->value('rtc.domestic_consumption_evidence_id') === null) {
            return $this->stop('ACTION_REQUIRED', ['RTC_DOMESTIC_CONSUMPTION_UNPROVEN'], ['rtc.domestic_consumption_evidence_id'], $trace);
        }
        if ($market === 'IMPORT') {
            $importMissing = $facts->missing(['rtc.supplier_country', 'rtc.importer_id', 'rtc.import_document_id',
                'rtc.domestic_consumption_evidence_id', 'rtc.import_brazil_share_percent']);
            if ($importMissing !== []) {
                return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_EVIDENCE_MISSING'], $importMissing, $trace);
            }
            if ($facts->value('rtc.supplier_country') === 'BR') {
                return $this->stop('INVALID', ['RTC_IMPORT_SUPPLIER_NOT_FOREIGN'], [], $trace);
            }
            if ($kind !== 'DIGITAL' || $facts->value('rtc.delivery_mode') !== 'DIGITAL'
                || ! in_array($facts->value('rtc.destination_basis'), ['ACQUIRER_DOMICILE', 'RECIPIENT_DOMICILE'], true)) {
                return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_SPECIFIC_LOCATION_NOT_MATERIALIZED'], [], $trace);
            }
            $acquirerDomestic = $facts->value('rtc.acquirer_country') === 'BR';
            $recipientDomestic = $facts->value('rtc.recipient_country') === 'BR';
            if (! $acquirerDomestic && ! $recipientDomestic) {
                return $this->stop('INVALID', ['RTC_IMPORT_DOMESTIC_CONSUMPTION_CONFLICT'], [], $trace);
            }
            $taxpayer = $acquirerDomestic ? $facts->value('rtc.acquirer_id') : $facts->value('rtc.recipient_id');
            $expectedBasis = $acquirerDomestic ? 'ACQUIRER_DOMICILE' : 'RECIPIENT_DOMICILE';
            if ($facts->value('rtc.importer_id') !== $taxpayer || $facts->value('rtc.destination_basis') !== $expectedBasis) {
                return $this->stop('INVALID', ['RTC_IMPORT_TAXPAYER_DESTINATION_CONFLICT'], [], $trace);
            }
            $brazilShare = DecimalValue::fromString((string) $facts->value('rtc.import_brazil_share_percent'));
            if ($brazilShare->compareTo(DecimalValue::fromString('0')) <= 0
                || $brazilShare->compareTo(DecimalValue::fromString('100')) > 0) {
                return $this->stop('INVALID', ['RTC_IMPORT_BRAZIL_SHARE_INVALID'], [], $trace);
            }
            if ($brazilShare->compareTo(DecimalValue::fromString('100')) < 0) {
                $allocationMissing = $facts->missing(['rtc.import_allocation_method', 'rtc.import_allocation_evidence_id']);
                if ($allocationMissing !== []) {
                    return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_IMPORT_ALLOCATION_UNPROVEN'], $allocationMissing, $trace);
                }
            }
            if ($facts->value('rtc.platform_id') !== null && ! $this->transitions->qualifiesPlatformResponsibility()) {
                return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_PLATFORM_RESPONSIBILITY_NOT_MATERIALIZED'], [], $trace);
            }
            $trace[] = ['stage' => 'IMPORT_QUALIFICATION', 'source_article' => 'LC214 art. 64', 'taxpayer' => $taxpayer,
                'brazil_share_percent' => $brazilShare->value(), 'consumption_evidence_id' => $facts->value('rtc.domestic_consumption_evidence_id')];
            if ($importResponsibility129) {
                $responsibility = (new ImportResponsibility129)->resolve($facts, $platformDecision);
                $trace[] = ['stage' => 'IMPORT_RESPONSIBILITY129', ...$responsibility];
                if ($responsibility['status'] !== 'RESOLVED') {
                    return $this->stop($responsibility['status'], [$responsibility['reason']], $responsibility['missing_facts'], $trace);
                }
            }
        }
        $financialOperation = null;
        if (isset($this->codes['financial_operation']) && ($kind === 'FINANCIAL' || $facts->value('rtc.financial_provider') === true
            || $facts->value('rtc.financial_scope_proof') !== null || $facts->value('rtc.financial_ledger') !== null)) {
            $proof = $facts->value('rtc.financial_scope_proof');
            $ledger = $facts->value('rtc.financial_ledger');
            if ($facts->value('rtc.financial_provider') === false) {
                return $this->stop('INVALID', ['RTC_FINANCIAL_PROVIDER_INDICATOR_CONFLICT'], [], $trace);
            }
            if ($proof === null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_FINANCIAL_SCOPE_PROOF_MISSING'], ['rtc.financial_scope_proof'], $trace);
            }
            if (! is_array($proof) || ! array_is_list($proof) || count($proof) !== 1 || ! is_array($proof[0])
                || ($ledger !== null && (! is_array($ledger) || ! array_is_list($ledger) || count($ledger) !== 1 || ! is_array($ledger[0])))) {
                return $this->stop('INVALID', ['RTC_FINANCIAL_SCOPE_OR_LEDGER_INVALID'], [], $trace);
            }
            $financialOperation = (new FinancialOperationEngine)->resolve($proof[0], $ledger === null ? null : $ledger[0],
                (string) $facts->value('rtc.supplier_id'), (string) $facts->value('rtc.document_id'), $on, $knownAt,
                $this->codes['financial_operation']);
            $trace[] = ['stage' => 'FINANCIAL_OPERATION_AND_BASE', ...$financialOperation];
            if ($financialOperation['status'] !== 'RESOLVED') {
                return $this->stop($financialOperation['status'], [$financialOperation['reason']], [], $trace);
            }
            if ($financialOperation['qualification']['regime'] === 'SPECIFIC_FINANCIAL_ART181') {
                if (isset($this->codes['financial_operation']['tax'])) {
                    $fulfilled = $facts->value('rtc.accessory_obligations_fulfilled');
                    $issIncident = $facts->value('rtc.financial_service_subject_to_iss');
                    $financialTax = (new FinancialTaxEngine($this->rates, $this->codes['financial_rate_catalog'] ?? ['rates' => []]))
                        ->resolve($financialOperation, $on, $knownAt, is_bool($fulfilled) ? $fulfilled : null,
                            is_bool($issIncident) ? $issIncident : null, $this->codes['financial_operation']['tax']);
                    $trace[] = ['stage' => 'FINANCIAL_GROSS_DEBITS', ...$financialTax];
                    if ($financialTax['status'] !== 'RESOLVED') {
                        $financialMissing = match ($financialTax['reason']) {
                            'RTC_FINANCIAL_TAX_2026_COLLECTION_CONDITION_UNKNOWN' => ['rtc.accessory_obligations_fulfilled'],
                            'RTC_FINANCIAL_TAX_ISS_INCIDENCE_REQUIRED_FOR_COMBINED_RATE' => ['rtc.financial_service_subject_to_iss'],
                            default => [],
                        };

                        return $this->stop($financialTax['status'], [$financialTax['reason']], $financialMissing, $trace);
                    }

                    if (isset($this->codes['financial_operation']['credit'])) {
                        $creditProof = $facts->value('rtc.financial_credit_proof');
                        if ($creditProof === null && $facts->value('rtc.credit_context') === 'INPUT') {
                            return $this->stop('ACTION_REQUIRED', ['RTC_FINANCIAL_CREDIT_PROOF_MISSING'], ['rtc.financial_credit_proof'], $trace);
                        }
                        if ($creditProof !== null) {
                            if (! is_array($creditProof) || ! array_is_list($creditProof) || count($creditProof) !== 1 || ! is_array($creditProof[0])) {
                                return $this->stop('INVALID', ['RTC_FINANCIAL_CREDIT_PROOF_INVALID'], [], $trace);
                            }
                            if (($creditProof[0]['operation_category'] ?? null) !== $proof[0]['operation_category']
                                || ($facts->value('rtc.acquirer_regime') !== null && ($creditProof[0]['acquirer_regime'] ?? null) !== $facts->value('rtc.acquirer_regime'))) {
                                return $this->stop('INVALID', ['RTC_FINANCIAL_CREDIT_SCOPE_CONFLICT'], [], $trace);
                            }
                            $financialCredit = (new FinancialCreditEngine)->resolve($creditProof[0], $financialTax,
                                (string) $facts->value('rtc.supplier_id'), (string) $facts->value('rtc.acquirer_id'),
                                (string) $facts->value('rtc.document_id'), $on, $knownAt, $this->codes['financial_operation']['credit']);
                            $trace[] = ['stage' => 'FINANCIAL_BORROWER_CREDIT', ...$financialCredit];
                            if ($financialCredit['status'] !== 'RESOLVED') {
                                return $this->stop($financialCredit['status'], [$financialCredit['reason']], [], $trace);
                            }
                        } else {
                            $trace[] = ['stage' => 'FINANCIAL_BORROWER_CREDIT', 'status' => 'NOT_ASSESSED_ON_OUTPUT'];
                        }

                        return $this->stop('ACTION_REQUIRED', ['RTC_FINANCIAL_DOCUMENT_PENDING'], [], $trace);
                    }

                    return $this->stop('ACTION_REQUIRED', ['RTC_FINANCIAL_CREDIT_AND_DOCUMENT_PENDING'], [], $trace);
                }

                return $this->stop('ACTION_REQUIRED', ['RTC_FINANCIAL_RATE_CREDIT_AND_DOCUMENT_PENDING'], [], $trace);
            }
            if (! in_array($kind, ['SERVICE', 'DIGITAL'], true)) {
                return $this->stop('INVALID', ['RTC_FINANCIAL_GENERAL_OPERATION_KIND_CONFLICT'], [], $trace);
            }
        }
        if ($kind === 'SPECIFIC_REGIME' && $facts->value('rtc.specific_regime_rule_id') === null) {
            return $this->stop('ACTION_REQUIRED', ['RTC_SPECIFIC_REGIME_RULE_MISSING'], ['rtc.specific_regime_rule_id'], $trace);
        }
        if (in_array($kind, ['FINANCIAL', 'REAL_ESTATE', 'SPECIFIC_REGIME'], true)) {
            return $this->stop('ACTION_REQUIRED', ['RTC_SPECIFIC_REGIME_STRATEGY_NOT_MATERIALIZED:'.$kind], [], $trace);
        }
        if ($facts->value('customer.person_type') === 'PUBLIC_ADMINISTRATION') {
            if (! $this->transitions->qualifiesGovernmentPurchases()) {
                if ($date >= '2027-01-01') {
                    return $this->stop('ACTION_REQUIRED', ['RTC_GOVERNMENT_PURCHASE_RULE_NOT_MATERIALIZED'], ['rtc.government_entity_type'], $trace);
                }
            } else {
                $government = (new GovernmentPurchaseQualifier)->resolve($facts, $date);
                if ($government['status'] !== 'RESOLVED') {
                    return $this->stop($government['status'], [$government['reason']], $government['missing_facts'], $trace);
                }
                $treatment = $government['treatment'] ?? [];
                $trace[] = ['stage' => 'GOVERNMENT_PURCHASE', 'source_hash' => $this->transitions->sourceHash(),
                    'treatment' => $treatment];
                if (($treatment['taxable_event_at_payment'] ?? false) === true) {
                    $paymentMissing = $facts->missing(['rtc.government_payment_date', 'rtc.government_payment_evidence_id']);
                    if ($paymentMissing !== []) {
                        return $this->stop('ACTION_REQUIRED', ['RTC_GOVERNMENT_PAYMENT_EVIDENCE_MISSING'], $paymentMissing, $trace);
                    }
                    if ($facts->value('rtc.government_payment_date') !== $date) {
                        return $this->stop('ACTION_REQUIRED', ['RTC_GOVERNMENT_PAYMENT_DATE_RELEVANT'], ['rtc.operation_date'], $trace);
                    }
                }
                if (($treatment['article_472_reductor'] ?? null) === 'APPLIES_RATE_PENDING') {
                    return $this->stop('ACTION_REQUIRED', ['RTC_GOVERNMENT_REDUCTOR_NOT_PROVEN'], [], $trace);
                }
                if (($treatment['article_473_destination'] ?? null) === 'APPLIES') {
                    return $this->stop('ACTION_REQUIRED', ['RTC_GOVERNMENT_DESTINATION_CALCULATION_NOT_MATERIALIZED'], [], $trace);
                }
            }
        }
        if (in_array($regime, ['SIMPLES_NACIONAL', 'MEI'], true) && $date >= '2027-01-01' && $facts->value('rtc.simples_collection_option') === null) {
            return $this->stop('ACTION_REQUIRED', ['RTC_SIMPLES_OPTION_MISSING'], ['rtc.simples_collection_option'], $trace);
        }
        if ($regime === 'MEI' && $date >= '2027-01-01' && $facts->value('rtc.simples_collection_option') === 'REGULAR') {
            return $this->stop('INVALID', ['RTC_MEI_REGULAR_ELECTION_FORBIDDEN'], [], $trace);
        }
        if ($regime === 'SIMPLES_NACIONAL' && $date >= '2027-01-01'
            && $facts->value('rtc.simples_collection_option') === 'REGULAR') {
            $window = $this->transitions->resolveSimplesOption($on, $knownAt);
            if ($window['status'] !== 'RESOLVED' || ! isset($window['row'])) {
                return $this->stop($window['status'] === 'CONFLICT' ? 'INVALID' : 'ACTION_REQUIRED', [$window['reason']], [], $trace);
            }
            $windowRow = $window['row'];
            if (array_key_exists('qualification_policy', $windowRow)) {
                if (! is_array($windowRow['qualification_policy'])) {
                    return $this->stop('INVALID', ['RTC_SIMPLES_194_POLICY_CORRUPT'], [], $trace);
                }
                $election = (new SimplesRegularElectionQualifier78)->qualify($facts->canonicalValues(), $date, $knownAt, $windowRow['qualification_policy']);
                $trace[] = ['stage' => 'SIMPLES_REGULAR_ELECTION_194', ...$election];
                if ($election['status'] !== 'LOCAL_ELECTION_QUALIFIED') {
                    return $this->stop($election['status'] === 'INVALID' ? 'INVALID' : 'ACTION_REQUIRED',
                        [$election['reason']], $election['missing_facts'], $trace);
                }
                $trace[] = ['stage' => 'SIMPLES_REGULAR_OPTION', 'scope' => '2027_FIRST_SEMESTER',
                    'filed_on' => $election['filed_on'], 'receipt_id' => $election['receipt_id'],
                    'status_evidence_id' => $election['status_evidence_id'],
                    'source_id' => $windowRow['source_id'], 'source_hash' => $windowRow['source_hash'],
                    'route' => $election['route'], 'government_receipt_authenticated' => false];
            } else {
                $optionMissing = $facts->missing(['rtc.simples_option_filed_on', 'rtc.simples_option_receipt_id',
                    'rtc.simples_option_status', 'rtc.simples_option_status_evidence_id']);
                if ($optionMissing !== []) {
                    return $this->stop('ACTION_REQUIRED', ['RTC_SIMPLES_REGULAR_OPTION_UNPROVEN'], $optionMissing, $trace);
                }
                $filedOn = (string) $facts->value('rtc.simples_option_filed_on');
                if ($filedOn > $knownAt->format('Y-m-d')) {
                    return $this->stop('INVALID', ['RTC_SIMPLES_OPTION_AFTER_KNOWLEDGE_DATE'], [], $trace);
                }
                if ($filedOn < $windowRow['filing_from'] || $filedOn > $windowRow['filing_to']) {
                    return $this->stop('ACTION_REQUIRED', ['RTC_SIMPLES_OPTION_OUTSIDE_STANDARD_WINDOW'], [], $trace);
                }
                if ($facts->value('rtc.simples_option_status') !== 'CONFIRMED') {
                    return $this->stop('ACTION_REQUIRED', ['RTC_SIMPLES_REGULAR_OPTION_NOT_CONFIRMED'], [], $trace);
                }
                if ($knownAt->format('Y-m-d') <= $windowRow['cancellation_through']) {
                    return $this->stop('ACTION_REQUIRED', ['RTC_SIMPLES_OPTION_REVOCATION_WINDOW_OPEN'], [], $trace);
                }
                $trace[] = ['stage' => 'SIMPLES_REGULAR_OPTION',
                    'scope' => $windowRow['valid_from'] === '2027-07-01' ? '2027_SECOND_SEMESTER' : '2027_FIRST_SEMESTER',
                    'filed_on' => $filedOn, 'receipt_id' => $facts->value('rtc.simples_option_receipt_id'),
                    'status_evidence_id' => $facts->value('rtc.simples_option_status_evidence_id'),
                    'source_id' => $windowRow['source_id'], 'source_hash' => $windowRow['source_hash']];
            }
        }
        if (in_array($regime, ['MEI', 'SIMPLES_NACIONAL'], true) && $date >= '2027-01-01'
            && $facts->value('rtc.simples_collection_option') === 'INTERNAL') {
            return $this->stop('ACTION_REQUIRED', ['RTC_SIMPLES_INTERNAL_APPORTIONMENT_NOT_MATERIALIZED'], [], $trace);
        }
        if (in_array($regime, ['SIMPLES_NACIONAL', 'MEI'], true) && $date < '2027-01-01') {
            return ['status' => 'VALID', 'reason_codes' => ['RTC_SIMPLES_2026_TEST_NOT_APPLICABLE'], 'missing_facts' => [],
                'trace' => $trace, 'decision' => ['transition' => $this->transition($transitionRow), 'ibs' => ['status' => 'NOT_APPLICABLE'],
                    'cbs' => ['status' => 'NOT_APPLICABLE'], 'legacy_taxes' => $this->legacy($transitionRow)]];
        }
        if ($market === 'EXPORT') {
            return ['status' => 'VALID', 'reason_codes' => ['RTC_EXPORT_FOREIGN_CONSUMPTION'], 'missing_facts' => [],
                'trace' => $trace, 'decision' => ['transition' => $this->transition($transitionRow), 'ibs' => ['status' => 'IMMUNE_EXPORT', 'credit_treatment' => 'PRESERVED_SUBJECT_TO_PROOF'],
                    'cbs' => ['status' => 'IMMUNE_EXPORT', 'credit_treatment' => 'PRESERVED_SUBJECT_TO_PROOF'], 'legacy_taxes' => $this->legacy($transitionRow)]];
        }

        $basis = (string) $facts->value('rtc.destination_basis');
        $municipality = $facts->value('rtc.destination_municipality_ibge');
        $state = $facts->value('rtc.destination_state');
        if ($basis === 'ACQUIRER_DOMICILE' && $facts->value('rtc.acquirer_country') !== 'BR') {
            return $this->stop('INVALID', ['RTC_FOREIGN_ACQUIRER_REQUIRES_RECIPIENT_DESTINATION'], [], $trace);
        }
        if ($basis === 'RECIPIENT_DOMICILE'
            && ($facts->value('rtc.acquirer_country') === 'BR' || $facts->value('rtc.recipient_country') !== 'BR')) {
            return $this->stop('INVALID', ['RTC_RECIPIENT_DESTINATION_ROLE_CONFLICT'], [], $trace);
        }
        if ($municipality === null || $state === null || $facts->value('rtc.destination_evidence_id') === null) {
            return $this->stop('ACTION_REQUIRED', ['RTC_DESTINATION_UNPROVEN'], ['rtc.destination_state', 'rtc.destination_municipality_ibge', 'rtc.destination_evidence_id'], $trace);
        }
        $presenceDestination = null;
        $geographicStop = $this->geographicReference($facts, $on, $knownAt, 'ORDINARY_OR_IMPORT', $trace);
        if ($geographicStop !== null) {
            return $geographicStop;
        }
        if (isset($this->codes['presence_destination_qualification'])
            && $facts->value('rtc.service_on_person_or_enjoyed_in_person') === true
            && ($facts->value('rtc.delivery_mode') !== 'PRESENTIAL' || $basis !== 'PERSON_PRESENCE')) {
            return $this->stop('INVALID', ['RTC_PERSON_PRESENCE_DELIVERY_OR_BASIS_CONFLICT'], [], $trace);
        }
        if ($basis === 'PERSON_PRESENCE' && isset($this->codes['presence_destination_qualification'])) {
            $locationRule = $this->codes['presence_destination_qualification'];
            if ($knownAt < new DateTimeImmutable($locationRule['published_at'])) {
                return $this->stop('ACTION_REQUIRED', ['RTC_PRESENCE_LOCATION_RULE_NOT_AVAILABLE'], [], $trace);
            }
            $presenceDestination = (new PresenceDestinationQualifier)->resolve($facts);
            $trace[] = ['stage' => 'PERSON_PRESENCE_DESTINATION', 'source_hash' => $locationRule['source_hash'], ...$presenceDestination];
            if ($presenceDestination['status'] !== 'RESOLVED') {
                return $this->stop($presenceDestination['status'], [$presenceDestination['reason']], $presenceDestination['missing_facts'], $trace);
            }
        }
        if ($facts->value('rtc.destination_registration_status') === 'UNREGISTERED'
            && (! isset($this->codes['presence_destination_qualification']) || in_array($basis, ['ACQUIRER_DOMICILE', 'RECIPIENT_DOMICILE'], true))) {
            $sources = $facts->value('rtc.destination_evidence_sources');
            if (! is_array($sources)) {
                return $this->stop('ACTION_REQUIRED', ['RTC_DESTINATION_CORROBORATION_UNPROVEN'], ['rtc.destination_evidence_sources'], $trace);
            }
            $kinds = [];
            $evidenceIds = [];
            foreach ($sources as $source) {
                if (! is_array($source) || ! isset($source['kind'], $source['state'], $source['municipality_ibge'], $source['evidence_id'])
                    || ! in_array($source['kind'], ['DECLARED_ADDRESS', 'COMMERCIAL_INFO', 'PAYMENT_ARRANGEMENT', 'IP_GEOLOCATION'], true)
                    || ! is_string($source['evidence_id']) || $source['evidence_id'] === '') {
                    return $this->stop('ACTION_REQUIRED', ['RTC_DESTINATION_CORROBORATION_UNPROVEN'], ['rtc.destination_evidence_sources'], $trace);
                }
                if ($source['state'] !== $state || $source['municipality_ibge'] !== $municipality) {
                    return $this->stop('INVALID', ['RTC_DESTINATION_EVIDENCE_CONFLICT'], [], $trace);
                }
                $kinds[$source['kind']] = true;
                $evidenceIds[$source['evidence_id']] = true;
            }
            if (count($kinds) < 2 || count($evidenceIds) < 2) {
                return $this->stop('ACTION_REQUIRED', ['RTC_DESTINATION_CORROBORATION_UNPROVEN'], ['rtc.destination_evidence_sources'], $trace);
            }
            $trace[] = ['stage' => 'DESTINATION_CORROBORATION', 'source_article' => 'LC214 art. 11, §3º, II',
                'criteria' => array_keys($kinds), 'evidence_ids' => array_column($sources, 'evidence_id')];
        }
        $trace[] = ['stage' => 'DESTINATION', 'basis' => $basis, 'state' => $state, 'municipality' => $municipality];

        $treatment = (string) $facts->value('rtc.tax_treatment');
        $professionalReduction = null;
        $annexReduction = null;
        $institutionalImmunity = null;
        $cooperativeZero = null;
        if ($treatment === 'IMMUNE' && isset($this->codes['institutional_immunity'])) {
            if ($market !== 'DOMESTIC' || $regime !== 'REGULAR' || $consideration !== 'ONEROUS') {
                return $this->stop('ACTION_REQUIRED', ['RTC_INSTITUTIONAL_IMMUNITY_OTHER_OPERATION_NOT_MATERIALIZED'], [], $trace);
            }
            $institutionalImmunity = (new InstitutionalImmunityQualifier)->resolve($facts, $on, $knownAt,
                $this->codes['institutional_immunity']);
            $trace[] = ['stage' => 'INSTITUTIONAL_IMMUNITY', ...$institutionalImmunity];
            if ($institutionalImmunity['status'] !== 'RESOLVED') {
                return $this->stop($institutionalImmunity['status'], [$institutionalImmunity['reason']],
                    $institutionalImmunity['missing_facts'], $trace);
            }
        } elseif ($treatment === 'REDUCED_30' && isset($this->codes['professional_reduction'])) {
            $professionalReduction = (new ProfessionalReductionQualifier)->resolve($facts, $on, $knownAt,
                $this->codes['professional_reduction']);
            $trace[] = ['stage' => 'PROFESSIONAL_REDUCTION', ...$professionalReduction];
            if ($professionalReduction['status'] !== 'RESOLVED') {
                return $this->stop($professionalReduction['status'], [$professionalReduction['reason']],
                    $professionalReduction['missing_facts'], $trace);
            }
        } elseif ($treatment === 'REDUCED_60' && isset($this->codes['annex_service_reduction'])) {
            $annexReduction = (new AnnexServiceReductionQualifier)->resolve($facts, $on, $knownAt, $this->codes['annex_service_reduction']);
            $trace[] = ['stage' => 'ANNEX_SERVICE_REDUCTION', ...$annexReduction];
            if ($annexReduction['status'] !== 'RESOLVED') {
                return $this->stop($annexReduction['status'], [$annexReduction['reason']], $annexReduction['missing_facts'], $trace);
            }
        } elseif ($treatment === 'ZERO_RATE' && isset($this->codes['cooperative_zero'])) {
            $cooperativeZero = (new CooperativeZeroRateQualifier)->resolve($facts, $on, $knownAt, $this->codes['cooperative_zero']);
            $trace[] = ['stage' => 'COOPERATIVE_ZERO', ...$cooperativeZero];
            if ($cooperativeZero['status'] !== 'RESOLVED') {
                return $this->stop($cooperativeZero['status'], [$cooperativeZero['reason']], $cooperativeZero['missing_facts'], $trace);
            }
        } elseif ($treatment !== 'STANDARD') {
            if ($facts->value('rtc.treatment_rule_id') === null || $facts->value('rtc.treatment_evidence_id') === null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_TREATMENT_UNPROVEN'], ['rtc.treatment_rule_id', 'rtc.treatment_evidence_id'], $trace);
            }

            return $this->stop('ACTION_REQUIRED', ['RTC_TREATMENT_STRATEGY_NOT_MATERIALIZED:'.$treatment], [], $trace);
        }
        if ($facts->value('rtc.assess_cooperative_credit_transfer') === true) {
            $transfer = (new CooperativeCreditTransferQualifier)->resolve($facts, $on, $knownAt,
                $this->codes['cooperative_credit_transfer'] ?? [], $cooperativeZero ?? []);
            $trace[] = ['stage' => 'COOPERATIVE_CREDIT_TRANSFER', ...$transfer];
            if ($transfer['status'] !== 'RESOLVED') {
                return $this->stop($transfer['status'], [$transfer['reason']], $transfer['missing_facts'], $trace);
            }
            $cooperativeZero['credit_transfer'] = $transfer;
        }
        $rateReduction = $professionalReduction ?? $annexReduction ?? $cooperativeZero;
        $eligibleRules = [];
        foreach ($this->codes['engine_decision_rules'] ?? [] as $id => $rule) {
            $canonicalService = $rule['canonical_service_id'] ?? null;
            if ($canonicalService === null && $id === 'RTC.CLASSIFICATION.SAAS.RESIDUAL.SANDBOX-1'
                && ($rule['version'] ?? null) === '1.0.0') {
                // Earlier captured knowledge omitted this field; retain replay for its SaaS scope.
                $canonicalService = 'SOFTWARE_DEVELOPMENT';
            }
            if (($rule['kind'] ?? null) === 'ENGINE_DECISION_RULE'
                && ($rule['operation_kind'] ?? null) === $kind && ($rule['market'] ?? null) === $market
                && $canonicalService === $facts->value('service.canonical_id')
                && ($rule['tax_treatment'] ?? 'STANDARD') === $treatment
                && (! isset($rule['immunity_cclasstrib']) || $rule['immunity_cclasstrib'] === ($institutionalImmunity['cclasstrib'] ?? null))
                && (! isset($rule['professional_category']) || $rule['professional_category'] === $facts->value('rtc.professional_category'))
                && (! isset($rule['annex_service_category']) || $rule['annex_service_category'] === $facts->value('rtc.annex_service_category'))
                && (! isset($rule['presence_cindop']) || $rule['presence_cindop'] === ($presenceDestination['cindop'] ?? null))
                && (isset($rule['delivery_modes']) ? in_array($facts->value('rtc.delivery_mode'), $rule['delivery_modes'], true)
                    : ($rule['delivery_mode'] ?? null) === $facts->value('rtc.delivery_mode'))
                && $this->matchesNbsRule($rule, $facts)
                && ($rule['destination_basis'] ?? null) === $basis
                && isset($rule['valid_from'], $rule['published_at'])
                && $date >= $rule['valid_from'] && (! isset($rule['valid_to']) || $date <= $rule['valid_to'])
                && $knownAt >= new DateTimeImmutable($rule['published_at'])) {
                $eligibleRules[$id] = $rule;
            }
        }
        if (count($eligibleRules) !== 1) {
            return $this->stop(count($eligibleRules) > 1 ? 'INVALID' : 'ACTION_REQUIRED',
                [count($eligibleRules) > 1 ? 'RTC_ENGINE_DECISION_RULE_CONFLICT' : 'RTC_ENGINE_DECISION_RULE_NOT_PROVEN'], [], $trace);
        }
        $classificationRuleId = array_key_first($eligibleRules);
        $classificationRule = $eligibleRules[$classificationRuleId];
        $code = isset($classificationRule['cindop_by_presence'])
            ? (string) $classificationRule['cindop_by_presence'][$facts->value('rtc.presence_at_supplier_establishment') === true ? 'SUPPLIER' : 'OTHER']
            : (string) $classificationRule['cindop'];
        $class = (string) $classificationRule['cclasstrib'];
        if (CIndOpReference126::required($this->codes)) {
            $operationReference = (new CIndOpReference126)->resolve($this->codes, $code, 'LOCAL_SANDBOX', $on, $knownAt);
            $trace[] = ['stage' => 'CINDOP_REFERENCE126', 'context' => 'STANDARD_OPERATION', ...$operationReference];
            if ($operationReference['status'] !== 'REFERENCE_ROW_AVAILABLE') {
                return $this->stop($operationReference['status'], [$operationReference['reason']], [], $trace);
            }
        }
        if (($facts->has('rtc.cindop') && $facts->value('rtc.cindop') !== $code)
            || ($facts->has('rtc.cclasstrib') && $facts->value('rtc.cclasstrib') !== $class)
            || ($facts->has('rtc.classification_rule_id') && $facts->value('rtc.classification_rule_id') !== $classificationRuleId)) {
            return $this->stop('INVALID', ['RTC_SUPPLIED_CLASSIFICATION_CONFLICT'], [], $trace);
        }
        if (! isset($this->codes['cindop'][$code]) || ! isset($this->codes['cclasstrib'][$class])) {
            return $this->stop('INVALID', ['RTC_DOCUMENT_CODE_UNKNOWN'], [], $trace);
        }
        $classRow = $this->codes['cclasstrib'][$class];
        $reference = $this->cclassReference($class, $on, $knownAt);
        if ($reference !== null) {
            $trace[] = ['stage' => 'CCLASSTRIB_REFERENCE', 'context' => 'STANDARD_OPERATION', ...$reference];
            if ($reference['status'] !== 'REFERENCE_ROW_AVAILABLE') {
                return $this->stop($reference['status'], [$reference['reason']], [], $trace);
            }
        }
        if (($this->codes['cindop'][$code]['nfse_supported'] ?? false) !== true || ($classRow['nfse_supported'] ?? false) !== true) {
            return $this->stop('INVALID', ['RTC_CODE_NOT_SUPPORTED_BY_NFSE'], [], $trace);
        }
        if (($classRow['valid_from'] ?? null) === null || $date < $classRow['valid_from']
            || (($classRow['valid_to'] ?? null) !== null && $date > $classRow['valid_to'])) {
            return $this->stop('ACTION_REQUIRED', ['RTC_CCLASSTRIB_OUTSIDE_PROVEN_VALIDITY'], [], $trace);
        }
        if (($rateReduction === null && $institutionalImmunity === null && $classRow['cst'] !== '000')
            || ($institutionalImmunity !== null && ($classRow['cst'] !== '410' || $class !== $institutionalImmunity['cclasstrib']))
            || ($rateReduction !== null && ($classRow['cst'] !== $rateReduction['cst']
                || $class !== $rateReduction['cclasstrib']))) {
            return $this->stop('ACTION_REQUIRED', ['RTC_STANDARD_TREATMENT_CLASS_CONFLICT'], [], $trace);
        }
        $trace[] = ['stage' => 'CLASSIFICATION', 'rule_id' => $classificationRuleId,
            'kind' => 'ENGINE_DECISION_RULE', 'review_status' => $classificationRule['review_status']];

        $gross = DecimalValue::fromString((string) $facts->value('rtc.gross_amount'));
        if ($market === 'IMPORT' && $facts->value('rtc.gross_currency') === null) {
            return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_GROSS_CURRENCY_MISSING'], ['rtc.gross_currency'], $trace);
        }
        $fxTransaction = null;
        if ($market === 'IMPORT' && $facts->value('rtc.gross_currency') !== 'BRL') {
            $fxPolicy = $this->codes['ordinary_import_fx'] ?? null;
            if (! is_array($fxPolicy)) {
                return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_FX_CONVERSION_NOT_MATERIALIZED'], [], $trace);
            }
            $fxTransaction = (new ImportFxTransactionQualifier)->qualify($facts->canonicalValues(), $date, $knownAt, $fxPolicy);
            $trace[] = ['stage' => 'IMPORT_FX_TRANSACTION', ...$fxTransaction];
            if (($fxTransaction['status'] ?? null) !== 'LOCAL_IMPORT_FX_TRANSACTION_BOUND'
                || ! is_string($fxTransaction['converted_gross_brl'] ?? null)) {
                $reason = $fxTransaction['reason'] ?? 'RTC_IMPORT_FX_CONVERSION_UNPROVEN';
                $missing = $facts->missing(['rtc.import_fx_source_id', 'rtc.import_fx_proof', 'rtc.operation_amount_reconciliation_id']);

                return $this->stop('ACTION_REQUIRED', [is_string($reason) ? $reason : 'RTC_IMPORT_FX_CONVERSION_UNPROVEN'], $missing, $trace);
            }
            $gross = DecimalValue::fromString($fxTransaction['converted_gross_brl']);
        }
        if ($gross->isNegative()) {
            return $this->stop('INVALID', ['RTC_NEGATIVE_GROSS'], [], $trace);
        }
        if ($personalUseRecipient) {
            $marketValue = DecimalValue::fromString((string) $facts->value('rtc.market_value_brl'));
            if ($consideration === 'NON_ONEROUS' && $gross->compareTo(DecimalValue::fromString('0')) !== 0) {
                return $this->stop($this->transitions->qualifiesEmployeeBelowMarket() ? 'INVALID' : 'ACTION_REQUIRED',
                    [$this->transitions->qualifiesEmployeeBelowMarket()
                        ? 'RTC_NON_ONEROUS_CONSIDERATION_AMOUNT_CONFLICT' : 'RTC_BELOW_MARKET_STRATEGY_NOT_MATERIALIZED'], [], $trace);
            }
            if ($personalBelowMarketCandidate && ($gross->compareTo(DecimalValue::fromString('0')) <= 0
                || $gross->compareTo($marketValue) >= 0)) {
                return $this->stop('INVALID', [$this->transitions->qualifiesPersonalUseRecipients()
                    ? 'RTC_PERSONAL_USE_PRICE_NOT_BELOW_MARKET' : 'RTC_EMPLOYEE_PRICE_NOT_BELOW_MARKET'], [], $trace);
            }
        }
        if ($facts->value('operation.amount') !== null
            && $gross->compareTo(DecimalValue::fromString((string) $facts->value('operation.amount'))) !== 0
            && ! $facts->has('rtc.operation_amount_reconciliation_id')) {
            return $this->stop('ACTION_REQUIRED', ['RTC_GROSS_OPERATION_RECONCILIATION_MISSING'], ['rtc.operation_amount_reconciliation_id'], $trace);
        }
        $base = $gross;
        $adjustments = $facts->value('rtc.base_adjustments');
        if (! is_array($adjustments)) {
            return $this->stop('INVALID', ['RTC_ADJUSTMENTS_INVALID'], [], $trace);
        }
        $legacyExclusions = 0;
        foreach ($adjustments as $adjustment) {
            if (! is_array($adjustment) || ! isset($adjustment['kind'], $adjustment['amount'], $adjustment['evidence_id'])) {
                return $this->stop('ACTION_REQUIRED', ['RTC_ADJUSTMENT_EVIDENCE_MISSING'], [], $trace);
            }
            $amount = DecimalValue::fromString((string) $adjustment['amount']);
            if ($amount->isNegative()) {
                return $this->stop('INVALID', ['RTC_NEGATIVE_ADJUSTMENT'], [], $trace);
            }
            $base = match ($adjustment['kind']) {
                'INCLUDED_CHARGE' => $base->plus($amount),
                'UNCONDITIONAL_DISCOUNT', 'DOCUMENTED_REIMBURSEMENT', 'LEGACY_TAX_EXCLUSION' => $base->minus($amount),
                default => $base,
            };
            if (! in_array($adjustment['kind'], ['INCLUDED_CHARGE', 'UNCONDITIONAL_DISCOUNT', 'DOCUMENTED_REIMBURSEMENT', 'LEGACY_TAX_EXCLUSION'], true)) {
                return $this->stop('INVALID', ['RTC_ADJUSTMENT_KIND_UNKNOWN'], [], $trace);
            }
            if ($adjustment['kind'] === 'LEGACY_TAX_EXCLUSION') {
                $legacyExclusions++;
                $allowedLegacyTaxes = $date < '2033-01-01' ? ['ISS', 'ICMS', 'PIS', 'COFINS'] : [];
                if (! in_array($adjustment['tax'] ?? null, $allowedLegacyTaxes, true)) {
                    return $this->stop('INVALID', ['RTC_LEGACY_TAX_EXCLUSION_INVALID'], [], $trace);
                }
            }
            if ($adjustment['kind'] === 'UNCONDITIONAL_DISCOUNT'
                && (($adjustment['document_id'] ?? null) !== $facts->value('rtc.document_id')
                    || ($adjustment['depends_on_later_event'] ?? null) !== false)) {
                return $this->stop('ACTION_REQUIRED', ['RTC_UNCONDITIONAL_DISCOUNT_PROOF_MISSING'], [], $trace);
            }
            if ($adjustment['kind'] === 'DOCUMENTED_REIMBURSEMENT'
                && (! isset($adjustment['third_party_document_id'], $adjustment['third_party_id'])
                    || ($adjustment['document_in_third_party_name'] ?? null) !== true)) {
                return $this->stop('ACTION_REQUIRED', ['RTC_THIRD_PARTY_REIMBURSEMENT_PROOF_MISSING'], [], $trace);
            }
        }
        if ($facts->value('rtc.legacy_tax_inclusion') === 'INCLUDED' && $legacyExclusions === 0) {
            return $this->stop('ACTION_REQUIRED', ['RTC_LEGACY_TAX_EXCLUSIONS_MISSING'], ['rtc.base_adjustments'], $trace);
        }
        if ($facts->value('rtc.legacy_tax_inclusion') === 'EXCLUDED' && $legacyExclusions > 0) {
            return $this->stop('INVALID', ['RTC_LEGACY_TAX_EXCLUSION_DOUBLE_COUNT'], [], $trace);
        }
        if ($base->isNegative()) {
            return $this->stop('INVALID', ['RTC_NEGATIVE_BASE'], [], $trace);
        }
        if ($annexReduction !== null && $annexReduction['excluded_medical_glosa'] !== '0.00') {
            $glosa = DecimalValue::fromString($annexReduction['excluded_medical_glosa']);
            if ($glosa->compareTo($base) > 0) {
                return $this->stop('INVALID', ['RTC_HEALTH_GLOSA_EXCEEDS_REMAINING_BASE'], [], $trace);
            }
            $base = $base->minus($glosa);
            $adjustments[] = ['kind' => 'MEDICAL_AUDIT_UNPAID_GLOSA', 'amount' => $glosa->value(),
                'evidence_id' => $facts->value('rtc.health_medical_audit_glosa_evidence_id'),
                'nonpayment_evidence_id' => $facts->value('rtc.health_glosa_nonpayment_evidence_id'),
                'source_article' => 'LC214 art. 130, parágrafo único', 'source_hash' => $annexReduction['source_hash']];
            $trace[] = ['stage' => 'MEDICAL_AUDIT_BASE_EXCLUSION', ...$adjustments[array_key_last($adjustments)]];
        }
        if ($personalUseRecipient) {
            if ($adjustments !== []) {
                return $this->stop('ACTION_REQUIRED', ['RTC_NON_ONEROUS_ADJUSTMENTS_NOT_MATERIALIZED'], [], $trace);
            }
            $base = DecimalValue::fromString((string) $facts->value('rtc.market_value_brl'));
            if ($base->compareTo(DecimalValue::fromString('0')) <= 0) {
                return $this->stop('INVALID', ['RTC_NON_ONEROUS_MARKET_VALUE_INVALID'], [], $trace);
            }
            $marketTrace = ['stage' => 'NON_ONEROUS_MARKET_BASE', 'source_article' => 'LC214 art. 5º, §9º; art. 12, §4º',
                'market_value_brl' => $base->rounded(2)->value(), 'evidence_id' => $facts->value('rtc.market_value_evidence_id')];
            if ($this->transitions->qualifiesEmployeeBelowMarket()) {
                $marketTrace['consideration'] = $consideration;
                $marketTrace['charged_gross_brl'] = $gross->rounded(2)->value();
            }
            $trace[] = $marketTrace;
        }
        if ($market === 'IMPORT') {
            $brazilShare = DecimalValue::fromString((string) $facts->value('rtc.import_brazil_share_percent'));
            $totalBase = $base->value();
            $base = $base->multipliedBy($brazilShare)->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2);
            $trace[] = ['stage' => 'IMPORT_BRAZIL_ALLOCATION', 'source_article' => 'LC214 art. 64, §3º',
                'total_base_brl' => $totalBase, 'brazil_share_percent' => $brazilShare->value(),
                'brazil_taxable_base_brl' => $base->value(),
                'method' => $facts->value('rtc.import_allocation_method'),
                'evidence_id' => $facts->value('rtc.import_allocation_evidence_id') ?? $facts->value('rtc.domestic_consumption_evidence_id')];
        }
        $trace[] = ['stage' => 'BASE', 'gross' => $gross->value(), 'adjustments' => $adjustments, 'taxable_base' => $base->value()];

        if ($advanceOrigin !== null) {
            if ($adjustments !== [] || $base->compareTo($gross) !== 0 || $rateReduction !== null
                || $annexReduction !== null || $institutionalImmunity !== null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_ADVANCE_BASE_OR_RATE_TRANSFORMATION_UNPROVEN'], [], $trace);
            }
            $nominal = (new AdvanceOnlyNominal87($this->rates))->calculate($advanceOrigin['context'],
                $advanceOrigin['selected_installment_id'], (string) $state, (string) $municipality, $knownAt);
            $trace[] = ['stage' => 'ADVANCE_ORIGIN_NOMINAL', ...$nominal];

            return $this->stop('ACTION_REQUIRED', [$nominal['status'] === 'LOCAL_ADVANCE_NOMINAL_ONLY'
                ? 'RTC_ADVANCE_REGISTRATION_AND_DOCUMENT_OBLIGATION_NOT_PROVEN' : $nominal['reason']], [], $trace);
        }

        if ($taxPoint !== null && $taxPoint['requires_advance_reconciliation'] === true) {
            if ($adjustments !== [] || $base->compareTo($gross) !== 0 || $rateReduction !== null
                || $annexReduction !== null || $institutionalImmunity !== null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_TAX_POINT_EVENT_BASE_OR_RATE_TRANSFORMATION_UNPROVEN'], [], $trace);
            }
            if ($recordedAdvanceRequested) {
                $recorded = (new RecordedAdvanceContext91($this->rates))->reconcile($facts->canonicalValues(), $taxPoint,
                    $knownAt, $this->transitions->taxPointPolicy() ?? []);
                $trace[] = ['stage' => 'RECORDED_ADVANCE_RECONCILIATION', ...$recorded];

                return $this->stop('ACTION_REQUIRED', [$recorded['status'] === 'LOCAL_CAPTURED_ADVANCE_FINAL_RECONCILIATION_ONLY'
                    ? 'RTC_RECORDED_ADVANCE_DOCUMENT_AND_POSTING_NOT_PROVEN' : $recorded['reason']], [], $trace);
            }
            $reconciliation = (new AdvanceTaxReconciler84($this->rates))->reconcile(
                $taxPoint['event_plan'], (string) $state, (string) $municipality, $knownAt);
            $trace[] = ['stage' => 'TEMPORAL_ADVANCE_RECONCILIATION', ...$reconciliation];

            return $this->stop('ACTION_REQUIRED', [$reconciliation['status'] === 'LOCAL_ADVANCE_RECONCILIATION_ONLY'
                ? 'RTC_TEMPORAL_PRIOR_ADVANCE_DEBITS_NOT_PROVEN' : $reconciliation['reason']], [], $trace);
        }

        if ($institutionalImmunity !== null) {
            $row = ['percent' => '0.00', 'source_id' => $institutionalImmunity['source_id']];
            $stateRate = $municipalRate = $cbsRate = ['status' => 'RESOLVED', 'row' => $row];
            $trace[] = ['stage' => 'IMMUNE_SUPPLY', 'operation_reference_value' => $base->value(),
                'taxable_base' => '0.00', 'rate_applicability' => 'NOT_APPLICABLE', 'source_hash' => $institutionalImmunity['source_hash']];
            $base = DecimalValue::fromString('0.00');
        } else {
            $stateRate = $this->rates->resolve('IBS_STATE', $date < '2029-01-01' ? 'BR' : (string) $state, $on, $knownAt);
            $municipalRate = $this->rates->resolve('IBS_MUNICIPAL', $date < '2029-01-01' ? 'BR' : (string) $municipality, $on, $knownAt);
            $cbsRate = $this->rates->resolve('CBS', 'BR', $on, $knownAt);
        }
        foreach ([$stateRate, $municipalRate, $cbsRate] as $rate) {
            if ($rate['status'] !== 'RESOLVED') {
                return $this->stop($rate['status'] === 'CONFLICT' ? 'INVALID' : 'ACTION_REQUIRED', [$rate['reason'] ?? 'RTC_RATE_CATALOG_CORRUPT'], [], $trace);
            }
        }
        if (! isset($stateRate['row'], $municipalRate['row'], $cbsRate['row'])) {
            return $this->stop('INVALID', ['RTC_RATE_CATALOG_CORRUPT'], [], $trace);
        }
        $stateRow = $stateRate['row'];
        $municipalRow = $municipalRate['row'];
        $cbsRow = $cbsRate['row'];
        $standardRateRows = [$stateRow, $municipalRow, $cbsRow];
        if ($rateReduction !== null) {
            $factor = DecimalValue::fromString('100')->minus(DecimalValue::fromString($rateReduction['rate_reduction_percent']))
                ->dividedBy(DecimalValue::fromString('100'), 2);
            $effectiveRows = [];
            foreach ($standardRateRows as $rateRow) {
                $effective = DecimalValue::fromString((string) $rateRow['percent'])
                    ->multipliedBy($factor);
                if ($effective->compareTo($effective->rounded(2)) !== 0) {
                    return $this->stop('ACTION_REQUIRED', ['RTC_EFFECTIVE_RATE_DOCUMENT_PRECISION_UNPROVEN'], [], $trace);
                }
                $rateRow['percent'] = $effective->rounded(2)->value();
                $effectiveRows[] = $rateRow;
            }
            [$stateRow, $municipalRow, $cbsRow] = $effectiveRows;
            $trace[] = ['stage' => 'REDUCED_RATE_CALCULATION', 'reduction_percent' => $rateReduction['rate_reduction_percent'],
                'standard_rates' => array_column($standardRateRows, 'percent'),
                'effective_rates' => [$stateRow['percent'], $municipalRow['percent'], $cbsRow['percent']],
                'policy' => 'EXACT_RATE_MULTIPLICATION_THEN_HALF_UP_AMOUNT_2'];
        }
        $ibsState = $this->tax($base, (string) $stateRow['percent']);
        $ibsMunicipal = $this->tax($base, (string) $municipalRow['percent']);
        $cbs = $this->tax($base, (string) $cbsRow['percent']);
        if ($personalUseRecipient) {
            $trace[] = ['stage' => 'NON_ONEROUS_NOMINAL_CALCULATION', 'source_article' => 'LC214 art. 5º, §9º',
                'ibs_state' => $ibsState, 'ibs_municipal' => $ibsMunicipal, 'cbs' => $cbs,
                'rate_sources' => [$stateRow['source_id'], $municipalRow['source_id'], $cbsRow['source_id']]];

            return $this->stop('ACTION_REQUIRED', ['RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED'], [], $trace);
        }
        $layouts = array_values(array_filter($this->codes['document_layouts'] ?? [],
            static fn (array $layout): bool => ($layout['status'] ?? null) === 'ACTIVE'
                && isset($layout['valid_from'], $layout['published_at'])
                && $date >= $layout['valid_from']
                && (! isset($layout['valid_to']) || $date <= $layout['valid_to'])
                && isset($layout['sandbox_coverage_through']) && $date <= $layout['sandbox_coverage_through']
                && $knownAt >= new DateTimeImmutable($layout['published_at'])));
        if (count($layouts) !== 1) {
            return $this->stop(count($layouts) > 1 ? 'INVALID' : 'ACTION_REQUIRED',
                [count($layouts) > 1 ? 'RTC_DOCUMENT_LAYOUT_CONFLICT' : 'RTC_DOCUMENT_LAYOUT_NOT_PROVEN'], [], $trace);
        }
        if ($market === 'IMPORT') {
            $trace[] = ['stage' => 'IMPORT_NOMINAL_CALCULATION', 'source_article' => 'LC214 art. 64, §5º',
                'ibs_state' => $ibsState, 'ibs_municipal' => $ibsMunicipal, 'cbs' => $cbs,
                'rate_sources' => [$stateRow['source_id'], $municipalRow['source_id'], $cbsRow['source_id']]];
            if ($this->transitions->qualifiesImportCredits() && $facts->value('rtc.credit_context') === 'INPUT') {
                $importCredit = (new CreditDecisionEngine)->resolve($facts, 'IMPORT', true, $inputCreditTreatment);
                if ($importCredit['status'] !== 'RESOLVED') {
                    $creditMissing = $creditInput122
                        ? (new CreditInput122)->correctionFacts($importCredit['reason'], $importCredit['missing_facts'])
                        : $importCredit['missing_facts'];
                    if ($creditMissing !== $importCredit['missing_facts']) {
                        $trace[] = ['stage' => 'CREDIT_CORRECTION122', 'reason' => $importCredit['reason'],
                            'missing_facts' => $creditMissing, 'credit_granted' => false];
                    }

                    return $this->stop('ACTION_REQUIRED', [$importCredit['reason']], $creditMissing, $trace);
                }
                $trace[] = ['stage' => 'IMPORT_CREDIT', 'source_article' => $importCredit['source_article'] ?? null,
                    'source_hash' => $this->transitions->sourceHash(),
                    'fiscal_document_id' => $facts->value('rtc.import_credit_fiscal_document_id'),
                    'extinction_evidence_id' => $facts->value('rtc.import_credit_extinction_evidence_id'),
                    'ibs' => $importCredit['ibs'], 'cbs' => $importCredit['cbs']];
            }
            $expectedCollector = $facts->value('rtc.acquirer_country') === 'BR' ? 'ACQUIRER' : 'RECIPIENT';
            if ($facts->value('rtc.collection_method') !== $expectedCollector || $facts->value('rtc.collection_legal_basis') === null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_COLLECTION_RESPONSIBILITY_UNPROVEN'],
                    $facts->value('rtc.collection_legal_basis') === null ? ['rtc.collection_legal_basis'] : [], $trace);
            }
            $documentMissing = $facts->missing(['rtc.supplier_foreign_nif', 'rtc.import_transaction_evidence_id', 'rtc.import_fx_contract_applicable']);
            if ($facts->value('rtc.import_fx_contract_applicable') === true && $facts->value('rtc.import_fx_contract_id') === null) {
                $documentMissing[] = 'rtc.import_fx_contract_id';
            }
            if ($documentMissing !== []) {
                return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_DOCUMENT_EVIDENCE_MISSING'], $documentMissing, $trace);
            }
            $issuerFields = [
                'DPS.infDPS.tpEmit' => '2',
                'DPS.infDPS.cMotivoEmisTI' => '1',
                'DPS.infDPS.prest.NIF' => $facts->value('rtc.supplier_foreign_nif'),
                'DPS.infDPS.prest.end.endExt.cPais' => $facts->value('rtc.supplier_country'),
                'DPS.infDPS.IBSCBS.cIndOp' => $code,
                'DPS.infDPS.IBSCBS.valores.trib.gIBSCBS.CST' => $classRow['cst'],
                'DPS.infDPS.IBSCBS.valores.trib.gIBSCBS.cClassTrib' => $class,
            ];
            if (isset($this->codes['dps_xsd'])) {
                if (DpsLayoutPolicy127::required($this->codes)) {
                    $issuerFields = (new DpsLayoutPolicy127)->mapIssuerFields($issuerFields);
                }
                $validation = (new DpsIssuerFieldValidator)->validate($issuerFields, $layouts[0], $this->codes);
                $trace[] = ['stage' => 'DPS_ISSUER_XSD_VALIDATION', ...$validation];
                if ($validation['status'] !== 'VALID') {
                    return $this->stop($validation['status'], [$validation['reason']], [], $trace);
                }
            }
            $trace[] = ['stage' => 'IMPORT_LOCAL_DPS_PROJECTION', 'scope' => 'PARTIAL_SANDBOX_NO_EMISSION',
                'schema_version' => $layouts[0]['version'], 'schema_source_hash' => $layouts[0]['source_hash'],
                'issuer_fields' => $issuerFields,
                'evidence' => ['foreign_invoice_id' => $facts->value('rtc.import_document_id'),
                    'transaction_evidence_id' => $facts->value('rtc.import_transaction_evidence_id'),
                    'fx_contract_id' => $facts->value('rtc.import_fx_contract_id')]];

            if (DocumentSchedule131::required($this->codes)) {
                $documentRegime = $regime;
                if (($this->codes['version'] ?? null) === DocumentSchedule131::VERSION) {
                    $regimeFact = $facts->value('rtc.acquirer_country') === 'BR' ? 'rtc.acquirer_regime' : 'rtc.recipient_regime';
                    $documentRegime = $facts->value($regimeFact);
                    if (! is_string($documentRegime)) {
                        return $this->stop('ACTION_REQUIRED', ['RTC_DOCUMENT131_TAXPAYER_REGIME_REQUIRED'], [$regimeFact], $trace);
                    }
                }
                $schedule = (new DocumentObligationEngine)->resolve((string) ($classificationRule['ctribnac_code'] ?? ''),
                    $documentRegime, $on, $knownAt, $this->codes, in_array($platformDecision['responsibility'] ?? null,
                        ['FOREIGN_SUPPLIER_SUBSTITUTIVE_SOLIDARY', 'DOMESTIC_SUPPLIER_SOLIDARY', 'NOT_TRIGGERED'], true));
                $trace[] = ['stage' => 'IMPORT_DOCUMENT_SCHEDULE131', ...$schedule];
                if ($schedule['status'] !== 'RESOLVED') {
                    return $this->stop($schedule['status'], [$schedule['reason']], [], $trace);
                }
            }

            return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'], [], $trace);
        }
        $credit = (new CreditDecisionEngine)->resolve($facts, $market, treatmentDecision: $inputCreditTreatment);
        if ($credit['status'] === 'ACTION_REQUIRED') {
            $creditMissing = $creditInput122
                ? (new CreditInput122)->correctionFacts($credit['reason'], $credit['missing_facts'])
                : $credit['missing_facts'];
            if ($creditMissing !== $credit['missing_facts']) {
                $trace[] = ['stage' => 'CREDIT_CORRECTION122', 'reason' => $credit['reason'],
                    'missing_facts' => $creditMissing, 'credit_granted' => false];
            }

            return $this->stop('ACTION_REQUIRED', [$credit['reason']], $creditMissing, $trace);
        }
        $collection = (string) $facts->value('rtc.collection_method');
        $collectionDecision = null;
        if ($materialInput119 && in_array($collection, ['SUPPLIER', 'ACQUIRER', 'SPLIT_PAYMENT'], true)) {
            $collectionReference = (new CollectionBasisReference119)->qualify($collection, $facts->value('rtc.collection_legal_basis'));
            $trace[] = ['stage' => 'COLLECTION_REFERENCE119', ...$collectionReference];
            if ($collectionReference['status'] !== 'RESOLVED') {
                return $this->stop($collectionReference['status'], [$collectionReference['reason']],
                    $collectionReference['reason'] === 'RTC_COLLECTION_BASIS_MISSING' ? ['rtc.collection_legal_basis'] : [], $trace);
            }
        }
        if ($this->transitions->qualifiesCollectionMethods()) {
            $collectionDecision = (new CollectionDecisionEngine)->resolve($facts, $market,
                $this->transitions->splitOperational($on, $knownAt));
            if ($collectionDecision['status'] !== 'RESOLVED') {
                return $this->stop($collectionDecision['status'], [$collectionDecision['reason']],
                    $collectionDecision['missing_facts'], $trace);
            }
            $trace[] = ['stage' => 'COLLECTION_ELIGIBILITY', 'source_article' => $collectionDecision['source_article'],
                'method' => $collectionDecision['method'], 'collector' => $collectionDecision['collector'],
                'extinction_status' => $collectionDecision['extinction_status']];
        } else {
            if ($collection !== 'SUPPLIER' && $facts->value('rtc.collection_legal_basis') === null) {
                return $this->stop('ACTION_REQUIRED', ['RTC_COLLECTION_BASIS_MISSING'], ['rtc.collection_legal_basis'], $trace);
            }
            if ($collection !== 'SUPPLIER') {
                return $this->stop('ACTION_REQUIRED', ['RTC_COLLECTION_STRATEGY_NOT_MATERIALIZED:'.$collection], [], $trace);
            }
        }
        $trace[] = ['stage' => 'CALCULATION', 'rounding' => 'HALF_UP_2', 'ibs_state' => $ibsState, 'ibs_municipal' => $ibsMunicipal, 'cbs' => $cbs];
        if ($institutionalImmunity === null && $date < '2027-01-01' && ! $facts->has('rtc.accessory_obligations_fulfilled')) {
            return $this->stop('ACTION_REQUIRED', ['RTC_2026_COLLECTION_CONDITION_UNKNOWN'], ['rtc.accessory_obligations_fulfilled'], $trace);
        }
        $dispensed = $date < '2027-01-01' && $facts->value('rtc.accessory_obligations_fulfilled') === true;
        $ibsTotal = DecimalValue::fromString($ibsState)->plus(DecimalValue::fromString($ibsMunicipal))->rounded(2)->value();
        $issuerFields = [
            'IBSCBS.finNFSe' => '0',
            'IBSCBS.indDest' => $facts->value('rtc.acquirer_id') === $facts->value('rtc.recipient_id') ? '0' : '1',
            'IBSCBS.cIndOp' => $code,
            'IBSCBS.valores.trib.gIBSCBS.CST' => $classRow['cst'],
            'IBSCBS.valores.trib.gIBSCBS.cClassTrib' => $class,
        ];
        if (isset($this->codes['dps_xsd'])) {
            if (DpsLayoutPolicy127::required($this->codes)) {
                $issuerFields = (new DpsLayoutPolicy127)->mapIssuerFields($issuerFields);
            }
            $validation = (new DpsIssuerFieldValidator)->validate($issuerFields, $layouts[0], $this->codes);
            $trace[] = ['stage' => 'DPS_ISSUER_XSD_VALIDATION', ...$validation];
            if ($validation['status'] !== 'VALID') {
                return $this->stop($validation['status'], [$validation['reason']], [], $trace);
            }
        }
        $documentObligation = null;
        if (isset($this->codes['document_obligations'])) {
            $documentObligation = (new DocumentObligationEngine)->resolve(
                (string) ($classificationRule['ctribnac_code'] ?? ''), $regime, $on, $knownAt, $this->codes,
                in_array($platformDecision['responsibility'] ?? null, ['FOREIGN_SUPPLIER_SUBSTITUTIVE_SOLIDARY', 'DOMESTIC_SUPPLIER_SOLIDARY', 'NOT_TRIGGERED'], true));
            $trace[] = ['stage' => 'DOCUMENT_OBLIGATION', ...$documentObligation];
            if ($documentObligation['status'] !== 'RESOLVED') {
                return $this->stop($documentObligation['status'], [$documentObligation['reason']], [], $trace);
            }
        }
        $decision = [
            'operation_date' => $date,
            'transition' => $this->transition($transitionRow),
            'legacy_taxes' => $this->legacy($transitionRow),
            'roles' => ['supplier' => $facts->value('rtc.supplier_id'), 'acquirer' => $facts->value('rtc.acquirer_id'),
                'recipient' => $facts->value('rtc.recipient_id'), 'importer' => $facts->value('rtc.importer_id'),
                'taxpayer' => $facts->value('rtc.taxpayer_id') ?? $facts->value('rtc.supplier_id'),
                'collector' => $collectionDecision['collector'] ?? $facts->value('rtc.supplier_id'),
                'buyer_relation' => $facts->value('rtc.buyer_relation'), 'delivery_mode' => $facts->value('rtc.delivery_mode')],
            'destination' => ['basis' => $basis, 'state' => $state, 'municipality_ibge' => $municipality, 'evidence_id' => $facts->value('rtc.destination_evidence_id')],
            'classification' => ['rule_id' => $classificationRuleId, 'rule_version' => $classificationRule['version'],
                'review_status' => $classificationRule['review_status'], 'source_kind' => 'ENGINE_DECISION_RULE',
                'cindop' => $code, 'cclasstrib' => $class],
            'base' => ['gross' => $gross->value(), 'adjustments' => $adjustments, 'taxable' => $base->value()],
            'ibs' => ['state_rate_percent' => $stateRow['percent'], 'municipal_rate_percent' => $municipalRow['percent'],
                'state_amount' => $ibsState, 'municipal_amount' => $ibsMunicipal, 'nominal_amount' => $ibsTotal,
                'cash_due' => $dispensed ? '0.00' : $ibsTotal, 'rate_sources' => [$stateRow['source_id'], $municipalRow['source_id']],
                'credit' => $credit['ibs']],
            'cbs' => ['federal_rate_percent' => $cbsRow['percent'], 'nominal_amount' => $cbs,
                'cash_due' => $dispensed ? '0.00' : $cbs, 'rate_source' => $cbsRow['source_id'], 'credit' => $credit['cbs']],
            'collection' => $collectionDecision === null
                ? ['method' => $collection, 'split_payment_status' => 'NOT_APPLICABLE']
                : ['method' => $collection, 'collector' => $collectionDecision['collector'],
                    'eligible_payer' => $collectionDecision['eligible_payer'] ?? null,
                    'source_article' => $collectionDecision['source_article'],
                    'eligibility_status' => 'RESOLVED', 'extinction_status' => $collectionDecision['extinction_status'],
                    'split_payment_status' => $collection === 'SPLIT_PAYMENT' ? 'ELIGIBLE_UNCONFIRMED' : 'NOT_APPLICABLE'],
            'document' => ['schema_version' => $layouts[0]['version'], 'schema_source_hash' => $layouts[0]['source_hash'],
                'issuer_fields' => $issuerFields,
                'national_enrichment' => [
                    'IBSCBS.valores.vBC' => $base->value(),
                    'IBSCBS.valores.uf.pIBSUF' => $stateRow['percent'],
                    'IBSCBS.valores.mun.pIBSMun' => $municipalRow['percent'],
                    'IBSCBS.valores.fed.pCBS' => $cbsRow['percent'],
                    'IBSCBS.totCIBS.gIBS.vIBSTot' => $ibsTotal,
                    'IBSCBS.totCIBS.gIBS.gIBSUFTot.vIBSUF' => $ibsState,
                    'IBSCBS.totCIBS.gIBS.gIBSMunTot.vIBSMun' => $ibsMunicipal,
                    'IBSCBS.totCIBS.gCBS.vCBS' => $cbs,
                ]],
            'catalog_versions' => ['rates' => $this->rates->metadata()['version'], 'codes' => $this->codes['version'] ?? null,
                'transition' => $this->transitions->version()],
        ];
        if ($platformDecision !== null) {
            $decision['platform_responsibility'] = $platformDecision;
        }
        if ($supplierCreditPeriod !== null) {
            $decision['supplier_credit_period'] = $supplierCreditPeriod;
        }
        if ($monthlyAssessment !== null) {
            $decision['monthly_assessment'] = $monthlyAssessment;
        }
        if ($financialOperation !== null) {
            $decision['financial_operation'] = $financialOperation;
        }
        if ($institutionalImmunity !== null) {
            $decision['treatment'] = $institutionalImmunity;
            $decision['ibs']['status'] = $decision['cbs']['status'] = 'IMMUNE';
            $decision['ibs']['state_rate_percent'] = $decision['ibs']['municipal_rate_percent'] = null;
            $decision['cbs']['federal_rate_percent'] = null;
            $decision['document']['national_enrichment'] = [];
            $decision['document']['enrichment_applicability'] = 'NOT_APPLICABLE_IMMUNITY';
        }
        if ($rateReduction !== null) {
            $decision['treatment'] = $rateReduction;
            if ($cooperativeZero !== null) {
                $decision['ibs']['status'] = $decision['cbs']['status'] = 'ZERO_RATE';
            }
            foreach ([['uf', 'pIBSUF', 'pRedAliqUF', 'pAliqEfetUF'],
                ['mun', 'pIBSMun', 'pRedAliqMun', 'pAliqEfetMun'],
                ['fed', 'pCBS', 'pRedAliqCBS', 'pAliqEfetCBS']] as $index => [$group, $standard, $reduction, $effective]) {
                $prefix = 'IBSCBS.valores.'.$group.'.';
                $decision['document']['national_enrichment'][$prefix.$effective] = [$stateRow['percent'], $municipalRow['percent'], $cbsRow['percent']][$index];
                $decision['document']['national_enrichment'][$prefix.$standard] = $standardRateRows[$index]['percent'];
                $decision['document']['national_enrichment'][$prefix.$reduction] = $rateReduction['rate_reduction_percent'];
            }
        }
        if ($documentObligation !== null) {
            $decision['document']['obligation'] = $documentObligation;
        }
        if (isset($classificationRule['nbs_prefixes'])) {
            $decision['classification']['nbs'] = $facts->value('rtc.service_nbs_code');
            $decision['classification']['nbs_evidence_id'] = $facts->value('rtc.service_nbs_evidence_id');
            if (($this->codes['project_service_nbs'] ?? false) === true) {
                $decision['document']['service_nbs'] = str_replace('.', '', (string) $facts->value('rtc.service_nbs_code'));
            }
        }
        if (isset($validation)) {
            $decision['document']['issuer_field_validation'] = $validation;
        }

        return ['status' => 'VALID', 'reason_codes' => [$cooperativeZero !== null ? 'RTC_COOPERATIVE_ZERO_RATE' : ($institutionalImmunity !== null ? 'RTC_INSTITUTIONAL_IMMUNITY'
            : ($dispensed ? 'RTC_2026_COLLECTION_DISPENSED'
            : ($professionalReduction !== null ? 'RTC_REDUCED_PROFESSIONAL_TAXATION'
                : ($annexReduction !== null ? 'RTC_REDUCED_ANNEX_SERVICE_TAXATION' : 'RTC_STANDARD_TAXATION'))))],
            'missing_facts' => [], 'trace' => $trace, 'decision' => $decision];
    }

    /** @param array<string, mixed> $rule */
    private function matchesNbsRule(array $rule, FactBag $facts): bool
    {
        if (! isset($rule['nbs_prefixes'])) {
            return true;
        }
        $nbs = $facts->value('rtc.service_nbs_code');
        if (! is_string($nbs) || ! $facts->has('rtc.service_nbs_evidence_id')) {
            return false;
        }
        if (! in_array($nbs, array_map(static fn (int|string $code): string => trim((string) $code), array_keys($this->codes['nbs'] ?? [])), true)) {
            return false;
        }
        if (isset($rule['annex_items']) && ! in_array($facts->value('rtc.annex_service_item'), $rule['annex_items'], true)) {
            return false;
        }
        foreach ($rule['nbs_prefixes'] as $prefix) {
            if (str_starts_with(str_replace('.', '', $nbs), str_replace('.', '', $prefix))) {
                return true;
            }
        }

        return false;
    }

    private function tax(DecimalValue $base, string $percent): string
    {
        return $base->multipliedBy(DecimalValue::fromString($percent))->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2)->value();
    }

    /** @return array<string, mixed>|null */
    private function cclassReference(string $class, LocalDate $on, DateTimeImmutable $knownAt): ?array
    {
        // Historical captured bundles do not acquire a new gate or trace stage retroactively.
        if (! array_key_exists('cclass_reference_policy', $this->codes)
            && ! array_key_exists('cst_reference_rows', $this->codes)
            && ($this->codes['sources']['cclasstrib']['version'] ?? null) !== 'IT2025.002 v1.70') {
            return null;
        }
        $policy = $this->codes['cclass_reference_policy'] ?? [];

        return (new CClassTribReference100)->resolve($this->codes, $class, 'NFSE', $on, $knownAt, is_array($policy) ? $policy : []);
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     * @return array<string, mixed>
     */
    private function donationNominal(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $trace): array
    {
        if ($facts->value('rtc.operation_kind') !== 'DIGITAL'
            || $facts->value('rtc.delivery_mode') !== 'DIGITAL'
            || $facts->value('service.canonical_id') !== 'SOFTWARE_DEVELOPMENT'
            || $facts->value('rtc.tax_treatment') !== 'STANDARD'
            || $facts->value('rtc.collection_method') !== 'SUPPLIER'
            || $facts->value('rtc.credit_context') !== 'OUTPUT'
            || $facts->value('rtc.platform_id') !== null
            || $facts->value('customer.person_type') === 'PUBLIC_ADMINISTRATION'
            || $facts->value('rtc.government_entity_type') !== null) {
            return $this->stop('ACTION_REQUIRED', ['RTC_DONATION_SPECIAL_STRATEGY_NOT_MATERIALIZED'], [], $trace);
        }
        if ($facts->value('rtc.acquirer_country') !== 'BR'
            || $facts->value('rtc.recipient_country') !== 'BR'
            || $facts->value('rtc.acquirer_id') !== $facts->value('rtc.recipient_id')
            || $facts->value('rtc.destination_basis') !== 'ACQUIRER_DOMICILE') {
            return $this->stop('ACTION_REQUIRED', ['RTC_DONATION_DESTINATION_ROLE_NOT_MATERIALIZED'], [], $trace);
        }
        $missing = $facts->missing(['rtc.destination_state', 'rtc.destination_municipality_ibge',
            'rtc.destination_evidence_id', 'rtc.destination_registration_status']);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', ['RTC_DONATION_DESTINATION_UNPROVEN'], $missing, $trace);
        }
        if ($facts->value('rtc.destination_registration_status') !== 'REGISTERED') {
            return $this->stop('ACTION_REQUIRED', ['RTC_DONATION_UNREGISTERED_DESTINATION_NOT_MATERIALIZED'], [], $trace);
        }
        $geographicStop = $this->geographicReference($facts, $on, $knownAt, 'DONATION_NOMINAL', $trace);
        if ($geographicStop !== null) {
            return $geographicStop;
        }
        if ($facts->value('rtc.legacy_tax_inclusion') !== 'EXCLUDED'
            || $facts->value('rtc.base_adjustments') !== []) {
            return $this->stop('ACTION_REQUIRED', ['RTC_DONATION_BASE_ADJUSTMENTS_NOT_MATERIALIZED'], [], $trace);
        }
        $date = $on->value();
        $state = $date < '2029-01-01' ? 'BR' : (string) $facts->value('rtc.destination_state');
        $municipality = $date < '2029-01-01' ? 'BR' : (string) $facts->value('rtc.destination_municipality_ibge');
        $stateRate = $this->rates->resolve('IBS_STATE', $state, $on, $knownAt);
        $municipalRate = $this->rates->resolve('IBS_MUNICIPAL', $municipality, $on, $knownAt);
        $cbsRate = $this->rates->resolve('CBS', 'BR', $on, $knownAt);
        foreach ([$stateRate, $municipalRate, $cbsRate] as $rate) {
            if ($rate['status'] !== 'RESOLVED') {
                return $this->stop($rate['status'] === 'CONFLICT' ? 'INVALID' : 'ACTION_REQUIRED', [$rate['reason']], [], $trace);
            }
        }
        if (! isset($stateRate['row'], $municipalRate['row'], $cbsRate['row'])) {
            return $this->stop('INVALID', ['RTC_RATE_CATALOG_CORRUPT'], [], $trace);
        }
        $base = DecimalValue::fromString((string) $facts->value('rtc.market_value_brl'))->rounded(2);
        $trace[] = ['stage' => 'DONATION_NOMINAL_CALCULATION', 'source_article' => 'LC214 art. 6º, §2º, I',
            'source_hash' => $this->transitions->sourceHash(), 'market_value_brl' => $base->value(),
            'market_value_evidence_id' => $facts->value('rtc.market_value_evidence_id'),
            'destination_state' => $facts->value('rtc.destination_state'),
            'destination_municipality_ibge' => $facts->value('rtc.destination_municipality_ibge'),
            'destination_evidence_id' => $facts->value('rtc.destination_evidence_id'),
            'ibs_state_nominal_brl' => $this->tax($base, (string) $stateRate['row']['percent']),
            'ibs_municipal_nominal_brl' => $this->tax($base, (string) $municipalRate['row']['percent']),
            'cbs_nominal_brl' => $this->tax($base, (string) $cbsRate['row']['percent']),
            'rate_sources' => [$stateRate['row']['source_id'], $municipalRate['row']['source_id'], $cbsRate['row']['source_id']]];

        return $this->stop('ACTION_REQUIRED', ['RTC_DONATION_DOCUMENT_OBLIGATION_NOT_MATERIALIZED'], [], $trace);
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     * @return array<string, mixed>
     */
    private function partialExportDomesticLeg(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt,
        DecimalValue $domesticGross, DecimalValue $foreignGross, array $trace): array
    {
        if ($domesticGross->compareTo(DecimalValue::fromString('0')) <= 0) {
            return $this->stop('INVALID', ['RTC_PARTIAL_EXPORT_DOMESTIC_AMOUNT_ZERO'], [], $trace);
        }
        $required = ['rtc.export_domestic_recipient_id', 'rtc.export_domestic_recipient_country',
            'rtc.destination_state', 'rtc.destination_municipality_ibge', 'rtc.destination_evidence_id'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_DOMESTIC_ROLE_UNPROVEN'], $missing, $trace);
        }
        if ($facts->value('rtc.export_domestic_recipient_country') !== 'BR'
            || $facts->value('rtc.export_domestic_recipient_id') === $facts->value('rtc.recipient_id')) {
            return $this->stop('INVALID', ['RTC_PARTIAL_EXPORT_DOMESTIC_RECIPIENT_CONFLICT'], [], $trace);
        }
        if ($facts->value('rtc.destination_basis') !== 'RECIPIENT_DOMICILE') {
            return $this->stop('INVALID', ['RTC_PARTIAL_EXPORT_DOMESTIC_DESTINATION_CONFLICT'], [], $trace);
        }
        $geographicStop = $this->geographicReference($facts, $on, $knownAt, 'PARTIAL_EXPORT_DOMESTIC_LEG', $trace);
        if ($geographicStop !== null) {
            return $geographicStop;
        }
        if ($facts->value('rtc.supplier_regime') !== 'REGULAR'
            || $facts->value('rtc.tax_treatment') !== 'STANDARD'
            || $facts->value('rtc.collection_method') !== 'SUPPLIER'
            || $facts->value('rtc.credit_context') !== 'OUTPUT') {
            return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_SPECIAL_TREATMENT_NOT_MATERIALIZED'], [], $trace);
        }
        if ($facts->value('rtc.legacy_tax_inclusion') !== 'EXCLUDED'
            || $facts->value('rtc.base_adjustments') !== []) {
            return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_ADJUSTMENT_ALLOCATION_UNPROVEN'], [], $trace);
        }
        $ruleId = 'RTC.CLASSIFICATION.SAAS.RECIPIENT_DESTINATION.SANDBOX-1';
        $rule = $this->codes['engine_decision_rules'][$ruleId] ?? null;
        $date = $on->value();
        if (! is_array($rule) || ($rule['market'] ?? null) !== 'DOMESTIC'
            || ($rule['canonical_service_id'] ?? null) !== $facts->value('service.canonical_id')
            || ($rule['destination_basis'] ?? null) !== 'RECIPIENT_DOMICILE'
            || ! isset($rule['valid_from'], $rule['published_at'], $rule['cindop'], $rule['cclasstrib'])
            || $date < $rule['valid_from'] || (isset($rule['valid_to']) && $date > $rule['valid_to'])
            || $knownAt < new DateTimeImmutable($rule['published_at'])) {
            return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_DOMESTIC_CLASSIFICATION_NOT_PROVEN'], [], $trace);
        }
        $code = (string) $rule['cindop'];
        $class = (string) $rule['cclasstrib'];
        if (CIndOpReference126::required($this->codes)) {
            $operationReference = (new CIndOpReference126)->resolve($this->codes, $code, 'LOCAL_SANDBOX', $on, $knownAt);
            $trace[] = ['stage' => 'CINDOP_REFERENCE126', 'context' => 'PARTIAL_EXPORT_DOMESTIC', ...$operationReference];
            if ($operationReference['status'] !== 'REFERENCE_ROW_AVAILABLE') {
                return $this->stop($operationReference['status'], [$operationReference['reason']], [], $trace);
            }
        }
        if (! isset($this->codes['cindop'][$code], $this->codes['cclasstrib'][$class])) {
            return $this->stop('INVALID', ['RTC_PARTIAL_EXPORT_DOMESTIC_CLASSIFICATION_INVALID'], [], $trace);
        }
        $reference = $this->cclassReference($class, $on, $knownAt);
        if ($reference !== null) {
            $trace[] = ['stage' => 'CCLASSTRIB_REFERENCE', 'context' => 'PARTIAL_EXPORT_DOMESTIC', ...$reference];
            if ($reference['status'] !== 'REFERENCE_ROW_AVAILABLE') {
                return $this->stop($reference['status'], [$reference['reason']], [], $trace);
            }
        }
        $jurisdiction = $date < '2029-01-01' ? 'BR' : (string) $facts->value('rtc.destination_state');
        $municipality = $date < '2029-01-01' ? 'BR' : (string) $facts->value('rtc.destination_municipality_ibge');
        $stateRate = $this->rates->resolve('IBS_STATE', $jurisdiction, $on, $knownAt);
        $municipalRate = $this->rates->resolve('IBS_MUNICIPAL', $municipality, $on, $knownAt);
        $cbsRate = $this->rates->resolve('CBS', 'BR', $on, $knownAt);
        foreach ([$stateRate, $municipalRate, $cbsRate] as $rate) {
            if ($rate['status'] !== 'RESOLVED') {
                return $this->stop($rate['status'] === 'CONFLICT' ? 'INVALID' : 'ACTION_REQUIRED', [$rate['reason']], [], $trace);
            }
        }
        if (! isset($stateRate['row'], $municipalRate['row'], $cbsRate['row'])) {
            return $this->stop('INVALID', ['RTC_RATE_CATALOG_CORRUPT'], [], $trace);
        }
        $trace[] = ['stage' => 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION',
            'source_article' => 'LC214 art. 80, §§1º-A e 5º; art. 11, X, a, 2',
            'source_hash' => $this->transitions->sourceHash(),
            'foreign_immune_gross_brl' => $foreignGross->value(),
            'domestic_taxable_base_brl' => $domesticGross->value(),
            'domestic_recipient_id' => $facts->value('rtc.export_domestic_recipient_id'),
            'domestic_recipient_country' => 'BR',
            'destination_state' => $facts->value('rtc.destination_state'),
            'destination_municipality_ibge' => $facts->value('rtc.destination_municipality_ibge'),
            'destination_evidence_id' => $facts->value('rtc.destination_evidence_id'),
            'classification_rule_id' => $ruleId,
            'classification_review_status' => $rule['review_status'] ?? null,
            'cindop' => $code, 'cclasstrib' => $class,
            'ibs_state_nominal_brl' => $this->tax($domesticGross, (string) $stateRate['row']['percent']),
            'ibs_municipal_nominal_brl' => $this->tax($domesticGross, (string) $municipalRate['row']['percent']),
            'cbs_nominal_brl' => $this->tax($domesticGross, (string) $cbsRate['row']['percent']),
            'rate_sources' => [$stateRate['row']['source_id'], $municipalRate['row']['source_id'], $cbsRate['row']['source_id']]];

        if ($facts->value('rtc.assess_export_document_prices') === true) {
            if (DocumentIssIntegration134::required($this->codes)) {
                $documentIss = (new DocumentIssIntegration134)->qualify($facts, $knownAt, $this->codes['document_iss_policy134']);
                $trace[] = ['stage' => 'PARTIAL_EXPORT_DOCUMENT_ISS134', ...$documentIss];
                if ($documentIss['status'] !== 'LOCAL_ISS_DOCUMENT_BASES_CALCULATED') {
                    return $this->stop($documentIss['status'], [$documentIss['reason']], $documentIss['missing_facts'], $trace);
                }

                return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'], [], $trace);
            }
            $policy = $this->codes['partial_export_document_prices_policy'] ?? [];
            $prices = (new PartialExportDocumentPrices112)->qualify($facts, $knownAt, is_array($policy) ? $policy : []);
            $trace = [...$trace, ...$prices['trace']];
            if ($prices['status'] !== 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED') {
                return $this->stop($prices['status'], [$prices['reason']], $prices['missing_facts'], $trace);
            }

            return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'], [], $trace);
        }

        if ($facts->value('rtc.assess_export_dated_documents') === true) {
            $missing = $facts->missing(['rtc.export_iss_material_records', 'rtc.export_iss_dated_result_records',
                'rtc.export_dated_dps_identity_records', 'provider.iss_regime', 'provider.iss_regime_proof_records']);
            if ($missing !== []) {
                return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_DATED108_MATERIAL_RESULT_IDENTITY_RECORDS_REQUIRED'], $missing, $trace);
            }
            $materialRecords = $facts->value('rtc.export_iss_material_records');
            $resultRecords = $facts->value('rtc.export_iss_dated_result_records');
            $identityRecords = $facts->value('rtc.export_dated_dps_identity_records');
            if (! is_array($materialRecords) || ! is_array($resultRecords) || ! is_array($identityRecords)) {
                return $this->stop('INVALID', ['RTC_EXPORT_DATED108_RECORD_ARRAYS_REQUIRED'], [], $trace);
            }
            $regimeProofs = $facts->value('provider.iss_regime_proof_records');
            if (! is_array($regimeProofs) || ! array_is_list($regimeProofs) || count($regimeProofs) !== 1 || ! is_array($regimeProofs[0])) {
                return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_DATED108_SINGLE_REGIME_PROOF_REQUIRED'], [], $trace);
            }
            $datedPolicy = $this->codes['partial_export_dated_documents_policy'] ?? [];
            $projection = DatedDocumentFactProjection108::from($facts);
            $dated = (new PartialExportDatedDpsIdentity107)->qualify($projection['facts'], $materialRecords,
                $resultRecords, $identityRecords, $knownAt->format('Y-m-d\TH:i:s.uP'), is_array($datedPolicy) ? $datedPolicy : []);
            unset($projection['facts']);
            $trace[] = ['stage' => 'PARTIAL_EXPORT_DATED_DOCUMENTS', ...$dated,
                ...$projection];
            if ($dated['status'] !== 'LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND') {
                return $this->stop($dated['status'], [$dated['reason']], [], $trace);
            }

            return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'], [], $trace);
        }

        if ($facts->value('rtc.assess_export_iss_results') === true) {
            $policy = $this->codes['partial_export_iss_result_policy'] ?? [];
            $issResults = (new CurrentPartialExportIss96)->qualify($facts->canonicalValues(), $on, $knownAt,
                is_array($policy) ? $policy : []);
            $trace[] = ['stage' => 'PARTIAL_EXPORT_ISS_RESULTS', ...$issResults];
            if ($issResults['status'] !== 'LOCAL_CURRENT_EXPORT_ISS_RESULTS_BOUND') {
                return $this->stop('ACTION_REQUIRED', [$issResults['reason']], $issResults['missing_facts'], $trace);
            }
            if ($facts->value('rtc.assess_export_sp_results') === true) {
                $municipalPolicy = $this->codes['partial_export_sp_result_policy'] ?? [];
                $spResults = (new CurrentPartialExportSp98)->qualify($facts->canonicalValues(), $on, $knownAt,
                    is_array($policy) ? $policy : [], is_array($municipalPolicy) ? $municipalPolicy : []);
                $trace[] = ['stage' => 'PARTIAL_EXPORT_SP_RESULTS', ...$spResults];
                if ($spResults['status'] !== 'LOCAL_CURRENT_SP_EXPORT_RESULTS_BOUND') {
                    return $this->stop('ACTION_REQUIRED', [$spResults['reason']], $spResults['missing_facts'], $trace);
                }
                if ($facts->value('rtc.assess_export_dps_identity') === true) {
                    $records = $facts->value('rtc.export_dps_identity_records');
                    if (! is_array($records)) {
                        return $this->stop('ACTION_REQUIRED', ['RTC_EXPORT_DPS104_IDENTITY_RECORDS_REQUIRED'],
                            ['rtc.export_dps_identity_records'], $trace);
                    }
                    $identityPolicy = $this->codes['partial_export_dps_identity_policy'] ?? [];
                    $identity = (new PartialExportDpsIdentity103)->qualify($facts->canonicalValues(), $records,
                        $knownAt->format('Y-m-d\TH:i:s.uP'), is_array($identityPolicy) ? $identityPolicy : []);
                    $trace[] = ['stage' => 'PARTIAL_EXPORT_DPS_IDENTITY', ...$identity];
                    if ($identity['status'] !== 'LOCAL_DPS_IDENTITY_PAIR_BOUND') {
                        return $this->stop($identity['status'], [$identity['reason']], [], $trace);
                    }
                }
            }
        }

        return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'],
            $facts->has('rtc.export_domestic_document_id') ? [] : ['rtc.export_domestic_document_id'], $trace);
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     * @return array<string, mixed>|null
     */
    private function geographicReference(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, string $context, array &$trace): ?array
    {
        // Bundle policy, not a caller opt-in: old captured bundles retain their original behavior.
        if (! array_key_exists('observed_geographic_reference', $this->codes)
            && ! array_key_exists('observed_geographic_reference_policy', $this->codes)
            && ! in_array($this->codes['version'] ?? null, ['2026.10.20-116-draft1', MaterialInputPolicy119::VERSION, CreditInputPolicy122::VERSION], true)) {
            return null;
        }
        $catalog = $this->codes['observed_geographic_reference'] ?? [];
        $policy = $this->codes['observed_geographic_reference_policy'] ?? [];
        $reference = (new ObservedGeographicReference115)->resolve(is_array($catalog) ? $catalog : [],
            (string) $facts->value('rtc.destination_municipality_ibge'), (string) $facts->value('rtc.destination_state'),
            $knownAt, is_array($policy) ? $policy : []);
        $trace[] = ['stage' => 'OBSERVED_GEOGRAPHIC_REFERENCE', 'context' => $context,
            'operation_date_not_reference_validity' => $on->value(), ...$reference];
        if ($reference['status'] !== 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT') {
            return $this->stop($reference['status'], [$reference['reason']], [], $trace);
        }

        return null;
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function transition(array $row): array
    {
        return ['phase' => $row['phase'], 'valid_from' => $row['valid_from'], 'valid_to' => $row['valid_to'],
            'source_article' => $row['source_article'], 'catalog_version' => $this->transitions->version()];
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function legacy(array $row): array
    {
        return ['iss_factor' => $row['iss_factor'], 'icms_factor' => $row['icms_factor'],
            'pis_cofins_status' => $row['pis_cofins_status'], 'iss_status' => $row['iss_status']];
    }

    /** @param list<string> $reasons
     * @param  list<string>  $missing
     * @param  list<array<string, mixed>>  $trace
     * @return array<string, mixed>
     */
    private function stop(string $status, array $reasons, array $missing, array $trace): array
    {
        return ['status' => $status, 'reason_codes' => $reasons, 'missing_facts' => array_values(array_unique($missing)), 'trace' => $trace, 'decision' => []];
    }
}
