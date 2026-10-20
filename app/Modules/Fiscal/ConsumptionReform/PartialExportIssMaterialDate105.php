<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Local material-performance evidence binding, independent of RTC date. Not legal or economic authentication. */
final readonly class PartialExportIssMaterialDate105
{
    public const CONTRACT = 'ISS-PARTIAL-EXPORT-MATERIAL-EVIDENCE-2026.10.19-105';

    public const AVAILABLE_AT = '2026-10-19T12:41:31.841178+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'scope' => 'LOCAL_SP_SOFTWARE_ORIGINAL_PAIR_MATERIAL_EVIDENCE_NOT_LEGAL_APPROVAL',
            'civil_timezone' => 'America/Sao_Paulo',
            'sources' => [
                'ctn' => '4536a6c7d566d184e188fe6c8fb7afd49ae5d4d48e3b2cd11a6c42b5a8c1bcaf',
                'lc116' => '23e06fe7a1dd75e06df80f1e761794e6aa0186ea53a2609ca2b1adc0545a8785',
                'law13701' => '9966f679d5859fb868d5639bb17e85188198444063b3fb613495e6e5111d4c89',
                'decree53151' => '6ac4319ca9b906af26ff917e02349d8d09725273d6c11990d37d95482d1d67ea',
                'pn4' => '066427dc1da3c25fddd1628bc3c168d1805665e113b2d7c5bbb894825e20a274',
                'metadata_review' => '5e9ce335f2dcd7df6e7bfe340293865a8aac8d20308313510614edb060a33b7c'],
            // The metadata receipt does not establish legal effects or historical applicability.
            'source_effective_date_proven' => false, 'legal_applicability_approved' => false];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<int|string, mixed>  $records
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $facts, array $records, string $knowledgeAt, array $policy): array
    {
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())) {
            return $this->stop('SOURCE_POLICY_NOT_BOUND');
        }
        $known = $this->instant($knowledgeAt);
        if ($known === null) {
            return $this->stop('EXACT_KNOWLEDGE_INSTANT_REQUIRED', 'INVALID');
        }
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }
        foreach (['service.canonical_id' => 'SOFTWARE_DEVELOPMENT', 'rtc.market' => 'EXPORT',
            'rtc.operation_kind' => 'DIGITAL', 'rtc.supplier_country' => 'BR',
            'provider.person_type' => 'PRIVATE_LEGAL_ENTITY', 'provider.municipality_ibge' => '3550308'] as $key => $value) {
            if (($facts[$key] ?? null) !== $value) {
                return [...$this->stop('EXPLICIT_SP_SOFTWARE_SCOPE_REQUIRED'), 'field' => $key];
            }
        }
        $share = $facts['rtc.export_share_percent'] ?? null;
        if (! is_string($share) || preg_match('/\A[0-9]{1,3}(?:\.[0-9]{1,8})?\z/', $share) !== 1
            || DecimalValue::fromString($share)->compareTo(DecimalValue::fromString('0')) <= 0
            || DecimalValue::fromString($share)->compareTo(DecimalValue::fromString('100')) >= 0) {
            return $this->stop('PARTIAL_EXPORT_REQUIRED');
        }
        foreach (['rtc.export_operation_reference', 'rtc.export_foreign_document_id', 'rtc.export_domestic_document_id',
            'rtc.supplier_id', 'rtc.acquirer_id', 'rtc.recipient_id', 'rtc.export_domestic_recipient_id',
            'rtc.export_transaction_evidence_id'] as $key) {
            if (! $this->text($facts[$key] ?? null)) {
                return [...$this->stop('CURRENT_REFERENCE_FACT_REQUIRED'), 'field' => $key];
            }
        }
        if (! $this->date($facts['rtc.operation_date'] ?? null)) {
            return $this->stop('EXACT_RTC_DATE_REQUIRED', 'INVALID');
        }
        if ($facts['rtc.export_foreign_document_id'] === $facts['rtc.export_domestic_document_id']) {
            return $this->stop('DISTINCT_ORIGINAL_DOCUMENTS_REQUIRED', 'INVALID');
        }
        if (($facts['provider.iss_regime'] ?? null) !== 'GENERAL_AD_VALOREM') {
            return $this->stop('EXPLICIT_ISS_REGIME_REQUIRED');
        }
        $regime = $facts['provider.iss_regime_proof'] ?? null;
        if (! is_array($regime)) {
            return $this->stop('ISS_REGIME_EVIDENCE_REQUIRED');
        }
        $regimeValue = ['supplier_id' => $facts['rtc.supplier_id'], 'supplier_municipality' => '3550308',
            'service_kind' => 'SOFTWARE_SERVICE', 'regime' => 'GENERAL_AD_VALOREM',
            'special_regime' => false, 'supplier_immunity' => false, 'supplier_flat_tax' => false, 'simples_option' => false];
        $error = $this->proof($regime, 'ISS_SUPPLIER_REGIME_MATERIAL_EVIDENCE', $regimeValue, $known);
        if ($error !== null) {
            return [...$error, 'proof_role' => 'SUPPLIER_REGIME'];
        }
        if (! array_is_list($records) || count($records) !== 2) {
            return $this->stop('TWO_MATERIAL_RECORDS_REQUIRED');
        }
        $byRole = [];
        foreach ($records as $record) {
            if (! is_array($record) || ! $this->keys($record, ['role', 'document_reference', 'performance', 'proof'])
                || ! in_array($record['role'], ['RTC_FOREIGN', 'RTC_DOMESTIC'], true)
                || ! is_array($record['performance']) || ! is_array($record['proof'])) {
                return $this->stop('EXACT_ROLE_RECORD_CONTRACT_REQUIRED', 'INVALID');
            }
            if (array_key_exists($record['role'], $byRole)) {
                return $this->stop('DUPLICATE_ROLE', 'INVALID');
            }
            $byRole[$record['role']] = $record;
        }
        $documents = [];
        $unitEvents = [];
        $evidenceIds = [$regime['evidence_id']];
        foreach (['RTC_FOREIGN', 'RTC_DOMESTIC'] as $role) {
            if (! isset($byRole[$role])) {
                return $this->stop('TWO_MATERIAL_RECORDS_REQUIRED');
            }
            $record = $byRole[$role];
            $ref = $facts[$role === 'RTC_FOREIGN' ? 'rtc.export_foreign_document_id' : 'rtc.export_domestic_document_id'];
            $recipient = $facts[$role === 'RTC_FOREIGN' ? 'rtc.recipient_id' : 'rtc.export_domestic_recipient_id'];
            if ($record['document_reference'] !== $ref) {
                return $this->stop('ORIGINAL_DOCUMENT_REFERENCE_CONFLICT', 'INVALID');
            }
            $performance = $record['performance'];
            if (! $this->keys($performance, ['contract_reference', 'unit_reference', 'mode', 'performed_from',
                'performed_to', 'performed_at', 'iss_supply_on', 'unit_completed', 'final_delivery', 'document_state'])) {
                return $this->stop('EXACT_PERFORMANCE_CONTRACT_REQUIRED', 'INVALID');
            }
            if (! $this->text($performance['contract_reference']) || ! $this->text($performance['unit_reference'])) {
                return $this->stop('CONTRACT_AND_PERFORMED_UNIT_REQUIRED');
            }
            if (! in_array($performance['mode'], ['ONE_OFF_COMPLETED_SERVICE', 'FULFILLED_MONTHLY_STAGE'], true)) {
                return $this->stop('EXPLICIT_PERFORMANCE_MODE_REQUIRED');
            }
            if ($performance['document_state'] !== 'ORIGINAL_ACTIVE') {
                return $this->stop('DOCUMENT_LIFECYCLE_REQUIRES_SEPARATE_CHAIN');
            }
            if (! is_bool($performance['unit_completed']) || ! is_bool($performance['final_delivery'])) {
                return $this->stop('EXACT_COMPLETION_FLAGS_REQUIRED', 'INVALID');
            }
            if (! $performance['unit_completed']) {
                return $this->stop('ACTUAL_COMPLETED_SERVICE_OR_MONTHLY_STAGE_REQUIRED');
            }
            $performed = $this->instant($performance['performed_at']);
            if ($performed === null || ! $this->date($performance['performed_from'])
                || ! $this->date($performance['performed_to']) || ! $this->date($performance['iss_supply_on'])) {
                return $this->stop('EXACT_PERFORMANCE_DATES_REQUIRED', 'INVALID');
            }
            $from = $performance['performed_from'];
            $to = $performance['performed_to'];
            if ($from > $to || $performance['iss_supply_on'] !== $to
                || $performed->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d') !== $to) {
                return $this->stop('MATERIAL_EVENT_AND_CIVIL_DATE_CONFLICT', 'INVALID');
            }
            if ($performance['mode'] === 'FULFILLED_MONTHLY_STAGE' && substr($from, 0, 7) !== substr($to, 0, 7)) {
                return $this->stop('MONTHLY_STAGE_SEGMENTATION_REQUIRED');
            }
            if ($performed > $known) {
                return $this->stop('ACTUAL_PERFORMANCE_NOT_YET_KNOWN');
            }
            if ($from < $regime['valid_from'] || $to > $regime['valid_to']) {
                return $this->stop('REGIME_EVIDENCE_PERIOD_NOT_COVERED');
            }
            $proofValue = ['operation_reference' => $facts['rtc.export_operation_reference'], 'document_reference' => $ref,
                'role' => $role, 'supplier_id' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id'],
                'recipient_id' => $recipient, 'supplier_municipality' => '3550308',
                'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'],
                'rtc_operation_date' => $facts['rtc.operation_date'], 'service_kind' => 'SOFTWARE_SERVICE',
                'event_kind' => $performance['mode'] === 'FULFILLED_MONTHLY_STAGE' ? 'MONTHLY_STAGE_FULFILLED' : 'ACTUAL_SERVICE_COMPLETED',
                'performance' => $performance];
            $proof = $record['proof'];
            $error = $this->proof($proof, 'ISS_SERVICE_PERFORMANCE_MATERIAL_EVIDENCE', $proofValue, $known);
            if ($error !== null) {
                return [...$error, 'role' => $role];
            }
            if ($from < $proof['valid_from'] || $to > $proof['valid_to']) {
                return $this->stop('PERFORMANCE_EVIDENCE_PERIOD_NOT_COVERED');
            }
            if ($this->instant($proof['observed_at']) < $performed) {
                return $this->stop('PERFORMANCE_OBSERVATION_PRECEDES_EVENT');
            }
            if (in_array($proof['evidence_id'], $evidenceIds, true)) {
                return $this->stop('DISTINCT_ROLE_EVIDENCE_REQUIRED', 'INVALID');
            }
            $unitKey = CanonicalJson::hash(['contract' => $performance['contract_reference'], 'unit' => $performance['unit_reference']]);
            $event = $performance;
            $event['performed_at'] = $performed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
            $eventHash = CanonicalJson::hash($event);
            if (isset($unitEvents[$unitKey]) && $unitEvents[$unitKey] !== $eventHash) {
                return $this->stop('SAME_ECONOMIC_UNIT_EVENT_CONFLICT', 'INVALID');
            }
            $unitEvents[$unitKey] = $eventHash;
            $evidenceIds[] = $proof['evidence_id'];
            $documents[] = ['role' => $role, 'document_reference' => $ref, 'recipient_reference' => $recipient,
                'iss_supply_on' => $performance['iss_supply_on'], 'performed_at' => $performance['performed_at'],
                'mode' => $performance['mode'], 'performance_hash' => CanonicalJson::hash($performance),
                'evidence_id' => $proof['evidence_id'], 'origin_reference' => $proof['origin']['reference'],
                'origin_content_sha256' => $proof['origin']['content_sha256'], 'proof_hash' => $proof['source_hash']];
        }

        return ['status' => 'LOCAL_ISS_MATERIAL_DATE_EVIDENCE_PAIR_BOUND',
            'reason' => 'ISS_DATE105_MATERIAL_EVIDENCE_NOT_LEGAL_APPROVAL', 'material_date_evidence_locally_bound' => true,
            'operation_reference' => $facts['rtc.export_operation_reference'], 'rtc_operation_date' => $facts['rtc.operation_date'],
            'documents' => $documents, 'original_references_preserved' => true,
            'regime_evidence_hash' => $regime['source_hash'], 'policy_hash' => CanonicalJson::hash($policy),
            'current_facts_hash' => CanonicalJson::hash($facts), 'records_hash' => CanonicalJson::hash($records),
            'binding_hash' => CanonicalJson::hash(['facts' => $facts, 'documents' => $documents,
                'regime_evidence_hash' => $regime['source_hash'], 'policy' => $policy]), ...$this->limits()];
    }

    /**
     * @param  array<string, mixed>  $proof
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>|null
     */
    private function proof(array $proof, string $kind, array $expected, DateTimeImmutable $known): ?array
    {
        if (! $this->keys($proof, ['contract', 'kind', 'evidence_id', 'valid_from', 'valid_to', 'observed_at',
            'revoked_at', 'superseded_at', 'value', 'origin', 'source_hash'])) {
            return $this->stop('EXACT_MATERIAL_PROOF_CONTRACT_REQUIRED');
        }
        if ($proof['contract'] !== self::CONTRACT || $proof['kind'] !== $kind || ! $this->text($proof['evidence_id'])) {
            return $this->stop('INDEPENDENT_MATERIAL_PROOF_REQUIRED');
        }
        $material = $proof;
        unset($material['source_hash']);
        if (! is_string($proof['source_hash']) || ! hash_equals(CanonicalJson::hash($material), $proof['source_hash'])) {
            return $this->stop('PROOF_HASH_CONFLICT', 'INVALID');
        }
        if (! is_array($proof['value']) || CanonicalJson::hash($proof['value']) !== CanonicalJson::hash($expected)) {
            return $this->stop('PROOF_CURRENT_VALUE_BINDING_CONFLICT', 'INVALID');
        }
        if (! $this->date($proof['valid_from']) || ! $this->date($proof['valid_to']) || $proof['valid_from'] > $proof['valid_to']) {
            return $this->stop('EXACT_PROOF_PERIOD_REQUIRED', 'INVALID');
        }
        $observed = $this->instant($proof['observed_at']);
        if ($observed === null) {
            return $this->stop('EXACT_PROOF_OBSERVATION_REQUIRED', 'INVALID');
        }
        if ($observed > $known) {
            return $this->stop('PROOF_NOT_YET_KNOWN');
        }
        foreach (['revoked_at', 'superseded_at'] as $key) {
            if ($proof[$key] === null) {
                continue;
            }
            $ended = $this->instant($proof[$key]);
            if ($ended === null || $ended < $observed) {
                return $this->stop('PROOF_LIFECYCLE_INVALID', 'INVALID');
            }
            if ($ended <= $known) {
                return $this->stop('PROOF_NO_LONGER_CURRENT');
            }
        }
        $origin = $proof['origin'];
        if (! is_array($origin) || ! $this->keys($origin, ['reference', 'media_type', 'content', 'content_sha256'])
            || ! $this->text($origin['reference']) || $origin['media_type'] !== 'application/json'
            || ! $this->text($origin['content']) || ! is_string($origin['content_sha256'])) {
            return $this->stop('CAPTURED_MATERIAL_CONTENT_REQUIRED');
        }
        if (! hash_equals(hash('sha256', $origin['content']), $origin['content_sha256'])) {
            return $this->stop('ORIGIN_CONTENT_HASH_CONFLICT', 'INVALID');
        }
        try {
            $payload = json_decode($origin['content'], true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->stop('ORIGIN_JSON_INVALID', 'INVALID');
        }
        $originExpected = $proof;
        unset($originExpected['origin'], $originExpected['source_hash']);
        if (! is_array($payload) || CanonicalJson::hash($payload) !== CanonicalJson::hash($originExpected)
            || CanonicalJson::encode($payload) !== $origin['content']) {
            return $this->stop('ORIGIN_CONTENT_VALUE_CONFLICT', 'INVALID');
        }

        return null;
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

    private function date(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        try {
            LocalDate::fromString($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/\A(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})\z/', $value, $p) !== 1) {
            return null;
        }
        $offset = $p[4] === 'Z' ? '+00:00' : $p[4];
        $hour = (int) substr($offset, 1, 2);
        $minute = (int) substr($offset, 4, 2);
        if ($hour > 14 || $minute > 59 || ($hour === 14 && $minute !== 0)) {
            return null;
        }
        $text = $p[1].'T'.$p[2].'.'.str_pad($p[3], 6, '0').$offset;
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $text);

        return $instant !== false && $instant->format('Y-m-d\TH:i:s.uP') === $text ? $instant : null;
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['iss_supply_date_materially_qualified' => false, 'economic_performance_authenticated' => false,
            'source_effective_date_proven' => false, 'legal_applicability_approved' => false,
            'government_evidence_authenticated' => false, 'municipal_authorization_verified' => false,
            'iss_result_requalified' => false, 'dps_competence_requalified' => false,
            'documentary_currency_conversion_qualified' => false, 'per_document_tax_amount_calculated' => false,
            'xml_generated' => false, 'document_obligation_complete' => false,
            'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'ISS_DATE105_'.$reason, 'material_date_evidence_locally_bound' => false,
            'original_references_preserved' => true, ...$this->limits()];
    }
}
