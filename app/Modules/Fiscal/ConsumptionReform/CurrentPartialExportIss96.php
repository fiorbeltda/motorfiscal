<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\ISS\IssExportResultQualifier95;
use DateTimeImmutable;

/** Local ISS proof binding inside an already evaluated RTC partial-export branch; not an archived-snapshot replay. */
final readonly class CurrentPartialExportIss96
{
    public const CONTRACT = 'ISS-CURRENT-PARTIAL-EXPORT-CONTEXT-2026.10.16-96';

    /**
     * The RTC engine calls this only after its native FX, allocation, classification and nominal-rate guards.
     * This helper itself qualifies ISS context links, not those earlier RTC stages or economic authenticity.
     *
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $facts, LocalDate $on, DateTimeImmutable $knownAt, array $policy): array
    {
        if (($policy['current_binding_contract'] ?? null) !== self::CONTRACT
            || ($policy['contract'] ?? null) !== IssExportResultQualifier95::CONTRACT
            || ($policy['source_sha256'] ?? null) !== IssExportResultQualifier95::SOURCE_SHA256
            || ($policy['source_observed_at'] ?? null) !== IssExportResultQualifier95::SOURCE_OBSERVED_AT
            || ($policy['scope'] ?? null) !== 'LOCAL_SOFTWARE_RESULT_EVIDENCE_NOT_LEGAL_ACCEPTANCE'
            || ($policy['source_effective_date_proven'] ?? null) !== false) {
            return $this->stop('POLICY_NOT_BOUND');
        }
        if ($knownAt < new DateTimeImmutable(IssExportResultQualifier95::SOURCE_OBSERVED_AT)) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }
        if ($on->value() < '2026-01-01' || $on->value() > '2032-12-31') {
            return $this->stop('LOCAL_PERIOD_NOT_COVERED');
        }
        if (($facts['service.canonical_id'] ?? null) !== 'SOFTWARE_DEVELOPMENT'
            || ($facts['rtc.market'] ?? null) !== 'EXPORT' || ($facts['rtc.operation_kind'] ?? null) !== 'DIGITAL'
            || ($facts['rtc.supplier_country'] ?? null) !== 'BR') {
            return $this->stop('CURRENT_SOFTWARE_PARTIAL_EXPORT_REQUIRED');
        }
        $missing = [];
        foreach (['rtc.export_operation_reference', 'rtc.document_id', 'rtc.export_foreign_document_id',
            'rtc.export_domestic_document_id', 'rtc.supplier_id', 'rtc.acquirer_id', 'rtc.recipient_id',
            'rtc.export_domestic_recipient_id', 'rtc.export_transaction_evidence_id'] as $key) {
            if (! is_string($facts[$key] ?? null) || trim($facts[$key]) === '' || str_contains($facts[$key], "\0")) {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            return $this->stop('OPERATION_DOCUMENT_PARTIES_REQUIRED', $missing);
        }
        if ($facts['rtc.export_foreign_document_id'] === $facts['rtc.export_domestic_document_id']) {
            return $this->stop('TWO_DOCUMENT_REFERENCES_MUST_DIFFER');
        }
        $records = $facts['rtc.export_iss_result_contexts'] ?? null;
        if (! is_array($records) || ! array_is_list($records) || count($records) !== 2) {
            return $this->stop('TWO_INDEPENDENT_RESULT_CONTEXTS_REQUIRED', ['rtc.export_iss_result_contexts']);
        }
        $byRole = [];
        foreach ($records as $record) {
            if (! is_array($record) || count($record) !== 2 || ! array_key_exists('role', $record)
                || ! array_key_exists('iss_context', $record) || ! is_array($record['iss_context'])
                || ! in_array($record['role'], ['RTC_FOREIGN', 'RTC_DOMESTIC'], true)) {
                return $this->stop('EXACT_ROLE_CONTEXT_CONTRACT_REQUIRED');
            }
            if (isset($byRole[$record['role']])) {
                return $this->stop('DUPLICATE_RESULT_ROLE');
            }
            $byRole[$record['role']] = $record['iss_context'];
        }
        if (! isset($byRole['RTC_FOREIGN'], $byRole['RTC_DOMESTIC'])) {
            return $this->stop('TWO_INDEPENDENT_RESULT_CONTEXTS_REQUIRED');
        }
        $documents = [];
        foreach (['RTC_FOREIGN', 'RTC_DOMESTIC'] as $role) {
            $context = $byRole[$role];
            $documentKey = $role === 'RTC_FOREIGN' ? 'rtc.export_foreign_document_id' : 'rtc.export_domestic_document_id';
            $recipientKey = $role === 'RTC_FOREIGN' ? 'rtc.recipient_id' : 'rtc.export_domestic_recipient_id';
            $expected = ['operation_id' => $facts['rtc.export_operation_reference'], 'document_id' => $facts[$documentKey],
                'supplier_id' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id'],
                'recipient_id' => $facts[$recipientKey], 'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'],
                'service_kind' => 'SOFTWARE_SERVICE', 'supply_on' => $on->value(), 'supplier_country' => 'BR'];
            foreach ($expected as $key => $value) {
                if (($context[$key] ?? null) !== $value) {
                    return [...$this->stop('RESULT_CONTEXT_SOURCE_BINDING_CONFLICT'), 'role' => $role, 'field' => $key];
                }
            }
            $qualified = (new IssExportResultQualifier95)->qualify($context, $knownAt, $policy);
            if (($qualified['independent_result_evidence_locally_bound'] ?? false) !== true) {
                return [...$this->stop('INDEPENDENT_ISS_RESULT_NOT_BOUND'), 'role' => $role,
                    'qualification_reason' => $qualified['reason']];
            }
            $documents[] = ['role' => $role, 'document_reference' => $facts[$documentKey],
                'recipient_reference' => $facts[$recipientKey], 'iss_result' => $qualified];
        }

        return ['status' => 'LOCAL_CURRENT_EXPORT_ISS_RESULTS_BOUND',
            'reason' => 'RTC_EXPORT_ISS96_INDEPENDENT_RESULTS_BOUND_NOT_DOCUMENTS', 'missing_facts' => [],
            'source_mode' => 'CURRENT_FACTS_AFTER_ENGINE_PARTIAL_EXPORT_NOMINAL_STAGE',
            'operation_reference' => $facts['rtc.export_operation_reference'], 'documents' => $documents,
            'binding_hash' => CanonicalJson::hash(['operation_reference' => $facts['rtc.export_operation_reference'],
                'on' => $on->value(), 'documents' => $documents, 'policy' => $policy]),
            'current_facts_hash' => CanonicalJson::hash($facts), 'policy_hash' => CanonicalJson::hash($policy),
            'archive_snapshot_replayed' => false, ...$this->limits()];
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['economic_operation_identity_authenticated' => false, 'fiscal_document_identifiers_qualified' => false,
            'iss_supply_date_materially_qualified' => false, 'documentary_currency_conversion_qualified' => false,
            'per_document_tax_amount_calculated' => false, 'full_operation_iss_rewritten' => false,
            'source_effective_date_proven' => false, 'legal_applicability_approved' => false,
            'xml_generated' => false, 'document_obligation_complete' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $reason, array $missing = []): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_EXPORT_ISS96_'.$reason,
            'missing_facts' => $missing, ...$this->limits()];
    }
}
