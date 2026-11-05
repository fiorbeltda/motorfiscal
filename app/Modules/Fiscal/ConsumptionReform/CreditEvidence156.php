<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Qualified input contract, not a remote authentication or a posting service. */
final readonly class CreditEvidence156
{
    public const KEY = 'credit_evidence_a156';

    public const POLICY = ['version' => 'A156', 'import' => 'DEFINITIVE_DOCUMENT_AND_PAYMENT',
        'books' => 'DOCUMENT_COMPONENT_LINKS', 'automatic_posting' => false];

    /** @param array<string, mixed> $codes */
    public static function enabled(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null)
            && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY);
    }

    /** @return array<string, mixed> */
    public function qualify(FactBag $facts, string $market, DateTimeImmutable $knownAt): array
    {
        $links = [];
        if ($facts->value('rtc.credit_context') === 'INPUT') {
            $links[] = ['document_id' => $facts->value('rtc.input_document_id'), 'taxpayer_id' => $facts->value('rtc.acquirer_id'),
                'ibs_amount' => $facts->value('rtc.input_ibs_amount'), 'cbs_amount' => $facts->value('rtc.input_cbs_amount')];
        }
        if ($facts->value('rtc.assess_supplier_credit_period') === true) {
            $period = $facts->value('rtc.supplier_credit_period');
            if (! is_array($period) || count($period) !== 1 || ! is_array($period[0] ?? null)
                || ! is_array($period[0]['credit_pool'] ?? null)) {
                return $this->stop('RTC_BOOK_CREDIT_POOL_REQUIRED', ['rtc.supplier_credit_period']);
            }
            foreach ($period[0]['credit_pool'] as $credit) {
                if (! is_array($credit)) {
                    return $this->stop('RTC_BOOK_CREDIT_POOL_INVALID');
                }
                $links[] = ['document_id' => $credit['document_id'] ?? null, 'taxpayer_id' => $period[0]['supplier_id'] ?? null,
                    'ibs_amount' => $credit['ibs_amount'] ?? null, 'cbs_amount' => $credit['cbs_amount'] ?? null];
            }
        }
        if ($links === []) {
            return ['status' => 'RESOLVED', 'reason' => 'RTC_CREDIT_EVIDENCE_NOT_APPLICABLE', 'missing_facts' => []];
        }
        $books = $facts->value('rtc.credit_book_entries');
        if (! is_array($books) || ! array_is_list($books) || $books === []) {
            return $this->stop('RTC_CREDIT_BOOK_ENTRIES_REQUIRED', ['rtc.credit_book_entries']);
        }
        $qualified = [];
        $entryIds = [];
        foreach ($books as $envelope) {
            $record = $this->record($envelope, $knownAt);
            if ($record === null || ! $this->reference($record['book_id'] ?? null)
                || ! $this->reference($record['entry_id'] ?? null) || isset($entryIds[$record['entry_id']])
                || ! $this->reference($record['document_id'] ?? null) || isset($qualified[$record['document_id']])
                || ! $this->reference($record['taxpayer_id'] ?? null) || ($record['written'] ?? null) !== true
                || ! $this->date($record['document_on'] ?? null) || ! $this->date($record['booked_on'] ?? null)
                || $record['document_on'] > $record['booked_on'] || $record['booked_on'] > $knownAt->format('Y-m-d')
                || ! $this->money($record['ibs_amount'] ?? null) || ! $this->money($record['cbs_amount'] ?? null)) {
                return $this->stop('RTC_CREDIT_BOOK_ENTRY_INVALID', ['rtc.credit_book_entries']);
            }
            $entryIds[$record['entry_id']] = true;
            $qualified[$record['document_id']] = $record;
        }
        foreach ($links as $link) {
            $doc = $link['document_id'];
            if (! is_string($doc) || ! isset($qualified[$doc])) {
                return $this->stop('RTC_CREDIT_DOCUMENT_NOT_IN_BOOK', ['rtc.credit_book_entries']);
            }
            foreach ($link as $key => $value) {
                if ($qualified[$doc][$key] !== $value) {
                    return $this->stop('RTC_CREDIT_BOOK_LINK_CONFLICT', ['rtc.credit_book_entries']);
                }
            }
        }
        $import = null;
        if ($market === 'IMPORT' && $facts->value('rtc.credit_context') === 'INPUT') {
            $rows = $facts->value('rtc.import_definitive_credit_records');
            if (! is_array($rows) || ! array_is_list($rows) || count($rows) !== 1) {
                return $this->stop('RTC_IMPORT_DEFINITIVE_RECORD_REQUIRED', ['rtc.import_definitive_credit_records']);
            }
            $import = $this->record($rows[0], $knownAt);
            if ($import === null || ($import['contract'] ?? null) !== 'IMPORT-CREDIT-A156-V1'
                || ($import['document_id'] ?? null) !== $facts->value('rtc.input_document_id')
                || ($import['document_id'] ?? null) !== $facts->value('rtc.import_credit_fiscal_document_id')
                || ($import['foreign_invoice_id'] ?? null) !== $facts->value('rtc.import_document_id')
                || ($import['acquirer_id'] ?? null) !== $facts->value('rtc.acquirer_id')
                || ($import['importer_id'] ?? null) !== $facts->value('rtc.importer_id')
                || $facts->value('rtc.importer_id') !== $facts->value('rtc.acquirer_id')
                || $facts->value('rtc.acquirer_country') !== 'BR' || ($import['definitive'] ?? null) !== true
                || ($import['document_valid'] ?? null) !== true || $facts->value('rtc.input_document_valid') !== true
                || ! $this->date($import['document_on'] ?? null) || ! $this->date($import['credit_on'] ?? null)
                || $import['document_on'] > $import['credit_on'] || $import['credit_on'] > $knownAt->format('Y-m-d')
                || $qualified[$import['document_id']]['document_on'] !== $import['document_on']
                || $qualified[$import['document_id']]['booked_on'] > $import['credit_on']
                || ! is_array($import['payments'] ?? null) || ! array_is_list($import['payments']) || count($import['payments']) !== 2) {
                return $this->stop('RTC_IMPORT_DEFINITIVE_RECORD_INVALID', ['rtc.import_definitive_credit_records']);
            }
            $seen = [];
            foreach ($import['payments'] as $payment) {
                if (! is_array($payment) || ! in_array($payment['tax'] ?? null, ['IBS', 'CBS'], true)
                    || isset($seen[$payment['tax']]) || ($payment['document_id'] ?? null) !== $import['document_id']
                    || ($payment['payer_id'] ?? null) !== $import['importer_id'] || ($payment['extinguished'] ?? null) !== true
                    || ! $this->reference($payment['payment_id'] ?? null) || ! $this->reference($payment['evidence_id'] ?? null)
                    || ! $this->money($payment['amount'] ?? null)
                    || $payment['amount'] !== $facts->value('rtc.input_'.strtolower($payment['tax']).'_amount')
                    || ! $this->date($payment['paid_on'] ?? null) || $payment['paid_on'] < $import['document_on']
                    || $payment['paid_on'] > $import['credit_on'] || $facts->value('rtc.prior_tax_extinguished') !== true
                    || ($import['extinction_evidence_id'] ?? null) !== $facts->value('rtc.import_credit_extinction_evidence_id')
                    || ! $this->reference($import['extinction_evidence_id'] ?? null)) {
                    return $this->stop('RTC_IMPORT_PAYMENT_LINK_CONFLICT', ['rtc.import_definitive_credit_records']);
                }
                $seen[$payment['tax']] = true;
            }
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_CREDIT_DOCUMENT_BOOK_LINKS_QUALIFIED', 'missing_facts' => [],
            'contract' => 'CREDIT-EVIDENCE-A156-V1', 'books' => array_values($qualified), 'import' => $import,
            'authenticated' => false, 'posted' => false, 'payment_performed' => false];
    }

    /** @return array<string, mixed>|null */
    public function record(mixed $envelope, DateTimeImmutable $knownAt): ?array
    {
        if (! is_array($envelope) || ! is_array($envelope['record'] ?? null)
            || ($envelope['record_hash'] ?? null) !== CanonicalJson::hash($envelope['record'])) {
            return null;
        }
        $record = $envelope['record'];
        if (! in_array($record['source_kind'] ?? null, ['DOCUMENTARY_INPUT', 'SYNTHETIC_TEST'], true)
            || ! $this->reference($record['evidence_id'] ?? null)
            || ! is_string($record['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $record['source_hash'])
            || ! is_string($record['observed_at'] ?? null)
            || ! preg_match('/^\d{4}-\d\d-\d\dT(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $record['observed_at'])) {
            return null;
        }
        try {
            $observed = new DateTimeImmutable($record['observed_at']);
            if (DateTimeImmutable::getLastErrors() !== false || $observed > $knownAt) {
                return null;
            }
        } catch (\Exception) {
            return null;
        }

        return $record;
    }

    public function reference(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && preg_match('/\p{C}/u', $value) === 0;
    }

    public function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(?:0|[1-9]\d*)\.\d{2}$/D', $value) === 1;
    }

    public function date(mixed $value): bool
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

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $reason, array $missing = []): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'missing_facts' => $missing];
    }
}
