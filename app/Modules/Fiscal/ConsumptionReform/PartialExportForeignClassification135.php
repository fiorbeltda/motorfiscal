<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/** Staged next integration: qualify the foreign RTC leg without copying a domestic indicator. */
final readonly class PartialExportForeignClassification135
{
    public const AVAILABLE_AT = '2026-10-22T19:02:10+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => 'PARTIAL-EXPORT-FOREIGN-CLASSIFICATION-135-V1', 'available_at' => self::AVAILABLE_AT,
            'layout_sha256' => '103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39',
            'layout_locator' => 'RN DPS_NFS-e!D697:Q698', 'layout_rules' => ['E0859', 'E0860'],
            'lc214_source_sha256' => '027797fa2a1f5ce9ebe34c7236766be4ef850127beb510eedb3b8315cf18d6ed',
            'legal_basis' => ['LC214_ART80_II', 'LC214_ART80_PAR5'],
            'cclass_source_sha256' => CClassTribReference100::SOURCE_SHA256,
            'cclass_code' => '410027', 'cst' => '410', 'ind_gIBSCBS' => 0,
            'cindop' => null, 'cindop_occurrence' => 'FORBIDDEN_WHEN_IND_GIBSCBS_ZERO',
            'scope' => 'LOCAL_FOREIGN_LEG_CLASSIFICATION_NOT_COMPLETE_DPS_OR_FULFILLMENT'];
    }

    /** Receives an assessment freshly produced in the calling engine; not a cached qualification flag.
     * @param  array<string, mixed>  $freshRtc
     * @param  array<string, mixed>  $codes
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(FactBag $facts, array $freshRtc, array $codes, LocalDate $on, DateTimeImmutable $known, array $policy): array
    {
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())) {
            return $this->stop('EXACT_SOURCE_POLICY_REQUIRED');
        }
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('SOURCE_NOT_YET_KNOWN');
        }
        $required = ['rtc.operation_date', 'rtc.supplier_id', 'rtc.acquirer_id', 'rtc.recipient_id',
            'rtc.export_foreign_document_id', 'rtc.export_domestic_document_id', 'rtc.export_evidence_id',
            'rtc.export_allocation_evidence_id', 'rtc.domestic_consumption_evidence_id', 'rtc.export_share_percent'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return [...$this->stop('FOREIGN_LEG_EVIDENCE_REQUIRED'), 'missing_facts' => $missing];
        }
        foreach (['rtc.market' => 'EXPORT', 'rtc.operation_kind' => 'DIGITAL', 'rtc.delivery_mode' => 'DIGITAL',
            'rtc.consideration' => 'ONEROUS', 'rtc.supplier_country' => 'BR', 'rtc.supplier_regime' => 'REGULAR',
            'service.canonical_id' => 'SOFTWARE_DEVELOPMENT', 'rtc.foreign_consumption_proven' => true] as $key => $value) {
            if ($facts->value($key) !== $value) {
                return $this->stop('EXPLICIT_QUALIFIED_PARTIAL_EXPORT_REQUIRED');
            }
        }
        if ($facts->value('rtc.acquirer_country') === 'BR' || $facts->value('rtc.recipient_country') === 'BR'
            || ! is_string($facts->value('rtc.acquirer_country')) || ! is_string($facts->value('rtc.recipient_country'))
            || $facts->value('rtc.export_foreign_document_id') === $facts->value('rtc.export_domestic_document_id')
            || $facts->value('rtc.operation_date') !== $on->value()) {
            return $this->stop('FOREIGN_ROLES_OR_DOCUMENT_CONFLICT');
        }
        foreach (array_diff($required, ['rtc.operation_date', 'rtc.export_share_percent']) as $key) {
            if (! is_string($facts->value($key)) || trim($facts->value($key)) === '') {
                return $this->stop('EXPLICIT_FOREIGN_CONTEXT_REQUIRED');
            }
        }
        $shareText = $facts->value('rtc.export_share_percent');
        if (! is_string($shareText) || preg_match('/\A[0-9]{1,3}(?:\.[0-9]{1,8})?\z/', $shareText) !== 1) {
            return $this->stop('PARTIAL_SHARE_REQUIRED');
        }
        $share = DecimalValue::fromString($shareText);
        if ($share->compareTo(DecimalValue::fromString('0')) <= 0 || $share->compareTo(DecimalValue::fromString('100')) >= 0) {
            return $this->stop('PARTIAL_SHARE_REQUIRED');
        }
        $stages = [];
        foreach ($freshRtc['trace'] ?? [] as $stage) {
            $name = $stage['stage'] ?? null;
            if (in_array($name, ['PARTIAL_EXPORT_GROSS_ALLOCATION', 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION', 'PARTIAL_EXPORT_FX_TRANSACTION'], true)) {
                if (isset($stages[$name])) {
                    return $this->stop('AMBIGUOUS_FRESH_ALLOCATION');
                }
                $stages[$name] = $stage;
            }
        }
        $allocation = $stages['PARTIAL_EXPORT_GROSS_ALLOCATION'] ?? null;
        $nominal = $stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'] ?? null;
        if (! is_array($allocation) || ! is_array($nominal)
            || ($allocation['allocation_evidence_id'] ?? null) !== $facts->value('rtc.export_allocation_evidence_id')
            || ($allocation['domestic_consumption_evidence_id'] ?? null) !== $facts->value('rtc.domestic_consumption_evidence_id')) {
            return $this->stop('FRESH_PARTIAL_ALLOCATION_REQUIRED');
        }
        $gross = $facts->value('rtc.gross_amount');
        if ($facts->value('rtc.gross_currency') !== 'BRL') {
            $fx = $stages['PARTIAL_EXPORT_FX_TRANSACTION'] ?? [];
            if (($fx['status'] ?? null) !== 'LOCAL_PARTIAL_EXPORT_FX_TRANSACTION_BOUND') {
                return $this->stop('FRESH_RTC_FX_REQUIRED');
            }
            $gross = $fx['converted_gross_brl'] ?? null;
        }
        if (! is_string($gross) || preg_match('/\A[0-9]+\.[0-9]{2}\z/', $gross) !== 1
            || ($allocation['total_gross_brl'] ?? null) !== $gross) {
            return $this->stop('CURRENT_GROSS_ALLOCATION_CONFLICT');
        }
        $foreign = DecimalValue::fromString($gross)->multipliedBy($share)->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2);
        $domestic = DecimalValue::fromString($gross)->minus($foreign)->rounded(2);
        if ($foreign->compareTo(DecimalValue::fromString('0')) <= 0 || $domestic->compareTo(DecimalValue::fromString('0')) <= 0
            || ($allocation['foreign_gross_brl'] ?? null) !== $foreign->value()
            || ($allocation['domestic_gross_brl'] ?? null) !== $domestic->value()
            || ($nominal['foreign_immune_gross_brl'] ?? null) !== $foreign->value()
            || ($nominal['domestic_taxable_base_brl'] ?? null) !== $domestic->value()) {
            return $this->stop('CURRENT_PARTIAL_AMOUNTS_CONFLICT');
        }
        $reference = (new CClassTribReference100)->resolve($codes, '410027', 'NFSE', $on, $known, $codes['cclass_reference_policy'] ?? []);
        if ($reference['status'] !== 'REFERENCE_ROW_AVAILABLE'
            || ($codes['cclasstrib']['410027']['cst'] ?? null) !== '410'
            || ($codes['cclasstrib']['410027']['legal_reference'] ?? null) !== 'Art. 80 II'
            || ($codes['cst_reference_rows']['410']['ind_gIBSCBS'] ?? null) !== 0) {
            return [...$this->stop('OFFICIAL_CLASSIFICATION_REFERENCE_REQUIRED'), 'reference' => $reference];
        }

        return ['status' => 'LOCAL_FOREIGN_EXPORT_CLASSIFICATION_QUALIFIED', 'document_reference' => $facts->value('rtc.export_foreign_document_id'),
            'cst' => '410', 'cclasstrib' => '410027', 'cindop' => null, 'ind_gIBSCBS' => 0,
            'cindop_occurrence' => 'FORBIDDEN_WHEN_IND_GIBSCBS_ZERO', 'foreign_immune_gross_brl' => $foreign->value(),
            'credit_treatment' => 'PRESERVED_SUBJECT_TO_PROOF', 'reference' => $reference,
            'policy_hash' => CanonicalJson::hash($policy), 'facts_hash' => CanonicalJson::hash($facts->canonicalValues()),
            'allocation_hash' => CanonicalJson::hash($allocation), 'nominal_hash' => CanonicalJson::hash($nominal),
            'binding_hash' => CanonicalJson::hash(['facts' => $facts->canonicalValues(), 'allocation' => $allocation,
                'nominal' => $nominal, 'policy' => $policy, 'known_at' => $known->format('Y-m-d\TH:i:s.uP')]),
            'iss_treatment_inferred' => false, 'legal_applicability_approved' => false,
            'remote_deployment_proven' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_EXPORT135_'.$reason,
            'document_obligation_complete' => false, 'production_ready' => false];
    }
}
