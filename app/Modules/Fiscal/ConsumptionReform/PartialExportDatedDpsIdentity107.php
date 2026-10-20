<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Local composition and operation/reference binding; not cadastral validity or issuance authorization. */
final readonly class PartialExportDatedDpsIdentity107
{
    public const CONTRACT = 'DPS-PARTIAL-EXPORT-DATED-IDENTITY-2026.10.19-107';

    public const AVAILABLE_AT = '2026-10-19T16:28:51.340690+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'identity_source_policy' => PartialExportDpsIdentity103::policy(),
            'dated_results_policy' => PartialExportIssDatedResults106::policy(),
            'scope' => 'FRESH_DATED_MATERIAL_RESULTS_AND_IDENTITY_PAIR_NOT_LEGAL_ISSUANCE',
            'remote_deployment_proven' => false, 'source_effective_date_proven' => false];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<int|string, mixed>  $materialRecords
     * @param  array<int|string, mixed>  $resultRecords
     * @param  array<int|string, mixed>  $records
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $facts, array $materialRecords, array $resultRecords, array $records, string $knowledgeAt, array $policy): array
    {
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())) {
            return $this->stop('SOURCE_POLICY_NOT_BOUND');
        }
        $known = $this->timestamp($knowledgeAt);
        if ($known === null) {
            return $this->stop('EXACT_KNOWLEDGE_TIMESTAMP_REQUIRED', 'INVALID');
        }
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }
        $dated = (new PartialExportIssDatedResults106)->qualify($facts, $materialRecords, $resultRecords,
            $knowledgeAt, $policy['dated_results_policy']);
        if (($dated['dated_material_and_results_locally_bound'] ?? false) !== true) {
            return [...$this->stop('DATED_MATERIAL_RESULTS_NOT_BOUND', $dated['status']), 'dated_reason' => $dated['reason']];
        }
        $datedByRole = [];
        foreach ($dated['documents'] as $document) {
            $datedByRole[$document['role']] = $document;
        }
        $materialByRole = [];
        foreach ($materialRecords as $materialRecord) {
            $materialByRole[$materialRecord['role']] = $materialRecord;
        }
        $resultByRole = [];
        foreach ($resultRecords as $resultRecord) {
            $resultByRole[$resultRecord['role']] = $resultRecord;
        }
        foreach (['service.canonical_id' => 'SOFTWARE_DEVELOPMENT', 'rtc.market' => 'EXPORT',
            'rtc.operation_kind' => 'DIGITAL', 'rtc.supplier_country' => 'BR',
            'provider.person_type' => 'PRIVATE_LEGAL_ENTITY'] as $key => $value) {
            if (($facts[$key] ?? null) !== $value) {
                return $this->stop('CURRENT_PARTIAL_EXPORT_SCOPE_REQUIRED');
            }
        }
        $share = $facts['rtc.export_share_percent'] ?? null;
        if (! is_string($share) || preg_match('/\A[0-9]{1,3}(?:\.[0-9]{1,8})?\z/', $share) !== 1
            || DecimalValue::fromString($share)->compareTo(DecimalValue::fromString('0')) <= 0
            || DecimalValue::fromString($share)->compareTo(DecimalValue::fromString('100')) >= 0) {
            return $this->stop('CURRENT_PARTIAL_EXPORT_SCOPE_REQUIRED');
        }
        foreach (['rtc.export_operation_reference', 'rtc.export_foreign_document_id', 'rtc.export_domestic_document_id',
            'rtc.supplier_id', 'provider.municipality_ibge', 'rtc.export_transaction_evidence_id', 'rtc.operation_date'] as $key) {
            if (! $this->text($facts[$key] ?? null)) {
                return [...$this->stop('CURRENT_REFERENCE_FACT_REQUIRED'), 'field' => $key];
            }
        }
        if (! $this->date($facts['rtc.operation_date'])
            || preg_match('/\A[0-9]{14}\z/', $facts['rtc.supplier_id']) !== 1
            || preg_match('/\A[0-9]{7}\z/', $facts['provider.municipality_ibge']) !== 1) {
            return $this->stop('CURRENT_FORMAT_SCOPE_REQUIRED', 'INVALID');
        }
        if ($facts['rtc.export_foreign_document_id'] === $facts['rtc.export_domestic_document_id']) {
            return $this->stop('DISTINCT_ORIGINAL_REFERENCES_REQUIRED', 'INVALID');
        }
        if (! array_is_list($records) || count($records) !== 2) {
            return $this->stop('TWO_IDENTITY_RECORDS_REQUIRED');
        }
        $byRole = [];
        foreach ($records as $record) {
            if (! is_array($record) || ! $this->keys($record, ['role', 'document_reference', 'identity', 'proof'])
                || ! in_array($record['role'], ['RTC_FOREIGN', 'RTC_DOMESTIC'], true)
                || ! is_array($record['identity']) || ! is_array($record['proof'])) {
                return $this->stop('EXACT_RECORD_CONTRACT_REQUIRED', 'INVALID');
            }
            if (array_key_exists($record['role'], $byRole)) {
                return $this->stop('DUPLICATE_ROLE', 'INVALID');
            }
            $byRole[$record['role']] = $record;
        }
        if (! isset($byRole['RTC_FOREIGN'], $byRole['RTC_DOMESTIC'])) {
            return $this->stop('TWO_IDENTITY_RECORDS_REQUIRED');
        }
        $documents = [];
        $mappingEvidenceIds = [];
        foreach (['RTC_FOREIGN', 'RTC_DOMESTIC'] as $role) {
            $record = $byRole[$role];
            $ref = $facts[$role === 'RTC_FOREIGN' ? 'rtc.export_foreign_document_id' : 'rtc.export_domestic_document_id'];
            $id = $record['identity'];
            if ($record['document_reference'] !== $ref) {
                return $this->stop('ORIGINAL_DOCUMENT_REFERENCE_CONFLICT', 'INVALID');
            }
            if (! $this->keys($id, ['model', 'environment', 'emission_channel', 'emitter_type', 'registration_type',
                'supplier_id', 'issuer_municipality', 'series', 'number', 'id', 'issued_at', 'competence_date'])) {
                return $this->stop('EXACT_IDENTITY_CONTRACT_REQUIRED', 'INVALID');
            }
            foreach (['model' => 'DPS', 'environment' => '2', 'emission_channel' => 'OWN_APPLICATION',
                'emitter_type' => '1', 'registration_type' => '2'] as $key => $value) {
                if ($id[$key] !== $value) {
                    return [...$this->stop('EXPLICIT_LOCAL_EMITTER_CHANNEL_ENVIRONMENT_REQUIRED'), 'field' => $key];
                }
            }
            if ($id['supplier_id'] !== $facts['rtc.supplier_id'] || $id['issuer_municipality'] !== $facts['provider.municipality_ibge']) {
                return $this->stop('CURRENT_SUPPLIER_MUNICIPALITY_CONFLICT', 'INVALID');
            }
            if (! is_string($id['series']) || preg_match('/\A[0-9]{1,5}\z/', $id['series']) !== 1
                || (int) $id['series'] < 1 || (int) $id['series'] > 49999) {
                return $this->stop('SERIES_OUTSIDE_OWN_APPLICATION_RANGE', 'INVALID');
            }
            if (! is_string($id['number']) || preg_match('/\A[1-9][0-9]{0,14}\z/', $id['number']) !== 1) {
                return $this->stop('EXACT_NUMBER_REQUIRED', 'INVALID');
            }
            $composed = 'DPS'.$id['issuer_municipality'].'2'.$id['supplier_id']
                .str_pad($id['series'], 5, '0', STR_PAD_LEFT).str_pad($id['number'], 15, '0', STR_PAD_LEFT);
            if ($id['id'] !== $composed) {
                return $this->stop('ID_COMPONENTS_CONFLICT', 'INVALID');
            }
            $issued = $this->timestamp($id['issued_at']);
            if ($issued === null || ! $this->date($id['competence_date'])) {
                return $this->stop('EXACT_DECLARED_DATES_REQUIRED', 'INVALID');
            }
            if ($issued > $known || $issued < new DateTimeImmutable(PartialExportDpsIdentity103::AVAILABLE_AT)) {
                return $this->stop('ISSUANCE_OUTSIDE_LOCAL_KNOWLEDGE_SCOPE');
            }
            $datedDocument = $datedByRole[$role];
            if ($id['competence_date'] !== $datedDocument['iss_supply_on']
                || $id['competence_date'] > $issued->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d')) {
                return $this->stop('PER_DOCUMENT_ISS_COMPETENCE_CONFLICT', 'INVALID');
            }
            $performed = new DateTimeImmutable($datedDocument['material_evidence']['performed_at']);
            if ($issued < $performed) {
                return $this->stop('ISSUANCE_PRECEDES_MATERIAL_EVENT', 'INVALID');
            }
            $proof = $record['proof'];
            if (! $this->keys($proof, ['contract', 'operation_reference', 'document_reference', 'role', 'transaction_evidence_id',
                'supplier_id', 'issuer_municipality', 'identity_hash', 'dated_pair_binding_hash', 'dated_document_hash',
                'observed_at', 'evidence_id', 'source_hash'])) {
                return $this->stop('EXACT_MAPPING_PROOF_REQUIRED');
            }
            $observed = $this->timestamp($proof['observed_at']);
            if ($observed === null || $observed < $issued || $observed > $known || $observed < new DateTimeImmutable(self::AVAILABLE_AT)) {
                return $this->stop('MAPPING_PROOF_KNOWLEDGE_CONFLICT');
            }
            foreach ([$materialByRole[$role]['proof']['observed_at'],
                $resultByRole[$role]['iss_context']['result_proof']['observed_at'],
                $resultByRole[$role]['municipal_proof']['observed_at']] as $sourceObserved) {
                if ($observed < new DateTimeImmutable($sourceObserved)) {
                    return $this->stop('MAPPING_PRECEDES_DATED_SOURCE_OBSERVATION');
                }
            }
            $expected = ['contract' => self::CONTRACT, 'operation_reference' => $facts['rtc.export_operation_reference'],
                'document_reference' => $ref, 'role' => $role, 'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'],
                'supplier_id' => $id['supplier_id'], 'issuer_municipality' => $id['issuer_municipality'], 'identity_hash' => CanonicalJson::hash($id),
                'dated_pair_binding_hash' => $dated['binding_hash'], 'dated_document_hash' => CanonicalJson::hash($datedDocument)];
            foreach ($expected as $key => $value) {
                if ($proof[$key] !== $value) {
                    return [...$this->stop('MAPPING_PROOF_BINDING_CONFLICT', 'INVALID'), 'field' => $key];
                }
            }
            if (! $this->text($proof['evidence_id']) || ! is_string($proof['source_hash'])
                || ! hash_equals(CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true])), $proof['source_hash'])) {
                return $this->stop('MAPPING_PROOF_HASH_CONFLICT', 'INVALID');
            }
            if (in_array($proof['evidence_id'], $mappingEvidenceIds, true)) {
                return $this->stop('DISTINCT_MAPPING_EVIDENCE_REQUIRED', 'INVALID');
            }
            $mappingEvidenceIds[] = $proof['evidence_id'];
            $documents[] = ['role' => $role, 'document_reference' => $ref, 'dps_id' => $composed,
                'identity' => $id, 'iss_supply_on' => $datedDocument['iss_supply_on'],
                'dated_document_hash' => CanonicalJson::hash($datedDocument), 'mapping_proof_hash' => $proof['source_hash']];
        }
        if ($documents[0]['dps_id'] === $documents[1]['dps_id']) {
            return $this->stop('DISTINCT_CANONICAL_DPS_IDS_REQUIRED', 'INVALID');
        }

        return ['status' => 'LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND', 'reason' => 'DPS107_DATED_COMPOSITION_AND_MAPPING_NOT_ISSUANCE_APPROVAL',
            'operation_reference' => $facts['rtc.export_operation_reference'], 'documents' => $documents,
            'identity_composition_locally_qualified' => true, 'dated_identity_pair_locally_bound' => true,
            'dated_pair_binding_hash' => $dated['binding_hash'], 'dated_results' => $dated,
            'rtc_operation_date' => $facts['rtc.operation_date'], 'rtc_facts_rewritten' => false,
            'original_references_preserved' => true,
            'binding_hash' => CanonicalJson::hash(['facts' => $facts, 'dated_pair_hash' => $dated['binding_hash'],
                'documents' => $documents, 'policy' => $policy, 'knowledge_at' => $knowledgeAt]),
            'current_facts_hash' => CanonicalJson::hash($facts), 'raw_records_hash' => CanonicalJson::hash($records), ...$this->limits()];
    }

    private function text(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '' && ! str_contains($v, "\0");
    }

    /** @param array<string, mixed> $v
     * @param  list<string>  $keys
     */
    private function keys(array $v, array $keys): bool
    {
        return count($v) === count($keys) && array_diff($keys, array_keys($v)) === [];
    }

    private function date(mixed $v): bool
    {
        if (! is_string($v)) {
            return false;
        }
        try {
            LocalDate::fromString($v);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function timestamp(mixed $v): ?DateTimeImmutable
    {
        if (! is_string($v) || preg_match('/\A(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})\z/', $v, $p) !== 1) {
            return null;
        }
        $offset = $p[4] === 'Z' ? '+00:00' : $p[4];
        $hour = (int) substr($offset, 1, 2);
        $minute = (int) substr($offset, 4, 2);
        if ($hour > 14 || $minute > 59 || ($hour === 14 && $minute !== 0)) {
            return null;
        }
        $text = $p[1].'T'.$p[2].'.'.str_pad($p[3], 6, '0').$offset;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $text);

        return $date !== false && $date->format('Y-m-d\TH:i:s.uP') === $text ? $date : null;
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['dps_competence_legally_approved' => false, 'legal_applicability_approved' => false,
            'fiscal_document_identifiers_qualified' => false, 'cnpj_check_digits_verified' => false,
            'municipal_authorization_verified' => false, 'cadastral_identity_authenticated' => false,
            'remote_uniqueness_verified' => false, 'government_evidence_authenticated' => false,
            'iss_supply_date_materially_qualified' => false, 'documentary_currency_conversion_qualified' => false,
            'per_document_tax_amount_calculated' => false, 'document_obligation_complete' => false,
            'remote_deployment_proven' => false, 'xml_generated' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'DPS107_'.$reason, 'identity_composition_locally_qualified' => false,
            'original_references_preserved' => true, 'dated_identity_pair_locally_bound' => false,
            'rtc_facts_rewritten' => false, ...$this->limits()];
    }
}
