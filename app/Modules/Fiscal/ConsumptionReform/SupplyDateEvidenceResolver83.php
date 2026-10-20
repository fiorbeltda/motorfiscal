<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use DateTimeImmutable;
use DateTimeZone;

/** Local evidence normalization only; legal policy, calculation and posting remain separate gates. */
final class SupplyDateEvidenceResolver83
{
    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    public function resolve(array $operation, DateTimeImmutable $knownAt): array
    {
        $today = $knownAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        $id = $operation['id'] ?? null;
        $documentId = $operation['document_id'] ?? null;
        if (! $this->id($id) || ! $this->id($documentId)) {
            return $this->stop('OPERATION_DOCUMENT_BINDING_REQUIRED');
        }
        if (($operation['mode'] ?? null) !== 'ORDINARY'
            || ($operation['public_purchase'] ?? null) !== false
            || ($operation['cancelled'] ?? null) !== false) {
            return $this->stop('SEPARATE_EVENT_CONTEXT_REQUIRED');
        }
        $kind = $operation['supply_kind'] ?? null;
        $kinds = ['MATERIAL_DELIVERY', 'INTANGIBLE_AVAILABILITY', 'SERVICE_COMPLETION',
            'DOMESTIC_TRANSPORT_START', 'FOREIGN_CARGO_TRANSPORT_END', 'UNDOCUMENTED_DISCOVERY',
            'PUBLIC_AUCTION_ACQUISITION', 'JUDICIAL_AUCTION_ACQUISITION'];
        if (! in_array($kind, $kinds, true)) {
            return $this->stop('SUPPLY_KIND_UNPROVEN');
        }
        // Null is an explicit statement that no contrary/actual event has been supplied. Absence is unknown.
        if (! array_key_exists('actual_supply', $operation)) {
            return $this->stop('ACTUAL_SUPPLY_EVIDENCE_STATE_UNKNOWN');
        }
        $actual = $operation['actual_supply'];
        if ($actual !== null && (! $this->proof($actual, $id, $documentId, $today)
            || ($actual['kind'] ?? null) !== $kind)) {
            return $this->stop('ACTUAL_SUPPLY_PROOF_INVALID');
        }
        if (! array_key_exists('fiscal_document', $operation)) {
            return $this->stop('FISCAL_DOCUMENT_EVIDENCE_STATE_UNKNOWN');
        }
        $document = $operation['fiscal_document'];
        if ($document !== null && (! $this->proof($document, $id, $documentId, $today)
            || ($document['kind'] ?? null) !== 'FISCAL_DOCUMENT_ISSUED'
            || ($document['local_integrity_checked'] ?? null) !== true)) {
            return $this->stop('FISCAL_DOCUMENT_PROOF_INVALID');
        }
        if ($actual !== null) {
            // A material event may rebut a document. Do not silently discard supplied conflicting documents.
            if ($document !== null && ($actual['rebuts_document_id'] ?? null) !== $documentId) {
                return $this->stop('DOCUMENT_REBUTTAL_LINK_REQUIRED');
            }

            return $this->result($actual, 'ACTUAL_SUPPLY_EVIDENCE', $document, $today);
        }
        if (! in_array($kind, ['MATERIAL_DELIVERY', 'INTANGIBLE_AVAILABILITY'], true)) {
            return $this->stop('SERVICE_OR_SPECIAL_EVENT_DOCUMENT_PRESUMPTION_UNPROVEN');
        }
        if ($document === null) {
            return $this->stop('NO_SUPPLY_EVENT_OR_FISCAL_DOCUMENT');
        }
        if (! array_key_exists('expected_delivery_on', $document)) {
            return $this->stop('EXPECTED_DELIVERY_FIELD_STATE_UNKNOWN');
        }
        $expected = $document['expected_delivery_on'];
        if ($expected !== null && ! $this->date($expected)) {
            return $this->stop('EXPECTED_DELIVERY_DATE_INVALID');
        }
        $event = ['operation_id' => $id, 'document_id' => $documentId, 'kind' => $kind,
            'date' => $expected ?? $document['date'], 'evidence_id' => $document['evidence_id'],
            'observed_on' => $today, 'observation_basis' => 'LOCAL_PRESUMPTION_EVALUATION',
            'document_observed_on' => $document['observed_on']];

        return $this->result($event, $expected === null ? 'DOCUMENT_ISSUE_PRESUMPTION' : 'DOCUMENT_EXPECTED_DATE_PRESUMPTION', $document, $today);
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>|null  $document
     * @return array<string, mixed>
     */
    private function result(array $event, string $basis, ?array $document, string $today): array
    {
        // The document may contain a forecast, not an event that has already occurred.
        $reached = $event['date'] <= $today;

        return ['status' => $reached ? 'LOCAL_SUPPLY_DATE_ONLY' : 'PRESUMED_SUPPLY_DATE_NOT_YET_REACHED',
            'basis' => $basis, 'supply_event' => $event,
            'document_evidence_id' => $document['evidence_id'] ?? null,
            'source_policy_qualified' => false, 'material_supply_remotely_verified' => false,
            'production_ready' => false];
    }

    private function id(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && ! str_contains($value, "\0");
    }

    private function date(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) !== 1) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('America/Sao_Paulo'));

        return $date !== false && $date->format('Y-m-d') === $value && $value >= '2026-01-01';
    }

    private function proof(mixed $proof, string $id, string $documentId, string $today): bool
    {
        return is_array($proof) && ($proof['operation_id'] ?? null) === $id
            && ($proof['document_id'] ?? null) === $documentId && $this->id($proof['evidence_id'] ?? null)
            && $this->date($proof['date'] ?? null) && $this->date($proof['observed_on'] ?? null)
            && $proof['date'] <= $proof['observed_on'] && $proof['observed_on'] <= $today;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'source_policy_qualified' => false,
            'production_ready' => false];
    }
}
