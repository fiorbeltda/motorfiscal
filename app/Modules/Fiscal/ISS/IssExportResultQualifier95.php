<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Independent local ISS result-evidence binding. Never treats RTC consumption or a payment as proof of result. */
final readonly class IssExportResultQualifier95
{
    public const CONTRACT = 'ISS-SOFTWARE-EXPORT-RESULT-LOCAL-2026.10.16-95';

    public const SOURCE_SHA256 = '3ae4d3fc894ced12874364e366867b736c81369352cbaf97c70d57e534cdf86c';

    public const SOURCE_OBSERVED_AT = '2026-10-16T14:16:16.333043+00:00';

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $context, DateTimeImmutable $knownAt, array $policy): array
    {
        if (($policy['contract'] ?? null) !== self::CONTRACT
            || ($policy['source_sha256'] ?? null) !== self::SOURCE_SHA256
            || ($policy['source_observed_at'] ?? null) !== self::SOURCE_OBSERVED_AT
            || ($policy['scope'] ?? null) !== 'LOCAL_SOFTWARE_RESULT_EVIDENCE_NOT_LEGAL_ACCEPTANCE') {
            return $this->stop('SOURCE_CONTRACT_NOT_BOUND');
        }
        if ($knownAt < new DateTimeImmutable(self::SOURCE_OBSERVED_AT)) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }
        $keys = ['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id',
            'transaction_evidence_id', 'service_kind', 'supplier_country', 'performed_in', 'supply_on',
            'special_context', 'result_proof'];
        $missing = array_values(array_filter($keys, static fn (string $key): bool => ! array_key_exists($key, $context)));
        if ($missing !== []) {
            return [...$this->stop('EXPLICIT_CONTEXT_REQUIRED'), 'missing_fields' => $missing];
        }
        if (array_diff(array_keys($context), $keys) !== []) {
            return $this->stop('UNMAPPED_CONTEXT_FIELDS');
        }
        foreach (['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'transaction_evidence_id'] as $key) {
            if (! $this->id($context[$key])) {
                return $this->stop('OPERATION_DOCUMENT_PARTIES_REQUIRED');
            }
        }
        if ($context['service_kind'] !== 'SOFTWARE_SERVICE' || $context['supplier_country'] !== 'BR'
            || $context['performed_in'] !== 'BR' || $context['special_context'] !== false) {
            return $this->stop('SEPARATE_SERVICE_OR_SPECIAL_CONTEXT_REQUIRED');
        }
        $on = $context['supply_on'];
        $today = $knownAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        if (! $this->date($on)) {
            return $this->stop('EXACT_SERVICE_DATE_REQUIRED');
        }
        if ($on < '2026-01-01' || $on > '2032-12-31') {
            return $this->stop('LOCAL_PERIOD_NOT_COVERED');
        }
        if ($on > $today) {
            return $this->stop('SERVICE_DATE_NOT_REACHED');
        }
        $proof = $context['result_proof'];
        if (! is_array($proof) || array_is_list($proof)) {
            return $this->stop('RESULT_PROOF_REQUIRED');
        }
        $proofKeys = ['kind', 'evidence_id', 'valid_from', 'valid_to', 'observed_at', 'revoked_at',
            'superseded_at', 'value', 'source_hash'];
        if (array_diff($proofKeys, array_keys($proof)) !== [] || array_diff(array_keys($proof), $proofKeys) !== []) {
            return $this->stop('EXACT_RESULT_PROOF_CONTRACT_REQUIRED');
        }
        if ($proof['kind'] !== 'SERVICE_RESULT_MATERIAL_EVIDENCE' || ! $this->id($proof['evidence_id'])) {
            return $this->stop('RESULT_NOT_PAYMENT_DOMICILE_OR_RTC_CONSUMPTION_REQUIRED');
        }
        $material = $proof;
        unset($material['source_hash']);
        if (! is_string($proof['source_hash']) || ! hash_equals(CanonicalJson::hash($material), $proof['source_hash'])) {
            return $this->stop('RESULT_PROOF_HASH_CONFLICT');
        }
        if (! $this->date($proof['valid_from']) || ! $this->date($proof['valid_to'])
            || $proof['valid_from'] > $proof['valid_to'] || $on < $proof['valid_from'] || $on > $proof['valid_to']) {
            return $this->stop('RESULT_PROOF_PERIOD_NOT_BOUND');
        }
        $observed = $this->instant($proof['observed_at']);
        if ($observed === null || $observed > $knownAt
            || $observed->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d') < $on) {
            return $this->stop('RESULT_PROOF_OBSERVATION_NOT_BOUND');
        }
        foreach (['revoked_at', 'superseded_at'] as $key) {
            if ($proof[$key] === null) {
                continue;
            }
            $ended = $this->instant($proof[$key]);
            if ($ended === null || $ended < $observed) {
                return $this->stop('RESULT_PROOF_LIFECYCLE_INVALID');
            }
            if ($ended <= $knownAt) {
                return $this->stop('RESULT_PROOF_NO_LONGER_CURRENT');
            }
        }
        $value = $proof['value'];
        $valueKeys = ['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id',
            'transaction_evidence_id', 'supply_on', 'service_kind', 'performed_in',
            'result_scope', 'result_description', 'result_evidence_reference'];
        if (! is_array($value) || array_diff($valueKeys, array_keys($value)) !== []
            || array_diff(array_keys($value), $valueKeys) !== []) {
            return $this->stop('RESULT_PROOF_VALUE_CONTRACT_REQUIRED');
        }
        foreach (['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id',
            'transaction_evidence_id', 'supply_on', 'service_kind', 'performed_in'] as $key) {
            if ($value[$key] !== $context[$key]) {
                return $this->stop('RESULT_OPERATION_DOCUMENT_PARTY_BINDING_CONFLICT');
            }
        }
        if (! $this->id($value['result_description']) || ! $this->id($value['result_evidence_reference'])) {
            return $this->stop('INDEPENDENT_RESULT_DESCRIPTION_AND_EVIDENCE_REQUIRED');
        }
        if (! in_array($value['result_scope'], ['BR', 'ABROAD'], true)) {
            return $this->stop('INDIVIDUAL_DOCUMENT_RESULT_SCOPE_REQUIRED');
        }
        $foreign = $value['result_scope'] === 'ABROAD';

        return ['status' => $foreign ? 'LOCAL_ISS_EXPORT_NON_INCIDENCE_CANDIDATE' : 'LOCAL_ISS_EXPORT_EXCEPTION_BOUND',
            'reason' => $foreign ? 'ISS_EXPORT95_RESULT_ABROAD_LOCALLY_BOUND' : 'ISS_EXPORT95_RESULT_IN_BR_NOT_EXPORT_NON_INCIDENCE',
            'source_article' => 'LC116 art2 I e paragrafo unico', 'source_sha256' => self::SOURCE_SHA256,
            'scope' => 'LOCAL_SOFTWARE_RESULT_EVIDENCE_NOT_LEGAL_ACCEPTANCE',
            'operation_id' => $context['operation_id'], 'document_id' => $context['document_id'],
            'supplier_id' => $context['supplier_id'], 'acquirer_id' => $context['acquirer_id'],
            'recipient_id' => $context['recipient_id'], 'supply_on' => $on,
            'result_scope' => $value['result_scope'], 'result_evidence_id' => $proof['evidence_id'],
            'result_proof_hash' => $proof['source_hash'], 'context_hash' => CanonicalJson::hash($context),
            'policy_hash' => CanonicalJson::hash($policy), 'export_non_incidence_candidate' => $foreign,
            // BR result only defeats this export claim: it does not rule out other ISS treatments.
            'candidate_tribISSQN' => $foreign ? '3' : null,
            'independent_result_evidence_locally_bound' => true, ...$this->limits()];
    }

    private function id(mixed $value): bool
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
        if (! is_string($value) || preg_match('/\A(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?([+-]\d{2}:\d{2})\z/', $value, $parts) !== 1) {
            return null;
        }
        $hour = (int) substr($parts[3], 1, 2);
        $minute = (int) substr($parts[3], 4, 2);
        if ($hour > 14 || $minute > 59 || ($hour === 14 && $minute !== 0)) {
            return null;
        }
        $normalized = $parts[1].'.'.str_pad($parts[2], 6, '0').$parts[3];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $normalized);

        return $date !== false && $date->format('Y-m-d\TH:i:s.uP') === $normalized ? $date : null;
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['government_evidence_authenticated' => false, 'legal_applicability_approved' => false,
            'documentary_currency_conversion_qualified' => false, 'tax_calculated' => false,
            'iss_full_operation_rewritten' => false, 'xml_generated' => false,
            'document_obligation_complete' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'ISS_EXPORT95_'.$reason,
            'independent_result_evidence_locally_bound' => false, ...$this->limits()];
    }
}
