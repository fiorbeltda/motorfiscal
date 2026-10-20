<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Bind declared contract prices to fresh dated documents; never select a tax base or FX rate. */
final readonly class PartialExportContractPrices110
{
    public const CONTRACT = 'LOCAL-PARTIAL-EXPORT-CONTRACT-PRICES-2026.10.19-110';

    public const AVAILABLE_AT = '2026-10-19T17:23:46+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'dated_identity_policy' => PartialExportDatedDpsIdentity107::policy(),
            'research_receipt_sha256' => '289d7f5b174ec8f49791f7a1e21e709f507ede7be9e89c38d1b12e5625c960ca',
            'scope' => 'DECLARED_CONTRACT_PRICE_PAIR_NOT_LEGAL_ISS_BASE_OR_CURRENCY_CONVERSION',
            'price_semantics' => 'CONTRACTUAL_SERVICE_PRICE_NOT_TAX_BASE',
            'local_currency_input_scope' => ['BRL', 'USD', 'EUR', 'AUD'],
            'source_effective_date_proven' => false, 'legal_applicability_approved' => false];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<int|string, mixed>  $materialRecords
     * @param  array<int|string, mixed>  $resultRecords
     * @param  array<int|string, mixed>  $identityRecords
     * @param  array<int|string, mixed>  $priceRecords
     * @param  array<string, mixed>  $proof
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $facts, array $materialRecords, array $resultRecords, array $identityRecords,
        array $priceRecords, array $proof, string $knowledgeAt, array $policy): array
    {
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())) {
            return $this->stop('EXACT_POLICY_REQUIRED');
        }
        $known = $this->instant($knowledgeAt);
        if ($known === null) {
            return $this->stop('EXACT_KNOWLEDGE_INSTANT_REQUIRED', 'INVALID');
        }
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('COMPONENT_NOT_YET_AVAILABLE');
        }
        if (($facts['rtc.consideration'] ?? null) !== 'ONEROUS') {
            return $this->stop('EXPLICIT_ONEROUS_CONTRACT_PRICE_SCOPE_REQUIRED');
        }
        $dated = (new PartialExportDatedDpsIdentity107)->qualify($facts, $materialRecords, $resultRecords,
            $identityRecords, $knowledgeAt, $policy['dated_identity_policy']);
        if (($dated['dated_identity_pair_locally_bound'] ?? false) !== true) {
            return [...$this->stop('FRESH_DATED_IDENTITY_PAIR_REQUIRED', $dated['status']), 'dated_reason' => $dated['reason']];
        }
        if (! array_is_list($priceRecords) || count($priceRecords) !== 2) {
            return $this->stop('TWO_EXPLICIT_PRICE_RECORDS_REQUIRED');
        }
        $byRole = [];
        foreach ($priceRecords as $record) {
            if (! is_array($record) || ! $this->keys($record, ['role', 'document_reference', 'contract_reference',
                'unit_reference', 'line_reference', 'price_currency', 'service_price', 'price_semantics'])
                || ! in_array($record['role'], ['RTC_FOREIGN', 'RTC_DOMESTIC'], true)) {
                return $this->stop('EXACT_PRICE_RECORD_CONTRACT_REQUIRED', 'INVALID');
            }
            if (array_key_exists($record['role'], $byRole)) {
                return $this->stop('DUPLICATE_PRICE_ROLE', 'INVALID');
            }
            $byRole[$record['role']] = $record;
        }
        $performances = [];
        foreach ($materialRecords as $record) {
            $performances[$record['role']] = $record['performance'];
        }
        $documents = [];
        $lineIds = [];
        $currency = null;
        $sum = DecimalValue::fromString('0.00');
        foreach ($dated['documents'] as $document) {
            $role = $document['role'];
            if (! isset($byRole[$role])) {
                return $this->stop('TWO_EXPLICIT_PRICE_RECORDS_REQUIRED');
            }
            $record = $byRole[$role];
            $performance = $performances[$role];
            foreach (['document_reference' => $document['document_reference'],
                'contract_reference' => $performance['contract_reference'], 'unit_reference' => $performance['unit_reference'],
                'price_semantics' => 'CONTRACTUAL_SERVICE_PRICE_NOT_TAX_BASE'] as $key => $value) {
                if ($record[$key] !== $value) {
                    return [...$this->stop('CURRENT_DOCUMENT_EVENT_OR_SEMANTICS_CONFLICT', 'INVALID'), 'field' => $key, 'role' => $role];
                }
            }
            if (! $this->text($record['line_reference']) || in_array($record['line_reference'], $lineIds, true)) {
                return $this->stop('DISTINCT_CONTRACT_LINES_REQUIRED', 'INVALID');
            }
            $lineIds[] = $record['line_reference'];
            if (! in_array($record['price_currency'], ['BRL', 'USD', 'EUR', 'AUD'], true)) {
                return $this->stop('CURRENCY_OUTSIDE_LOCAL_INPUT_SCOPE');
            }
            if ($currency !== null && $currency !== $record['price_currency']) {
                return $this->stop('MIXED_CURRENCY_CONTRACT_REQUIRES_SEPARATE_COMPONENT');
            }
            $currency = $record['price_currency'];
            if (! $this->money($record['service_price'])
                || DecimalValue::fromString($record['service_price'])->compareTo(DecimalValue::fromString('0')) <= 0) {
                return $this->stop('POSITIVE_CANONICAL_TWO_DECIMAL_PRICE_REQUIRED', 'INVALID');
            }
            $sum = $sum->plus(DecimalValue::fromString($record['service_price']));
            $documents[] = [...$record, 'iss_supply_on' => $document['iss_supply_on'], 'dps_id' => $document['dps_id'],
                'identity_hash' => CanonicalJson::hash($document['identity']), 'dated_document_hash' => $document['dated_document_hash']];
        }
        if (! $this->money($sum->value())) {
            return $this->stop('PAIR_TOTAL_OUTSIDE_LOCAL_DECIMAL_DOMAIN', 'INVALID');
        }
        if (! $this->keys($proof, ['contract', 'kind', 'evidence_id', 'observed_at', 'valid_from', 'valid_to',
            'revoked_at', 'superseded_at', 'value', 'origin', 'source_hash'])
            || $proof['contract'] !== self::CONTRACT || $proof['kind'] !== 'CAPTURED_CONTRACT_PRICE_PAIR'
            || ! $this->text($proof['evidence_id']) || ! is_array($proof['value']) || ! is_array($proof['origin'])) {
            return $this->stop('EXPLICIT_CONTRACT_PRICE_EVIDENCE_REQUIRED');
        }
        $observed = $this->instant($proof['observed_at']);
        if ($observed === null || $observed > $known || $observed < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('PRICE_PROOF_OBSERVATION_OUTSIDE_KNOWLEDGE');
        }
        if (! $this->date($proof['valid_from']) || ! $this->date($proof['valid_to']) || $proof['valid_from'] > $proof['valid_to']) {
            return $this->stop('EXACT_PRICE_EVIDENCE_COVERAGE_REQUIRED', 'INVALID');
        }
        foreach (['revoked_at', 'superseded_at'] as $field) {
            if ($proof[$field] !== null) {
                $event = $this->instant($proof[$field]);
                if ($event === null) {
                    return $this->stop('EXACT_PRICE_LIFECYCLE_REQUIRED', 'INVALID');
                }
                if ($event <= $known) {
                    return $this->stop('PRICE_EVIDENCE_REVOKED_OR_SUPERSEDED');
                }
            }
        }
        $priorIds = [$facts['provider.iss_regime_proof']['evidence_id']];
        if ($observed < new DateTimeImmutable($facts['provider.iss_regime_proof']['observed_at'])) {
            return $this->stop('PRICE_PROOF_PRECEDES_REGIME_OBSERVATION');
        }
        foreach ($materialRecords as $record) {
            $priorIds[] = $record['proof']['evidence_id'];
            if ($record['performance']['iss_supply_on'] < $proof['valid_from']
                || $record['performance']['iss_supply_on'] > $proof['valid_to']) {
                return $this->stop('PRICE_EVIDENCE_DOES_NOT_COVER_OWN_ISS_DATES');
            }
            if ($observed < new DateTimeImmutable($record['performance']['performed_at'])
                || $observed < new DateTimeImmutable($record['proof']['observed_at'])) {
                return $this->stop('PRICE_PROOF_PRECEDES_BOUND_MATERIAL_EVIDENCE');
            }
        }
        foreach ($resultRecords as $record) {
            foreach ([$record['iss_context']['result_proof'], $record['municipal_proof']] as $priorProof) {
                $priorIds[] = $priorProof['evidence_id'];
                if ($observed < new DateTimeImmutable($priorProof['observed_at'])) {
                    return $this->stop('PRICE_PROOF_PRECEDES_RESULT_OBSERVATION');
                }
            }
            $priorIds[] = $record['event_binding']['evidence_id'];
        }
        foreach ($identityRecords as $record) {
            $priorIds[] = $record['proof']['evidence_id'];
            if ($observed < new DateTimeImmutable($record['proof']['observed_at'])) {
                return $this->stop('PRICE_PROOF_PRECEDES_IDENTITY_OBSERVATION');
            }
        }
        if (in_array($proof['evidence_id'], $priorIds, true)) {
            return $this->stop('INDEPENDENT_CONTRACT_PRICE_EVIDENCE_REQUIRED', 'INVALID');
        }
        $expected = ['operation_reference' => $facts['rtc.export_operation_reference'],
            'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'],
            'supplier_id' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id'],
            'supplier_municipality' => $facts['provider.municipality_ibge'],
            'dated_identity_binding_hash' => $dated['binding_hash'],
            'contract_price_currency' => $currency, 'contract_service_price_total' => $sum->value(),
            'rtc_price_relationship' => 'SEPARATE_PRICE_BASIS_UNRECONCILED_WITH_RTC', 'documents' => $documents];
        if (CanonicalJson::hash($proof['value']) !== CanonicalJson::hash($expected)) {
            return $this->stop('CURRENT_PRICE_PAIR_CONTEXT_CONFLICT', 'INVALID');
        }
        $origin = $proof['origin'];
        $content = array_diff_key($proof, ['origin' => true, 'source_hash' => true]);
        if (! $this->keys($origin, ['reference', 'media_type', 'content', 'content_sha256'])
            || ! $this->text($origin['reference']) || $origin['media_type'] !== 'application/json'
            || ! is_string($origin['content']) || ! is_string($origin['content_sha256'])
            || CanonicalJson::encode($content) !== $origin['content']
            || ! hash_equals(hash('sha256', $origin['content']), $origin['content_sha256'])) {
            return $this->stop('CAPTURED_PRICE_CONTENT_CONFLICT', 'INVALID');
        }
        if (! is_string($proof['source_hash'])
            || ! hash_equals(CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true])), $proof['source_hash'])) {
            return $this->stop('PRICE_PROOF_ENVELOPE_HASH_CONFLICT', 'INVALID');
        }

        return ['status' => 'LOCAL_CONTRACT_PRICE_PAIR_EVIDENCE_BOUND',
            'reason' => 'PRICE110_CAPTURED_CONTRACT_PRICES_NOT_LEGAL_ISS_BASE_OR_CONVERSION',
            'contract_price_evidence_locally_bound' => true, 'documents' => $documents,
            'contract_price_currency' => $currency, 'contract_service_price_total' => $sum->value(),
            'dated_identity_binding_hash' => $dated['binding_hash'], 'price_proof_hash' => $proof['source_hash'],
            'origin_content_sha256' => $origin['content_sha256'], 'rtc_operation_date' => $facts['rtc.operation_date'],
            'current_facts_hash' => CanonicalJson::hash($facts), 'raw_price_records_hash' => CanonicalJson::hash($priceRecords),
            'binding_hash' => CanonicalJson::hash(['facts' => $facts, 'dated' => $dated['binding_hash'], 'documents' => $documents,
                'proof_hash' => $proof['source_hash'], 'knowledge_at' => $knowledgeAt, 'policy' => $policy]),
            ...$this->limits()];
    }

    private function text(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && ! str_contains($value, "\0");
    }

    /** @param array<string, mixed> $value
     * @param  list<string>  $keys
     */
    private function keys(array $value, array $keys): bool
    {
        return count($value) === count($keys) && array_diff($keys, array_keys($value)) === [];
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', $value) === 1;
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
        if (! is_string($value) || preg_match('/\A(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})\z/', $value, $parts) !== 1) {
            return null;
        }
        $offset = $parts[4] === 'Z' ? '+00:00' : $parts[4];
        $hour = (int) substr($offset, 1, 2);
        $minute = (int) substr($offset, 4, 2);
        if ($hour > 14 || $minute > 59 || ($hour === 14 && $minute !== 0)) {
            return null;
        }
        $text = $parts[1].'T'.$parts[2].'.'.str_pad($parts[3], 6, '0').$offset;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $text);

        return $date !== false && $date->format('Y-m-d\TH:i:s.uP') === $text ? $date : null;
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['rtc_facts_rewritten' => false, 'rtc_allocation_used_as_iss_price_rule' => false,
            'rtc_price_reconciled' => false, 'iss_legal_price_qualified' => false, 'iss_base_calculated' => false,
            'currency_code_materially_qualified' => false, 'documentary_currency_conversion_qualified' => false,
            'per_document_tax_amount_calculated' => false, 'government_evidence_authenticated' => false,
            'source_effective_date_proven' => false, 'legal_applicability_approved' => false,
            'document_obligation_complete' => false, 'xml_generated' => false, 'posting_authorized' => false,
            'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'PRICE110_'.$reason, 'contract_price_evidence_locally_bound' => false, ...$this->limits()];
    }
}
