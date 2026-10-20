<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Compose evidenced service prices; neither select an ISS tax base nor convert currencies. */
final readonly class PartialExportIssPriceComposition111
{
    public const CONTRACT = 'LOCAL-ISS-DOCUMENT-PRICE-COMPOSITION-2026.10.19-111';

    public const AVAILABLE_AT = '2026-10-19T18:10:00+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'price_policy' => PartialExportContractPrices110::policy(),
            'source_review_sha256' => 'de8f371533326d0c249036cbf349923b42e7e028f8f1e7994d6500a95b972413',
            'scope' => 'NORMAL_SP_SOFTWARE_ACTUAL_PRICE_NOT_TAX_BASE_OR_FX',
            'price_rule' => 'GROSS_SERVICE_PRICE_LESS_EVIDENCED_UNCONDITIONAL_DISCOUNTS',
            'rate_policy_selected' => false, 'legal_applicability_approved' => false];
    }

    /**
     * @param  array<string, mixed>  $input110
     * @param  array<int|string, mixed>  $records
     * @param  array<string, mixed>  $proof
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $input110, array $records, array $proof, string $knowledgeAt, array $policy): array
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
        foreach (['facts', 'material_records', 'result_records', 'identity_records', 'price_records', 'proof'] as $key) {
            if (! isset($input110[$key]) || ! is_array($input110[$key])) {
                return $this->stop('FRESH_PRICE_INPUT_REQUIRED');
            }
        }
        $price = (new PartialExportContractPrices110)->qualify($input110['facts'], $input110['material_records'],
            $input110['result_records'], $input110['identity_records'], $input110['price_records'],
            $input110['proof'], $knowledgeAt, $policy['price_policy']);
        if (($price['contract_price_evidence_locally_bound'] ?? false) !== true) {
            return [...$this->stop('FRESH_PRICE110_REQUIRED', $price['status']), 'upstream_reason' => $price['reason']];
        }
        if (! array_is_list($records) || count($records) !== 2) {
            return $this->stop('TWO_EXPLICIT_COMPOSITIONS_REQUIRED');
        }
        $byRole = [];
        foreach ($records as $record) {
            if (! is_array($record) || ! $this->keys($record, ['role', 'document_reference', 'contract_reference',
                'unit_reference', 'line_reference', 'currency', 'principal', 'service_additions', 'unconditional_discounts',
                'conditional_discounts', 'price110_amount_basis', 'all_price_adjustments_enumerated'])
                || ! in_array($record['role'], ['RTC_FOREIGN', 'RTC_DOMESTIC'], true)) {
                return $this->stop('EXACT_COMPOSITION_CONTRACT_REQUIRED', 'INVALID');
            }
            if (isset($byRole[$record['role']])) {
                return $this->stop('DUPLICATE_DOCUMENT_ROLE', 'INVALID');
            }
            $byRole[$record['role']] = $record;
        }
        $references = [];
        $documents = [];
        foreach ($price['documents'] as $document) {
            if (! isset($byRole[$document['role']])) {
                return $this->stop('TWO_EXPLICIT_COMPOSITIONS_REQUIRED');
            }
            $r = $byRole[$document['role']];
            foreach (['document_reference', 'contract_reference', 'unit_reference', 'line_reference'] as $key) {
                if ($r[$key] !== $document[$key]) {
                    return [...$this->stop('CURRENT_DOCUMENT_CONTEXT_CONFLICT', 'INVALID'), 'field' => $key];
                }
            }
            if ($r['currency'] !== $document['price_currency']) {
                return $this->stop('EXACT_DOCUMENT_CURRENCY_REQUIRED', 'INVALID');
            }
            if ($r['all_price_adjustments_enumerated'] !== true) {
                return $this->stop('COMPLETE_PRICE_ADJUSTMENT_EVIDENCE_REQUIRED');
            }
            if (! $this->money($r['principal'])) {
                return $this->stop('CANONICAL_PRINCIPAL_REQUIRED', 'INVALID');
            }
            $sums = [];
            foreach (['service_additions' => 'SERVICE_PRICE_ADDITION', 'unconditional_discounts' => 'UNCONDITIONAL',
                'conditional_discounts' => 'CONDITIONAL'] as $field => $kind) {
                if (! is_array($r[$field]) || ! array_is_list($r[$field])) {
                    return $this->stop('EXPLICIT_ADJUSTMENT_LISTS_REQUIRED', 'INVALID');
                }
                $sum = DecimalValue::fromString('0.00');
                foreach ($r[$field] as $item) {
                    if (! is_array($item) || ! $this->keys($item, ['reference', 'amount', 'terms_kind', 'granted_on', 'future_condition'])
                        || ! $this->text($item['reference']) || ! $this->money($item['amount'])
                        || DecimalValue::fromString($item['amount'])->compareTo(DecimalValue::fromString('0')) <= 0
                        || ! $this->day($item['granted_on']) || $item['granted_on'] > $document['iss_supply_on']
                        || $item['terms_kind'] !== $kind) {
                        return $this->stop('EXACT_DATED_ADJUSTMENT_TERMS_REQUIRED', 'INVALID');
                    }
                    if (in_array($item['reference'], $references, true)) {
                        return $this->stop('DISTINCT_ADJUSTMENT_REFERENCES_REQUIRED', 'INVALID');
                    }
                    $references[] = $item['reference'];
                    if ($kind === 'CONDITIONAL' ? ! $this->text($item['future_condition']) : $item['future_condition'] !== null) {
                        return $this->stop('UNAMBIGUOUS_DISCOUNT_CONDITIONS_REQUIRED');
                    }
                    $sum = $sum->plus(DecimalValue::fromString($item['amount']));
                }
                $sums[$field] = $sum;
            }
            $gross = DecimalValue::fromString($r['principal'])->plus($sums['service_additions']);
            $issPrice = $gross->minus($sums['unconditional_discounts']);
            $cashCandidate = $issPrice->minus($sums['conditional_discounts']);
            foreach ([$gross, $issPrice, $cashCandidate] as $amount) {
                if (! $this->money($amount->value())) {
                    return $this->stop('NEGATIVE_OR_OUT_OF_DOMAIN_PRICE_COMPOSITION', 'INVALID');
                }
            }
            $expected = match ($r['price110_amount_basis']) {
                'GROSS_BEFORE_ALL_DISCOUNTS' => $gross->value(),
                'NET_AFTER_UNCONDITIONAL_ONLY' => $issPrice->value(),
                'NET_AFTER_ALL_DISCOUNTS' => $cashCandidate->value(),
                default => null,
            };
            if ($expected === null) {
                return $this->stop('EXPLICIT_PRICE110_AMOUNT_BASIS_REQUIRED');
            }
            if ($expected !== $document['service_price']) {
                return $this->stop('PRICE110_COMPOSITION_RECONCILIATION_CONFLICT', 'INVALID');
            }
            $documents[] = [...$document, 'composition' => $r, 'gross_service_price' => $gross->value(),
                'unconditional_discount' => $sums['unconditional_discounts']->value(),
                'conditional_discount' => $sums['conditional_discounts']->value(),
                'price_for_iss_evaluation_in_input_currency' => $issPrice->value(),
                'payment_candidate_if_conditional_terms_satisfied' => $cashCandidate->value()];
        }
        if (! $this->keys($proof, ['contract', 'kind', 'evidence_id', 'observed_at', 'valid_from', 'valid_to',
            'revoked_at', 'superseded_at', 'value', 'origin', 'source_hash']) || $proof['contract'] !== self::CONTRACT
            || $proof['kind'] !== 'CAPTURED_DOCUMENT_PRICE_COMPOSITION_PAIR' || ! $this->text($proof['evidence_id'])
            || ! is_array($proof['origin']) || ! is_array($proof['value'])) {
            return $this->stop('EXPLICIT_COMPOSITION_EVIDENCE_REQUIRED');
        }
        $observed = $this->instant($proof['observed_at']);
        $prior = $this->instant($input110['proof']['observed_at'] ?? null);
        if ($observed === null || $prior === null || $observed > $known || $observed < $prior
            || $observed < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('COMPOSITION_OBSERVATION_OUTSIDE_DEPENDENCIES_OR_KNOWLEDGE');
        }
        $priorIds = [$input110['proof']['evidence_id'], $input110['facts']['provider.iss_regime_proof']['evidence_id']];
        foreach ($input110['material_records'] as $record) {
            $priorIds[] = $record['proof']['evidence_id'];
        }
        foreach ($input110['identity_records'] as $record) {
            $priorIds[] = $record['proof']['evidence_id'];
        }
        foreach ($input110['result_records'] as $record) {
            $priorIds[] = $record['iss_context']['result_proof']['evidence_id'];
            $priorIds[] = $record['municipal_proof']['evidence_id'];
            $priorIds[] = $record['event_binding']['evidence_id'];
        }
        if (in_array($proof['evidence_id'], $priorIds, true)) {
            return $this->stop('INDEPENDENT_ADJUSTMENT_EVIDENCE_REQUIRED', 'INVALID');
        }
        if (! $this->day($proof['valid_from']) || ! $this->day($proof['valid_to']) || $proof['valid_from'] > $proof['valid_to']) {
            return $this->stop('EXACT_COMPOSITION_COVERAGE_REQUIRED', 'INVALID');
        }
        foreach ($documents as $document) {
            if ($document['iss_supply_on'] < $proof['valid_from'] || $document['iss_supply_on'] > $proof['valid_to']) {
                return $this->stop('COMPOSITION_DOES_NOT_COVER_OWN_ISS_DATES');
            }
        }
        foreach (['revoked_at', 'superseded_at'] as $field) {
            if ($proof[$field] !== null) {
                $event = $this->instant($proof[$field]);
                if ($event === null) {
                    return $this->stop('EXACT_COMPOSITION_LIFECYCLE_REQUIRED', 'INVALID');
                }
                if ($event <= $known) {
                    return $this->stop('COMPOSITION_EVIDENCE_REVOKED_OR_SUPERSEDED');
                }
            }
        }
        $expectedValue = ['price110_binding_hash' => $price['binding_hash'],
            'operation_reference' => $input110['facts']['rtc.export_operation_reference'],
            'supplier_id' => $input110['facts']['rtc.supplier_id'], 'acquirer_id' => $input110['facts']['rtc.acquirer_id'],
            'supplier_municipality' => $input110['facts']['provider.municipality_ibge'], 'documents' => $documents];
        if (CanonicalJson::hash($proof['value']) !== CanonicalJson::hash($expectedValue)) {
            return $this->stop('CURRENT_COMPOSITION_CONTEXT_CONFLICT', 'INVALID');
        }
        $origin = $proof['origin'];
        $content = array_diff_key($proof, ['origin' => true, 'source_hash' => true]);
        if (! $this->keys($origin, ['reference', 'media_type', 'content', 'content_sha256']) || ! $this->text($origin['reference'])
            || $origin['media_type'] !== 'application/json' || ! is_string($origin['content'])
            || ! is_string($origin['content_sha256']) || $origin['content'] !== CanonicalJson::encode($content)
            || ! hash_equals(hash('sha256', $origin['content']), $origin['content_sha256'])) {
            return $this->stop('CAPTURED_COMPOSITION_CONTENT_CONFLICT', 'INVALID');
        }
        if (! is_string($proof['source_hash']) || ! hash_equals(CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true])), $proof['source_hash'])) {
            return $this->stop('COMPOSITION_ENVELOPE_HASH_CONFLICT', 'INVALID');
        }

        return ['status' => 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED', 'reason' => 'PRICE111_EVIDENCED_PRICES_NOT_ISS_TAX_BASE_OR_CONVERSION',
            'document_prices_locally_composed' => true, 'documents' => $documents,
            'price110_binding_hash' => $price['binding_hash'], 'composition_proof_hash' => $proof['source_hash'],
            'binding_hash' => CanonicalJson::hash(['price110' => $price['binding_hash'], 'documents' => $documents,
                'proof_hash' => $proof['source_hash'], 'known_at' => $knowledgeAt, 'policy' => $policy]), ...$this->limits()];
    }

    private function text(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '' && ! str_contains($v, "\0");
    }

    /**
     * @param  array<string,mixed>  $v
     * @param  list<string>  $keys
     */
    private function keys(array $v, array $keys): bool
    {
        return count($v) === count($keys) && array_diff($keys, array_keys($v)) === [];
    }

    private function money(mixed $v): bool
    {
        return is_string($v) && preg_match('/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', $v) === 1;
    }

    private function day(mixed $v): bool
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

    private function instant(mixed $v): ?DateTimeImmutable
    {
        if (! is_string($v) || preg_match('/\A(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})\z/', $v, $p) !== 1) {
            return null;
        }
        $offset = $p[4] === 'Z' ? '+00:00' : $p[4];
        $h = (int) substr($offset, 1, 2);
        $m = (int) substr($offset, 4, 2);
        if ($h > 14 || $m > 59 || ($h === 14 && $m !== 0)) {
            return null;
        }
        $t = $p[1].'T'.$p[2].'.'.str_pad($p[3], 6, '0').$offset;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $t);

        return $d !== false && $d->format('Y-m-d\TH:i:s.uP') === $t ? $d : null;
    }

    /** @return array<string,bool> */
    private function limits(): array
    {
        return ['rtc_facts_rewritten' => false, 'rtc_allocation_used_as_iss_price_rule' => false,
            'conditional_discount_subtracted_from_iss_price' => false, 'iss_base_calculated' => false,
            'per_document_tax_amount_calculated' => false, 'documentary_currency_conversion_qualified' => false,
            'payment_receipt_proven' => false, 'legal_applicability_approved' => false, 'government_evidence_authenticated' => false,
            'xml_generated' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string,mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'PRICE111_'.$reason, 'document_prices_locally_composed' => false, ...$this->limits()];
    }
}
