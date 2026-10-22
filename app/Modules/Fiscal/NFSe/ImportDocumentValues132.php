<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;

/** Reconciles declared single-line invoice values; does not qualify an ISS base or authenticate a source. */
final readonly class ImportDocumentValues132
{
    public const CONTRACT = 'IMPORT-DOCUMENT-VALUES-132-V1';

    /** @param array<string, mixed> $snapshot
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function reconcile(array $snapshot, array $document, string $sourceSha256): array
    {
        $keys = ['contract', 'coverage', 'invoice_id', 'line_id', 'supplier_nif', 'acquirer_id',
            'service_id', 'competence_date', 'currency', 'service_price', 'source_evidence'];
        if (count($document) !== count($keys) || array_diff(array_keys($document), $keys) !== []) {
            return $this->stop('EXACT_DOCUMENT_FIELDS_REQUIRED');
        }
        foreach (array_diff($keys, ['source_evidence']) as $key) {
            if (! is_string($document[$key]) || trim($document[$key]) === '') {
                return $this->stop('EXPLICIT_DOCUMENT_VALUES_REQUIRED');
            }
        }
        if ($document['contract'] !== self::CONTRACT
            || $document['coverage'] !== 'SINGLE_SERVICE_LINE_ENTIRE_INVOICE') {
            return $this->stop('DOCUMENT_PROFILE_NOT_MAPPED');
        }
        $evidence = $document['source_evidence'];
        if (! is_array($evidence) || count($evidence) !== 3 || array_diff(array_keys($evidence), ['id', 'sha256', 'observed_at']) !== []
            || ! is_string($evidence['id'] ?? null) || trim($evidence['id']) === ''
            || ! is_string($evidence['sha256'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/', $evidence['sha256']) !== 1
            || ! hash_equals($evidence['sha256'], $sourceSha256)) {
            return $this->stop('SOURCE_BYTES_NOT_BOUND');
        }
        $known = $this->instant($snapshot['input']['knowledge_at'] ?? null);
        $observed = $this->instant($evidence['observed_at'] ?? null);
        if ($known === null || $observed === null || $observed > $known) {
            return $this->stop('SOURCE_NOT_KNOWN');
        }
        $facts = $snapshot['input']['facts'] ?? [];
        foreach (['invoice_id' => 'rtc.import_document_id', 'supplier_nif' => 'rtc.supplier_foreign_nif',
            'acquirer_id' => 'rtc.acquirer_id', 'service_id' => 'service.canonical_id'] as $key => $fact) {
            if ($document[$key] !== ($facts[$fact] ?? null)) {
                return $this->stop('DOCUMENT_FACT_BINDING_CONFLICT');
            }
        }
        if ($document['competence_date'] !== ($snapshot['input']['competence_date'] ?? null)) {
            return $this->stop('DOCUMENT_FACT_BINDING_CONFLICT');
        }
        if (($facts['rtc.market'] ?? null) !== 'IMPORT' || $document['currency'] !== 'BRL'
            || ($facts['rtc.gross_currency'] ?? null) !== 'BRL') {
            return $this->stop('INDEPENDENT_ISS_CURRENCY_CONTRACT_REQUIRED');
        }
        $iss = null;
        foreach ($snapshot['result']['trace'] ?? [] as $item) {
            if (($item['type'] ?? null) === 'ISS_CALCULATION') {
                if ($iss !== null || ! is_array($item['payload'] ?? null)) {
                    return $this->stop('AMBIGUOUS_ISS_CALCULATION');
                }
                $iss = $item['payload'];
            }
        }
        if ($iss === null || ($iss['valid'] ?? null) !== true || ($iss['currency'] ?? null) !== 'BRL'
            || ($iss['deduction_amount'] ?? null) !== '0.00') {
            return $this->stop('ISS_PROFILE_NOT_MAPPED');
        }
        // No rounding, currency conversion, allocation, discount or deduction is inferred here.
        $amounts = ['invoice_service_price' => $document['service_price'],
            'operation_amount' => $facts['operation.amount'] ?? null, 'iss_gross_amount' => $iss['gross_amount'] ?? null,
            'rtc_original_gross_amount' => $facts['rtc.gross_amount'] ?? null];
        $price = null;
        foreach ($amounts as $amount) {
            if (! is_string($amount) || preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', $amount) !== 1) {
                return $this->stop('EXACT_NONNEGATIVE_CENT_VALUES_REQUIRED');
            }
            $decimal = DecimalValue::fromString($amount);
            if ($price !== null && $price->compareTo($decimal) !== 0) {
                return $this->stop('SERVICE_PRICE_CONFLICT');
            }
            $price = $decimal;
        }

        return ['status' => 'RECONCILED_DECLARED_VALUES', 'contract' => self::CONTRACT,
            'document_sha256' => CanonicalJson::hash($document), 'source_sha256' => $sourceSha256,
            'declared_document' => $document, 'source_fields_extracted' => false,
            'invoice_id' => $document['invoice_id'], 'line_id' => $document['line_id'],
            'currency' => 'BRL', 'compared_amounts' => $amounts, 'iss_calculation' => $iss,
            'source_authenticity_verified' => false, 'iss_legal_base_qualified' => false,
            'fiscal_decision_final' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);

        return $date !== false && $date->format('Y-m-d\TH:i:sP') === $value ? $date : null;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'IMPORT132_'.$reason,
            'fiscal_decision_final' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }
}
