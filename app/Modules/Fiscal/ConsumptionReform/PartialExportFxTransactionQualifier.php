<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Throwable;

/** Binds a local candidate conversion to immutable operation/document facts; never authorizes credit or transmission. */
final readonly class PartialExportFxTransactionQualifier
{
    public const CONTRACT = 'RTC-ORDINARY-PARTIAL-EXPORT-FX-LOCAL-2026.10.14-73';

    /** @param array<string,mixed> $facts
     * @param  array<string,mixed>  $policy
     * @return array<string,mixed>
     */
    public function qualify(array $facts, string $taxPoint, DateTimeImmutable $knownAt, array $policy): array
    {
        if (($policy['require_material_quote_origin'] ?? null) !== true) {
            return $this->stop('POLICY_MATERIAL_ORIGIN_REQUIRED');
        }
        if (! $this->date($taxPoint) || ! $this->date($policy['valid_from'] ?? null)
            || (isset($policy['valid_to']) && ! $this->date($policy['valid_to']))
            || (isset($policy['valid_to']) && $policy['valid_from'] > $policy['valid_to'])
            || ! $this->timestamp($policy['published_at'] ?? null)) {
            return $this->stop('POLICY_EXACT_DATE_REQUIRED');
        }
        $material = $policy;
        unset($material['source_hash']);
        if (($policy['contract'] ?? null) !== self::CONTRACT || ($policy['status'] ?? null) !== 'SANDBOX_CANDIDATE'
            || ($policy['source_hash'] ?? null) !== CanonicalJson::hash($material) || ! is_string($policy['published_at'] ?? null)
            || ! is_string($policy['valid_from'] ?? null) || $taxPoint < $policy['valid_from']
            || (isset($policy['valid_to']) && (! is_string($policy['valid_to']) || $taxPoint > $policy['valid_to']))
            || isset($policy['revoked_at']) || isset($policy['superseded_at'])) {
            return $this->stop('POLICY_UNQUALIFIED');
        }
        try {
            if (new DateTimeImmutable($policy['published_at']) > $knownAt) {
                return $this->stop('POLICY_NOT_KNOWN');
            }
        } catch (Throwable) {
            return $this->stop('POLICY_DATE_INVALID');
        }
        $required = ['rtc.gross_amount', 'rtc.gross_currency', 'rtc.supplier_id', 'rtc.acquirer_foreign_nif', 'rtc.export_domestic_recipient_id',
            'rtc.document_id', 'rtc.export_foreign_document_id', 'rtc.export_transaction_evidence_id', 'operation.amount',
            'rtc.operation_amount_reconciliation_id', 'rtc.export_fx_source_id', 'rtc.export_fx_proof',
            'rtc.supplier_country', 'rtc.acquirer_id', 'rtc.acquirer_country', 'rtc.recipient_id', 'rtc.recipient_country',
            'rtc.export_domestic_recipient_country', 'rtc.export_domestic_document_id', 'rtc.export_share_percent',
            'rtc.export_allocation_method', 'rtc.export_allocation_evidence_id', 'rtc.domestic_consumption_evidence_id', 'rtc.export_evidence_id'];
        $missing = array_values(array_filter($required, static fn (string $key): bool => ! array_key_exists($key, $facts) || $facts[$key] === null));
        if ($missing !== []) {
            return $this->stop('TRANSACTION_FACTS_REQUIRED', $missing);
        }
        foreach (array_diff($required, ['rtc.export_fx_proof']) as $key) {
            if (! is_string($facts[$key]) || trim($facts[$key]) === '') {
                return $this->stop('TRANSACTION_FACT_TYPE_CONFLICT');
            }
        }
        if ($facts['rtc.supplier_country'] !== 'BR' || $facts['rtc.export_domestic_recipient_country'] !== 'BR'
            || ! preg_match('/^[A-Z]{2}$/', $facts['rtc.acquirer_country']) || $facts['rtc.acquirer_country'] === 'BR'
            || ! preg_match('/^[A-Z]{2}$/', $facts['rtc.recipient_country']) || $facts['rtc.recipient_country'] === 'BR') {
            return $this->stop('EXPORT_AND_DOMESTIC_ROLE_CONFLICT');
        }
        if (($facts['rtc.foreign_consumption_proven'] ?? null) !== true
            || ! in_array($facts['rtc.export_allocation_method'], ['CONTRACTUAL', 'METERED_USAGE'], true)
            || ! preg_match('/^\d{1,3}(?:[.]\d{1,8})?$/', $facts['rtc.export_share_percent'])
            || BigDecimal::of($facts['rtc.export_share_percent'])->compareTo('0') <= 0
            || BigDecimal::of($facts['rtc.export_share_percent'])->compareTo('100') >= 0) {
            return $this->stop('EXPLICIT_PARTIAL_EXPORT_ALLOCATION_REQUIRED');
        }
        $sources = $policy['quote_sources'] ?? null;
        if (! is_array($sources)) {
            return $this->stop('QUOTE_SOURCE_POLICY_REQUIRED');
        }
        $trusted = [];
        foreach ($sources as $id => $source) {
            if (! is_string($id) || ! is_array($source)) {
                return $this->stop('QUOTE_SOURCE_POLICY_REQUIRED');
            }
            $trusted[$id] = $source;
        }
        $originQualification = null;

        $selectedSource = $trusted[$facts['rtc.export_fx_source_id']] ?? null;
        $origins = $policy['qualified_quote_origins'] ?? null;
        $origin = is_array($origins) ? ($origins[$facts['rtc.export_fx_source_id']] ?? null) : null;
        if (! is_array($selectedSource) || ! is_array($origin) || ($origin['status'] ?? null) !== 'QUALIFIED_LOCAL_SOURCE_SELECTION'
            || ($origin['currency'] ?? null) !== $facts['rtc.gross_currency']
            || ($origin['quote_source_sha256'] ?? null) !== ($selectedSource['sha256'] ?? null)
            || ! is_string($origin['primary_evidence_sha256'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/', $origin['primary_evidence_sha256'])
            || ! is_string($origin['known_from'] ?? null) || ! $this->timestamp($origin['known_from'])) {
            return $this->stop('MATERIAL_QUOTE_ORIGIN_UNQUALIFIED');
        }
        $originMaterial = $origin;
        unset($originMaterial['source_hash']);
        if (($origin['source_hash'] ?? null) !== CanonicalJson::hash($originMaterial)
            || new DateTimeImmutable($origin['known_from']) > $knownAt) {
            return $this->stop('MATERIAL_QUOTE_ORIGIN_HASH_OR_KNOWLEDGE_CONFLICT');
        }
        $expectedStrategy = $facts['rtc.gross_currency'] === 'USD'
            ? 'BCB_USD_BRL_PTAX' : 'FOREIGN_MONETARY_AUTHORITY_RATE_DISCLOSED_BY_BCB';
        if (($origin['strategy'] ?? null) !== $expectedStrategy
            || ($facts['rtc.gross_currency'] !== 'USD' && (! is_string($origin['foreign_authority_id'] ?? null)
                || trim($origin['foreign_authority_id']) === '' || ! is_string($origin['foreign_authority_evidence_sha256'] ?? null)
                || ! preg_match('/^[a-f0-9]{64}$/', $origin['foreign_authority_evidence_sha256'])))) {
            return $this->stop('MATERIAL_QUOTE_ORIGIN_STRATEGY_OR_AUTHORITY_UNQUALIFIED');
        }
        $originQualification = $origin;

        $converted = (new PartialExportFxQuoteConverter)->convert($facts['rtc.gross_amount'], $facts['rtc.gross_currency'], $taxPoint, $knownAt,
            ['synthetic' => true, 'operation_kind' => 'ORDINARY_PARTIAL_SERVICE_EXPORT', 'side' => 'SALE', 'bulletin' => 'Fechamento',
                'source_id' => $facts['rtc.export_fx_source_id']], $trusted);
        if (($converted['status'] ?? null) !== 'LOCAL_SOURCE_BOUND_PARTIAL_EXPORT_FX_CONVERSION') {
            return $converted;
        }
        $proof = $facts['rtc.export_fx_proof'];
        if (! is_array($proof) || ! array_is_list($proof) || count($proof) !== 1 || ! is_array($proof[0])) {
            return $this->stop('SINGLE_TRANSACTION_PROOF_REQUIRED');
        }
        $proof = $proof[0];
        if (($proof['qualified'] ?? null) !== true
            || ($proof['scope'] ?? null) !== 'SYNTHETIC_COMPLETE_PARTIAL_EXPORT_FX_TRANSACTION_NOT_REMOTE_VERIFICATION'
            || ! is_string($proof['evidence_id'] ?? null) || $proof['evidence_id'] !== $facts['rtc.operation_amount_reconciliation_id']) {
            return $this->stop('EXPLICIT_LOCAL_TRANSACTION_PROOF_REQUIRED');
        }
        if (! $this->date($proof['valid_from'] ?? null) || ! $this->date($proof['valid_to'] ?? null)
            || $proof['valid_from'] > $proof['valid_to'] || ! $this->timestamp($proof['observed_at'] ?? null)) {
            return $this->stop('TRANSACTION_EXACT_DATE_REQUIRED');
        }
        $proofMaterial = $proof;
        unset($proofMaterial['source_hash']);
        if (($proof['source_hash'] ?? null) !== CanonicalJson::hash($proofMaterial)) {
            return $this->stop('TRANSACTION_PROOF_HASH_CONFLICT');
        }
        if (! is_string($proof['observed_at'] ?? null) || ! is_string($proof['valid_from'] ?? null)
            || ! is_string($proof['valid_to'] ?? null) || $taxPoint < $proof['valid_from'] || $taxPoint > $proof['valid_to']) {
            return $this->stop('TRANSACTION_PROOF_PERIOD_REQUIRED');
        }
        try {
            if (new DateTimeImmutable($proof['observed_at']) > $knownAt) {
                return $this->stop('TRANSACTION_PROOF_NOT_KNOWN');
            }
        } catch (Throwable) {
            return $this->stop('TRANSACTION_PROOF_DATE_INVALID');
        }
        $expected = ['supplier_id' => $facts['rtc.supplier_id'], 'acquirer_foreign_nif' => $facts['rtc.acquirer_foreign_nif'],
            'domestic_recipient_id' => $facts['rtc.export_domestic_recipient_id'], 'document_id' => $facts['rtc.document_id'],
            'foreign_document_id' => $facts['rtc.export_foreign_document_id'], 'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'],
            'tax_point_date' => $taxPoint, 'original_amount' => $facts['rtc.gross_amount'], 'original_currency' => $facts['rtc.gross_currency'],
            'converted_gross_brl' => $converted['converted_gross_brl'], 'operation_amount_brl' => $facts['operation.amount'],
            'quote_source_id' => $facts['rtc.export_fx_source_id'], 'quote_source_sha256' => $converted['raw_source_sha256'],
            'supplier_country' => $facts['rtc.supplier_country'], 'acquirer_id' => $facts['rtc.acquirer_id'],
            'acquirer_country' => $facts['rtc.acquirer_country'], 'recipient_id' => $facts['rtc.recipient_id'],
            'recipient_country' => $facts['rtc.recipient_country'], 'domestic_recipient_country' => $facts['rtc.export_domestic_recipient_country'],
            'domestic_document_id' => $facts['rtc.export_domestic_document_id'], 'foreign_share_percent' => $facts['rtc.export_share_percent'],
            'allocation_method' => $facts['rtc.export_allocation_method'], 'allocation_evidence_id' => $facts['rtc.export_allocation_evidence_id'],
            'domestic_consumption_evidence_id' => $facts['rtc.domestic_consumption_evidence_id'], 'export_evidence_id' => $facts['rtc.export_evidence_id']];
        if (! is_array($proof['value'] ?? null) || CanonicalJson::hash($proof['value']) !== CanonicalJson::hash($expected)
            || $facts['operation.amount'] !== $converted['converted_gross_brl']) {
            return $this->stop('TRANSACTION_OPERATION_OR_DOCUMENT_BINDING_CONFLICT');
        }
        $adjustments = $facts['rtc.base_adjustments'] ?? null;
        if (! is_array($adjustments)) {
            return $this->stop('BASE_ADJUSTMENTS_REQUIRED');
        }
        foreach ($adjustments as $adjustment) {
            if (! is_array($adjustment) || ($adjustment['currency'] ?? null) !== 'BRL') {
                return $this->stop('ADJUSTMENT_BRL_UNIT_REQUIRED');
            }
        }

        return ['status' => 'LOCAL_PARTIAL_EXPORT_FX_TRANSACTION_BOUND', 'conversion' => $converted,
            'material_quote_origin' => $originQualification, 'converted_gross_brl' => $converted['converted_gross_brl'],
            'policy_hash' => $policy['source_hash'], 'transaction_proof_hash' => $proof['source_hash'],
            'transaction_locally_bound' => true, 'operation_document_binding_locally_verified' => true,
            'source_authenticity_externally_verified' => false, 'document_authenticated' => false, 'credit_appropriated' => false,
            'eligible_for_posting' => false, 'production_ready' => false];
    }

    private function date(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function timestamp(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?([+-]\d{2}:\d{2})$/', $value, $parts)) {
            return false;
        }
        $offsetHour = (int) substr($parts[4], 1, 2);
        $offsetMinute = (int) substr($parts[4], 4, 2);
        if ($offsetHour > 14 || $offsetMinute > 59 || ($offsetHour === 14 && $offsetMinute !== 0)) {
            return false;
        }
        $fraction = str_pad($parts[3], 6, '0');
        $normalized = $parts[1].'T'.$parts[2].'.'.$fraction.$parts[4];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $normalized);

        return $date !== false && $date->format('Y-m-d\TH:i:s.uP') === $normalized;
    }

    /** @param list<string> $missing
     * @return array<string,mixed>
     */
    private function stop(string $reason, array $missing = []): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_PARTIAL_EXPORT_FX_'.$reason, 'missing_facts' => $missing, 'production_ready' => false];
    }
}
