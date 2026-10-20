<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** SP PN4 arts1-3 documentary candidate. No FX rule or proof of legal applicability is inferred. */
final readonly class SpSoftwareExportResult97
{
    public const CONTRACT = 'SP-SOFTWARE-EXPORT-RESULT-LOCAL-2026.10.16-97';

    public const SOURCE_SHA256 = '066427dc1da3c25fddd1628bc3c168d1805665e113b2d7c5bbb894825e20a274';

    public const SOURCE_OBSERVED_AT = '2026-10-16T16:28:22.913454+00:00';

    /**
     * @param  array<string, mixed>  $context  National95 context, unchanged.
     * @param  array<string, mixed>  $nationalPolicy
     * @param  array<string, mixed>  $policy  Municipal source contract.
     * @return array<string, mixed>
     */
    public function qualify(array $context, array $nationalPolicy, mixed $proof, array $policy, DateTimeImmutable $knownAt): array
    {
        $expected = ['contract' => self::CONTRACT, 'source_sha256' => self::SOURCE_SHA256,
            'source_observed_at' => self::SOURCE_OBSERVED_AT, 'scope' => 'LOCAL_SP_ITEM1_RESULT_NOT_LEGAL_ACCEPTANCE'];
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash($expected)) {
            return $this->stop('MUNICIPAL_SOURCE_NOT_BOUND');
        }
        if ($knownAt < new DateTimeImmutable(self::SOURCE_OBSERVED_AT)) {
            return $this->stop('MUNICIPAL_SOURCE_NOT_YET_OBSERVED');
        }
        $national = (new IssExportResultQualifier95)->qualify($context, $knownAt, $nationalPolicy);
        if (($national['independent_result_evidence_locally_bound'] ?? false) !== true) {
            return [...$this->stop('NATIONAL_RESULT_NOT_BOUND'), 'national_reason' => $national['reason']];
        }
        $keys = ['kind', 'evidence_id', 'valid_from', 'valid_to', 'observed_at', 'revoked_at', 'superseded_at', 'value', 'source_hash'];
        if (! is_array($proof) || ! $this->keys($proof, $keys)
            || $proof['kind'] !== 'SP_SOFTWARE_RESULT_MATERIAL_EVIDENCE' || ! $this->id($proof['evidence_id'])) {
            return $this->stop('EXACT_MUNICIPAL_MATERIAL_PROOF_REQUIRED');
        }
        $material = $proof;
        unset($material['source_hash']);
        if (! is_string($proof['source_hash']) || ! hash_equals(CanonicalJson::hash($material), $proof['source_hash'])) {
            return $this->stop('MUNICIPAL_PROOF_HASH_CONFLICT');
        }
        $on = $context['supply_on'];
        if (! $this->date($proof['valid_from']) || ! $this->date($proof['valid_to'])
            || $proof['valid_from'] > $proof['valid_to'] || $on < $proof['valid_from'] || $on > $proof['valid_to']) {
            return $this->stop('MUNICIPAL_PROOF_PERIOD_NOT_BOUND');
        }
        $observed = $this->instant($proof['observed_at']);
        if ($observed === null || $observed > $knownAt
            || $observed->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d') < $on) {
            return $this->stop('MUNICIPAL_PROOF_OBSERVATION_NOT_BOUND');
        }
        foreach (['revoked_at', 'superseded_at'] as $key) {
            if ($proof[$key] === null) {
                continue;
            }
            $ended = $this->instant($proof[$key]);
            if ($ended === null || $ended < $observed) {
                return $this->stop('MUNICIPAL_PROOF_LIFECYCLE_INVALID');
            }
            if ($ended <= $knownAt) {
                return $this->stop('MUNICIPAL_PROOF_NO_LONGER_CURRENT');
            }
        }
        $value = $proof['value'];
        $valueKeys = ['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id',
            'transaction_evidence_id', 'supply_on', 'national_result_proof_hash', 'supplier_establishment_municipality',
            'supplier_establishment_evidence_reference', 'service_list_item', 'result_subject_kind',
            'result_subject_id', 'result_subject_scope', 'result_subject_location_reference',
            'software_asset_kind', 'software_asset_id', 'br_person_link', 'br_person_link_evidence_reference',
            'service_execution', 'monthly_stage'];
        if (! is_array($value) || ! $this->keys($value, $valueKeys)) {
            return $this->stop('EXACT_MUNICIPAL_VALUE_REQUIRED');
        }
        foreach (['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'transaction_evidence_id', 'supply_on'] as $key) {
            if ($value[$key] !== $context[$key]) {
                return $this->stop('MUNICIPAL_OPERATION_DOCUMENT_PARTY_CONFLICT');
            }
        }
        if ($value['national_result_proof_hash'] !== $national['result_proof_hash']) {
            return $this->stop('NATIONAL_RESULT_PROOF_LINK_CONFLICT');
        }
        if ($value['supplier_establishment_municipality'] !== '3550308' || $value['service_list_item'] !== '1') {
            return $this->stop('SEPARATE_MUNICIPALITY_OR_SERVICE_REQUIRED');
        }
        foreach (['supplier_establishment_evidence_reference', 'result_subject_id', 'result_subject_location_reference',
            'software_asset_id', 'br_person_link_evidence_reference'] as $key) {
            if (! $this->id($value[$key])) {
                return $this->stop('INDEPENDENT_MUNICIPAL_DOCUMENTARY_REFERENCES_REQUIRED');
            }
        }
        if (! in_array($value['result_subject_kind'], ['PERSON', 'TANGIBLE_ELEMENT', 'INTANGIBLE_ELEMENT', 'ECONOMIC_INTEREST'], true)
            || ! in_array($value['software_asset_kind'], ['SYSTEM', 'PROGRAM', 'DATABASE', 'EQUIPMENT'], true)) {
            return $this->stop('MUNICIPAL_SUBJECT_AND_SOFTWARE_ASSET_REQUIRED');
        }
        if (! in_array($value['result_subject_scope'], ['BR', 'ABROAD'], true)
            || $value['result_subject_scope'] !== $national['result_scope']) {
            return $this->stop('NATIONAL_MUNICIPAL_RESULT_SCOPE_CONFLICT');
        }
        if (! in_array($value['br_person_link'], ['HAS_BR_LINK', 'NO_BR_LINK'], true)) {
            return $this->stop('SOFTWARE_BR_LINK_MUST_BE_DOCUMENTED');
        }
        if ($value['service_execution'] === 'SINGLE') {
            if ($value['monthly_stage'] !== null) {
                return $this->stop('SEPARATE_EXECUTION_STAGE_REQUIRED');
            }
        } elseif ($value['service_execution'] === 'CONTINUING_MONTHLY') {
            $stage = $value['monthly_stage'];
            if (! is_array($stage) || ! $this->keys($stage, ['period', 'stage_id', 'completed_on', 'completion_evidence_reference'])
                || ! $this->id($stage['stage_id']) || ! $this->id($stage['completion_evidence_reference'])
                || ! is_string($on) || $stage['period'] !== substr($on, 0, 7)
                || ! $this->date($stage['completed_on']) || $stage['completed_on'] !== $on) {
                return $this->stop('DOCUMENTED_MONTHLY_STAGE_REQUIRED');
            }
        } else {
            return $this->stop('EXPLICIT_SERVICE_EXECUTION_REQUIRED');
        }
        $foreign = $value['result_subject_scope'] === 'ABROAD' && $value['br_person_link'] === 'NO_BR_LINK';

        return ['status' => $foreign ? 'LOCAL_SP_EXPORT_NON_INCIDENCE_CANDIDATE' : 'LOCAL_SP_EXPORT_EXCEPTION_BOUND',
            'reason' => $foreign ? 'SP97_FOREIGN_SUBJECT_AND_NO_BR_SOFTWARE_LINK_BOUND'
                : ($value['br_person_link'] === 'HAS_BR_LINK' ? 'SP97_SOFTWARE_LINKED_TO_BR_PERSON' : 'SP97_RESULT_SUBJECT_IN_BR'),
            'municipal_documentary_conditions_locally_bound' => true, 'export_non_incidence_candidate' => $foreign,
            'candidate_tribISSQN' => $foreign ? '3' : null, 'source_sha256' => self::SOURCE_SHA256,
            'source_articles' => ['PN_SF4_2016_ART1', 'PN_SF4_2016_ART2_I', 'PN_SF4_2016_ART3'],
            'national_result' => $national, 'municipal_proof_hash' => $proof['source_hash'],
            'operation_id' => $context['operation_id'], 'document_id' => $context['document_id'],
            'result_subject_scope' => $value['result_subject_scope'], 'br_person_link' => $value['br_person_link'],
            'qualification_hash' => CanonicalJson::hash(['context' => $context, 'proof' => $proof, 'policy' => $policy]),
            ...$this->limits()];
    }

    /**
     * @param  array<mixed>  $value
     * @param  list<string>  $keys
     */
    private function keys(array $value, array $keys): bool
    {
        return array_diff($keys, array_keys($value)) === [] && array_diff(array_keys($value), $keys) === [];
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
            'source_effective_date_proven' => false, 'iss_supply_date_materially_qualified' => false,
            'documentary_currency_conversion_qualified' => false, 'tax_calculated' => false,
            'xml_generated' => false, 'document_obligation_complete' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'SP97_'.$reason,
            'municipal_documentary_conditions_locally_bound' => false, ...$this->limits()];
    }
}
