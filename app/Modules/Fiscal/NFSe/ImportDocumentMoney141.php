<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;
use Throwable;

/** Binds an explicit normalized invoice declaration to replayed monetary memories; no source authentication or tax qualification. */
final readonly class ImportDocumentMoney141
{
    public const CONTRACT = 'IMPORT-DOCUMENT-MONEY-141-V1';

    public const RULE = 'RTC.CLASSIFICATION.IMPORTED_STRATEGIC_CONSULTANCY141.STANDARD';

    /** @param array<string, mixed> $snapshot
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    public function reconcile(array $snapshot, string $source, array $evidence): array
    {
        try {
            $document = json_decode($source, true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->stop('SOURCE_JSON_INVALID');
        }
        // This normalized source contract requires canonical bytes, excluding duplicate keys and ambiguous numeric tokens.
        if (! is_array($document) || array_is_list($document) || CanonicalJson::encode($document) !== $source) {
            return $this->stop('CANONICAL_NORMALIZED_INVOICE_REQUIRED');
        }
        $keys = ['contract', 'coverage', 'invoice_id', 'line_id', 'supplier_nif', 'acquirer_id', 'service_id',
            'nbs_code', 'supply_on', 'supply_event_reference', 'currency', 'service_price', 'legacy_taxes'];
        if (count($document) !== count($keys) || array_diff(array_keys($document), $keys) !== []) {
            return $this->stop('EXACT_INVOICE_FIELDS_REQUIRED');
        }
        foreach (array_diff($keys, ['legacy_taxes']) as $key) {
            if (! is_string($document[$key]) || trim($document[$key]) === '') {
                return $this->stop('EXPLICIT_INVOICE_VALUES_REQUIRED');
            }
        }
        if ($document['contract'] !== self::CONTRACT || $document['coverage'] !== 'SINGLE_SERVICE_LINE_ENTIRE_INVOICE') {
            return $this->stop('INVOICE_PROFILE_NOT_MAPPED');
        }
        if (count($evidence) !== 3 || array_diff(array_keys($evidence), ['id', 'sha256', 'observed_at']) !== []
            || ! is_string($evidence['id'] ?? null) || trim($evidence['id']) === ''
            || ($evidence['sha256'] ?? null) !== hash('sha256', $source)) {
            return $this->stop('SOURCE_BYTES_NOT_BOUND');
        }
        $known = $this->instant($snapshot['input']['knowledge_at'] ?? null);
        $observed = $this->instant($evidence['observed_at'] ?? null);
        if ($known === null || $observed === null || $observed > $known) {
            return $this->stop('SOURCE_NOT_KNOWN');
        }
        $facts = $snapshot['input']['facts'] ?? [];
        if (($snapshot['knowledge']['catalog_versions']['rtc_codes'] ?? null) !== '2026.10.27-141-draft1'
            || ($snapshot['result']['status'] ?? null) !== 'ACTION_REQUIRED'
            || ($snapshot['result']['reason_codes'] ?? null) !== ['RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN']
            || ($facts['rtc.market'] ?? null) !== 'IMPORT' || ($facts['rtc.legacy_tax_inclusion'] ?? null) !== 'INCLUDED'
            || ($facts['rtc.import_brazil_share_percent'] ?? null) !== '100') {
            return $this->stop('REPLAYED_IMPORT_PROFILE_REQUIRED');
        }
        foreach (['invoice_id' => ['rtc.import_document_id', 'rtc.document_id', 'iss.document_reference'],
            'supplier_nif' => ['rtc.supplier_foreign_nif', 'rtc.supplier_id', 'iss.document_supplier_id'],
            'acquirer_id' => ['rtc.acquirer_id', 'rtc.recipient_id', 'iss.document_customer_id'],
            'service_id' => ['service.canonical_id'], 'nbs_code' => ['rtc.service_nbs_code'],
            'supply_on' => ['iss.document_supply_on'], 'supply_event_reference' => ['iss.supply_event_reference'],
            'currency' => ['iss.original_price_currency', 'rtc.gross_currency'],
            'service_price' => ['iss.original_price_amount', 'rtc.gross_amount']] as $key => $factKeys) {
            foreach ($factKeys as $fact) {
                if ($document[$key] !== ($facts[$fact] ?? null)) {
                    return $this->stop('INVOICE_FACT_BINDING_CONFLICT');
                }
            }
        }
        if (! $this->money($document['service_price']) || $document['currency'] !== 'USD'
            || $document['supply_on'] !== ($snapshot['input']['competence_date'] ?? null)) {
            return $this->stop('DOCUMENT_CURRENCY_PROFILE_NOT_MAPPED');
        }
        $iss = $this->unique($snapshot['result']['trace'] ?? [], 'type', 'ISS_CALCULATION');
        $conversion = $this->unique($snapshot['result']['trace'] ?? [], 'type', 'ISS_DOCUMENT_CURRENCY140');
        $rtc = $this->unique($snapshot['result']['trace'] ?? [], 'type', 'CONSUMPTION_REFORM');
        if ($iss === null || $conversion === null || $rtc === null) {
            return $this->stop('UNIQUE_MONETARY_TRACES_REQUIRED');
        }
        $classification = $this->unique($rtc['trace'] ?? [], 'stage', 'CLASSIFICATION', false);
        $fx = $this->unique($rtc['trace'] ?? [], 'stage', 'IMPORT_FX_TRANSACTION', false);
        $base = $this->unique($rtc['trace'] ?? [], 'stage', 'BASE', false);
        if (($classification['rule_id'] ?? null) !== self::RULE || ($iss['valid'] ?? null) !== true
            || ($iss['currency'] ?? null) !== 'BRL' || ($iss['deduction_amount'] ?? null) !== '0.00'
            || ($conversion['status'] ?? null) !== 'LOCAL_ISS_DOCUMENT_PRICE_CONVERTED'
            || ($fx['transaction_locally_bound'] ?? null) !== true || $base === null
            || ($base['gross'] ?? null) !== ($fx['converted_gross_brl'] ?? null)
            || ($base['gross'] ?? null) !== ($facts['operation.amount'] ?? null)
            || ($iss['gross_amount'] ?? null) !== ($conversion['converted_price_brl'] ?? null)) {
            return $this->stop('MONETARY_MEMORY_BINDING_CONFLICT');
        }
        foreach ([$iss['gross_amount'] ?? null, $iss['tax_amount'] ?? null, $base['gross'], $base['taxable_base'] ?? null] as $amount) {
            if (! $this->money($amount)) {
                return $this->stop('EXACT_CENT_VALUES_REQUIRED');
            }
        }
        $taxes = $document['legacy_taxes'];
        if (! is_array($taxes) || ! array_is_list($taxes) || count($taxes) !== 3
            || CanonicalJson::hash($taxes) !== CanonicalJson::hash($facts['rtc.base_adjustments'] ?? [])
            || CanonicalJson::hash($taxes) !== CanonicalJson::hash($base['adjustments'] ?? [])) {
            return $this->stop('EXPLICIT_COMPLETE_LEGACY_LEDGER_REQUIRED');
        }
        $sum = DecimalValue::fromString('0.00');
        $seen = [];
        foreach ($taxes as $tax) {
            if (! is_array($tax) || count($tax) !== 5
                || array_diff(array_keys($tax), ['kind', 'tax', 'amount', 'currency', 'evidence_id']) !== []
                || ($tax['kind'] ?? null) !== 'LEGACY_TAX_EXCLUSION' || ($tax['currency'] ?? null) !== 'BRL'
                || ! in_array($tax['tax'] ?? null, ['ISS', 'PIS', 'COFINS'], true)
                || isset($seen[$tax['tax']]) || ! $this->money($tax['amount'] ?? null)
                || ! is_string($tax['evidence_id'] ?? null) || trim($tax['evidence_id']) === '') {
                return $this->stop('LEGACY_LEDGER_ROW_NOT_QUALIFIED');
            }
            $seen[$tax['tax']] = $tax['amount'];
            $sum = $sum->plus(DecimalValue::fromString($tax['amount']));
        }
        if (($seen['ISS'] ?? null) !== $iss['tax_amount']) {
            return $this->stop('ISS_LEDGER_CONFLICT');
        }
        $issPrice = DecimalValue::fromString($iss['gross_amount']);
        $rtcPrice = DecimalValue::fromString($base['gross']);
        $delta = $rtcPrice->minus($issPrice);
        $calculated = $issPrice->plus($delta)->minus($sum)->rounded(2)->value();
        if ($calculated !== $base['taxable_base']) {
            return $this->stop('RTC_BASE_RECONCILIATION_CONFLICT');
        }

        return ['status' => 'LOCAL_NORMALIZED_INVOICE_MONETARY_RECONCILED', 'contract' => self::CONTRACT,
            'source_sha256' => hash('sha256', $source), 'source_evidence' => $evidence,
            'source_fields_extracted' => true, 'source_kind' => 'EXPLICIT_NORMALIZED_INVOICE_DECLARATION',
            'invoice_id' => $document['invoice_id'], 'line_id' => $document['line_id'],
            'original_currency' => $document['currency'], 'original_service_price' => $document['service_price'],
            'iss_document_price_brl' => $issPrice->value(), 'rtc_converted_gross_brl' => $rtcPrice->value(),
            'fx_adjustment_brl' => $delta->rounded(2)->value(), 'legacy_taxes' => $taxes,
            'legacy_exclusions_brl' => $sum->rounded(2)->value(), 'rtc_taxable_base_brl' => $calculated,
            'formula' => 'ISS_DOCUMENT_PRICE + FX_ADJUSTMENT - ISS - PIS - COFINS',
            'source_authenticity_verified' => false, 'legacy_tax_assessment_performed' => false,
            'fiscal_decision_final' => false, 'document_obligation_complete' => false,
            'xml_generated' => false, 'production_ready' => false];
    }

    /** @param list<array<string,mixed>> $rows
     * @return array<string,mixed>|null
     */
    private function unique(array $rows, string $key, string $value, bool $payload = true): ?array
    {
        $found = null;
        foreach ($rows as $row) {
            if (($row[$key] ?? null) === $value) {
                if ($found !== null || ($payload && ! is_array($row['payload'] ?? null))) {
                    return null;
                }
                $found = $payload ? $row['payload'] : $row;
            }
        }

        return $found;
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', $value) === 1;
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/', $value) !== 1) {
            return null;
        }
        try {
            $instant = new DateTimeImmutable($value);

            return DateTimeImmutable::getLastErrors() === false ? $instant : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'IMPORT_MONEY141_'.$reason,
            'fiscal_decision_final' => false, 'document_obligation_complete' => false,
            'xml_generated' => false, 'production_ready' => false];
    }
}
