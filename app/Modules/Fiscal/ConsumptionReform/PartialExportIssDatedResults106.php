<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\ISS\IssExportResultQualifier95;
use App\Modules\Fiscal\ISS\SpSoftwareExportResult97;
use DateTimeImmutable;

/** Compose fresh105/95/97 qualifications by document date. Never rewrite RTC facts or consume cached approvals. */
final readonly class PartialExportIssDatedResults106
{
    public const CONTRACT = 'ISS-PARTIAL-EXPORT-DATED-RESULT-COMPOSITION-2026.10.19-106';

    public const AVAILABLE_AT = '2026-10-19T16:23:41.850035+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT, 'material_policy' => PartialExportIssMaterialDate105::policy(),
            'national_policy' => ['contract' => IssExportResultQualifier95::CONTRACT,
                'source_sha256' => IssExportResultQualifier95::SOURCE_SHA256,
                'source_observed_at' => IssExportResultQualifier95::SOURCE_OBSERVED_AT,
                'scope' => 'LOCAL_SOFTWARE_RESULT_EVIDENCE_NOT_LEGAL_ACCEPTANCE'],
            'municipal_policy' => ['contract' => SpSoftwareExportResult97::CONTRACT,
                'source_sha256' => SpSoftwareExportResult97::SOURCE_SHA256,
                'source_observed_at' => SpSoftwareExportResult97::SOURCE_OBSERVED_AT,
                'scope' => 'LOCAL_SP_ITEM1_RESULT_NOT_LEGAL_ACCEPTANCE'],
            'scope' => 'FRESH_MATERIAL_AND_RESULT_BINDING_PER_ROLE_NOT_DPS_OR_TAX_APPROVAL',
            'legal_applicability_approved' => false, 'production_ready' => false];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<int|string, mixed>  $materialRecords
     * @param  array<int|string, mixed>  $resultRecords
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $facts, array $materialRecords, array $resultRecords, string $knowledgeAt, array $policy): array
    {
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())) {
            return $this->stop('COMPOSITION_POLICY_NOT_BOUND');
        }
        $material = (new PartialExportIssMaterialDate105)->qualify($facts, $materialRecords, $knowledgeAt, $policy['material_policy']);
        if (($material['material_date_evidence_locally_bound'] ?? false) !== true) {
            return [...$this->stop('MATERIAL_PAIR_NOT_BOUND', $material['status']), 'material_reason' => $material['reason']];
        }
        $known = new DateTimeImmutable($knowledgeAt); // Strictly validated by105 above.
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('COMPOSITION_NOT_YET_AVAILABLE');
        }
        if (! array_is_list($resultRecords) || count($resultRecords) !== 2) {
            return $this->stop('TWO_DATED_RESULT_RECORDS_REQUIRED');
        }
        $byRole = [];
        foreach ($resultRecords as $record) {
            if (! is_array($record) || ! $this->keys($record, ['role', 'iss_context', 'municipal_proof', 'event_binding'])
                || ! in_array($record['role'], ['RTC_FOREIGN', 'RTC_DOMESTIC'], true)
                || ! is_array($record['iss_context']) || ! is_array($record['municipal_proof']) || ! is_array($record['event_binding'])) {
                return $this->stop('EXACT_DATED_RECORD_CONTRACT_REQUIRED', 'INVALID');
            }
            if (array_key_exists($record['role'], $byRole)) {
                return $this->stop('DUPLICATE_RESULT_ROLE', 'INVALID');
            }
            $byRole[$record['role']] = $record;
        }
        $documents = [];
        $evidenceIds = [];
        $performanceByRole = [];
        foreach ($materialRecords as $record) {
            $performanceByRole[$record['role']] = $record['performance'];
        }
        foreach ($material['documents'] as $document) {
            $role = $document['role'];
            if (! isset($byRole[$role])) {
                return $this->stop('TWO_DATED_RESULT_RECORDS_REQUIRED');
            }
            $record = $byRole[$role];
            $context = $record['iss_context'];
            $expected = ['operation_id' => $facts['rtc.export_operation_reference'],
                'document_id' => $document['document_reference'], 'supplier_id' => $facts['rtc.supplier_id'],
                'acquirer_id' => $facts['rtc.acquirer_id'], 'recipient_id' => $document['recipient_reference'],
                'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'],
                'service_kind' => 'SOFTWARE_SERVICE', 'supplier_country' => 'BR', 'performed_in' => 'BR',
                'supply_on' => $document['iss_supply_on'], 'special_context' => false];
            foreach ($expected as $key => $value) {
                if (($context[$key] ?? null) !== $value) {
                    return [...$this->stop('MATERIAL_RESULT_CURRENT_CONTEXT_CONFLICT', 'INVALID'), 'role' => $role, 'field' => $key];
                }
            }
            $national = (new IssExportResultQualifier95)->qualify($context, $known, $policy['national_policy']);
            if (($national['independent_result_evidence_locally_bound'] ?? false) !== true) {
                return [...$this->stop('NATIONAL_RESULT_NOT_BOUND'), 'role' => $role, 'qualification_reason' => $national['reason']];
            }
            $municipal = (new SpSoftwareExportResult97)->qualify($context, $policy['national_policy'],
                $record['municipal_proof'], $policy['municipal_policy'], $known);
            if (($municipal['municipal_documentary_conditions_locally_bound'] ?? false) !== true) {
                return [...$this->stop('MUNICIPAL_RESULT_NOT_BOUND'), 'role' => $role, 'qualification_reason' => $municipal['reason']];
            }
            $performance = $performanceByRole[$role];
            $municipalValue = $record['municipal_proof']['value'];
            $monthly = $performance['mode'] === 'FULFILLED_MONTHLY_STAGE';
            if ($municipalValue['service_execution'] !== ($monthly ? 'CONTINUING_MONTHLY' : 'SINGLE')) {
                return [...$this->stop('MATERIAL_MUNICIPAL_EXECUTION_CONFLICT', 'INVALID'), 'role' => $role];
            }
            if ($monthly && ($municipalValue['monthly_stage']['stage_id'] !== $performance['unit_reference']
                || $municipalValue['monthly_stage']['completed_on'] !== $document['iss_supply_on'])) {
                return [...$this->stop('MATERIAL_MUNICIPAL_STAGE_CONFLICT', 'INVALID'), 'role' => $role];
            }
            $binding = $record['event_binding'];
            if (! $this->keys($binding, ['contract', 'kind', 'evidence_id', 'value', 'origin_content', 'origin_sha256', 'source_hash'])
                || $binding['contract'] !== self::CONTRACT || $binding['kind'] !== 'MATERIAL_RESULT_SAME_EVENT_BINDING'
                || ! $this->text($binding['evidence_id']) || ! is_array($binding['value'])
                || ! is_string($binding['origin_content']) || ! is_string($binding['origin_sha256']) || ! is_string($binding['source_hash'])) {
                return $this->stop('EXACT_EVENT_BINDING_PROOF_REQUIRED');
            }
            $expectedValue = ['operation_reference' => $facts['rtc.export_operation_reference'], 'role' => $role,
                'document_reference' => $document['document_reference'], 'supplier_id' => $facts['rtc.supplier_id'],
                'recipient_reference' => $document['recipient_reference'], 'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'],
                'rtc_operation_date' => $facts['rtc.operation_date'], 'iss_supply_on' => $document['iss_supply_on'],
                'contract_reference' => $performance['contract_reference'], 'unit_reference' => $performance['unit_reference'],
                'performed_at' => $document['performed_at'], 'performance_hash' => $document['performance_hash'],
                'material_proof_hash' => $document['proof_hash'], 'material_origin_hash' => $document['origin_content_sha256'],
                'national_context_hash' => $national['context_hash'], 'national_result_proof_hash' => $national['result_proof_hash'],
                'municipal_proof_hash' => $municipal['municipal_proof_hash']];
            if (CanonicalJson::hash($binding['value']) !== CanonicalJson::hash($expectedValue)) {
                return [...$this->stop('SAME_EVENT_VALUE_CONFLICT', 'INVALID'), 'role' => $role];
            }
            $content = ['contract' => self::CONTRACT, 'kind' => $binding['kind'],
                'evidence_id' => $binding['evidence_id'], 'value' => $expectedValue];
            if (! hash_equals(hash('sha256', $binding['origin_content']), $binding['origin_sha256'])
                || CanonicalJson::encode($content) !== $binding['origin_content']) {
                return $this->stop('SAME_EVENT_CAPTURED_CONTENT_CONFLICT', 'INVALID');
            }
            $body = $binding;
            unset($body['source_hash']);
            if (! hash_equals(CanonicalJson::hash($body), $binding['source_hash'])) {
                return $this->stop('SAME_EVENT_PROOF_HASH_CONFLICT', 'INVALID');
            }
            foreach ([$binding['evidence_id'], $national['result_evidence_id'], $record['municipal_proof']['evidence_id']] as $id) {
                if (in_array($id, $evidenceIds, true)) {
                    return $this->stop('DISTINCT_ROLE_RESULT_EVIDENCE_REQUIRED', 'INVALID');
                }
                $evidenceIds[] = $id;
            }
            $documents[] = ['role' => $role, 'document_reference' => $document['document_reference'],
                'iss_supply_on' => $document['iss_supply_on'], 'material_evidence' => $document,
                'national_result' => $national, 'municipal_result' => $municipal,
                'same_event_binding_hash' => $binding['source_hash']];
        }

        return ['status' => 'LOCAL_DATED_ISS_RESULTS_AND_MATERIAL_PAIR_BOUND',
            'reason' => 'ISS_DATED106_CURRENT_PER_DOCUMENT_EVIDENCE_NOT_DPS_OR_TAX_APPROVAL',
            'dated_material_and_results_locally_bound' => true, 'documents' => $documents,
            'rtc_operation_date' => $facts['rtc.operation_date'], 'current_facts_hash' => CanonicalJson::hash($facts),
            'material_binding_hash' => $material['binding_hash'], 'policy_hash' => CanonicalJson::hash($policy),
            'binding_hash' => CanonicalJson::hash(['facts' => $facts, 'material_records' => $materialRecords,
                'result_records' => $resultRecords, 'policy' => $policy, 'knowledge_at' => $knowledgeAt]),
            'cached_qualification_used' => false, 'rtc_facts_rewritten' => false, 'original_references_preserved' => true,
            ...$this->limits()];
    }

    /** @param array<string, mixed> $value
     * @param  list<string>  $keys
     */
    private function keys(array $value, array $keys): bool
    {
        return count($value) === count($keys) && array_diff($keys, array_keys($value)) === [];
    }

    private function text(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && ! str_contains($value, "\0");
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['iss_supply_date_materially_qualified' => false, 'legal_applicability_approved' => false,
            'source_effective_date_proven' => false, 'government_evidence_authenticated' => false,
            'economic_performance_authenticated' => false, 'dps_competence_requalified' => false,
            'fiscal_document_identifiers_qualified' => false, 'municipal_authorization_verified' => false,
            'documentary_currency_conversion_qualified' => false, 'per_document_tax_amount_calculated' => false,
            'xml_generated' => false, 'document_obligation_complete' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'ISS_DATED106_'.$reason, 'dated_material_and_results_locally_bound' => false,
            'original_references_preserved' => true, 'rtc_facts_rewritten' => false, 'cached_qualification_used' => false, ...$this->limits()];
    }
}
