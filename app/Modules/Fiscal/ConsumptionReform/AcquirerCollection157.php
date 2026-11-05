<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;
use InvalidArgumentException;

/** Reconciles qualified receipts; never sends funds, authenticates them or posts a fiscal discharge. */
final readonly class AcquirerCollection157
{
    public const CONTRACT = 'RTC-ACQUIRER-COLLECTION-A157-V1';

    public const KEY = 'acquirer_collection_a157';

    public const POLICY = ['version' => 'A157', 'contract' => self::CONTRACT, 'method' => 'ACQUIRER',
        'source_hash' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde', 'posting' => false];

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function reconcile(array $input): array
    {
        try {
            return $this->calculate($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(),
                'missing_facts' => ['rtc.acquirer_collection_ledger'], 'posted' => false, 'payment_performed' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null)
            && CanonicalJson::hash($this->reconcile($report['input'])) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function calculate(array $input): array
    {
        $guard = new CreditEvidence156;
        $this->require(($input['contract'] ?? null) === self::CONTRACT, 'A157_CONTRACT_REQUIRED');
        $known = $this->instant($input['known_at'] ?? null);
        $operationAt = $this->instant($input['operation_at'] ?? null);
        $historyAt = $this->instant($input['history_as_of'] ?? null);
        $this->require(($input['currency'] ?? null) === 'BRL', 'A157_BRL_REQUIRED');
        $this->require(in_array($input['source_kind'] ?? null, ['SYNTHETIC_TEST', 'DOCUMENTARY_INPUT'], true), 'A157_PROVENANCE_REQUIRED');
        foreach (['document_id', 'supplier_id', 'acquirer_id', 'payment_transaction_id', 'instrument_evidence_id', 'history_evidence_id', 'evidence_id'] as $key) {
            $this->require($this->identifier($input[$key] ?? null), 'A157_IDENTITY_REQUIRED:'.$key);
        }
        $this->require(($input['acquirer_regime'] ?? null) === 'REGULAR'
            && ($input['instrument_split_capable'] ?? null) === false, 'A157_ART36_ELIGIBILITY_CONFLICT');
        $this->require($guard->date($input['operation_date'] ?? null)
            && $input['operation_date'] >= '2026-01-01' && $input['operation_date'] === $operationAt->format('Y-m-d')
            && $operationAt <= $historyAt && $historyAt <= $known, 'A157_OPERATION_DATE_CONFLICT');
        $this->require(($input['history_complete'] ?? null) === true && ($input['payments_complete'] ?? null) === true
            && ($input['transfers_complete'] ?? null) === true && ($input['other_extinctions_complete'] ?? null) === true
            && ($input['history_excludes_acquirer_collections'] ?? null) === true, 'A157_COMPLETE_LEDGER_REQUIRED');
        $this->require(is_string($input['source_hash'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $input['source_hash']) === 1, 'A157_SOURCE_HASH_REQUIRED');
        $material = $input;
        unset($material['input_hash']);
        $this->require(($input['input_hash'] ?? null) === CanonicalJson::hash($material), 'A157_INPUT_HASH_CONFLICT');
        foreach (['payments', 'transfers', 'prior_receipt_ids', 'other_extinctions'] as $key) {
            $this->require(is_array($input[$key] ?? null) && array_is_list($input[$key]), 'A157_LIST_REQUIRED:'.$key);
        }
        $seen = [];
        foreach ($input['prior_receipt_ids'] as $id) {
            $this->require($this->identifier($id) && ! isset($seen[$id]), 'A157_PRIOR_RECEIPT_INVALID');
            $seen[$id] = true;
        }
        $zero = DecimalValue::fromString('0.00');
        foreach (['debits', 'previously_extinguished'] as $key) {
            $this->require(is_array($input[$key] ?? null) && count($input[$key]) === 2
                && array_key_exists('IBS', $input[$key]) && array_key_exists('CBS', $input[$key]), 'A157_TWO_TAX_COMPONENTS_REQUIRED');
        }
        $taxes = [];
        foreach (['IBS', 'CBS'] as $tax) {
            $debit = $this->money($input['debits'][$tax] ?? null);
            $prior = $this->money($input['previously_extinguished'][$tax] ?? null);
            $this->require($prior->compareTo($debit) <= 0, 'A157_PRIOR_EXTINCTION_EXCEEDS_DEBIT');
            $this->require($prior->compareTo($zero) === 0 || $input['prior_receipt_ids'] !== [], 'A157_PRIOR_RECEIPTS_REQUIRED');
            $taxes[$tax] = ['debit' => $debit, 'prior' => $prior, 'remaining' => $debit->minus($prior),
                'received' => $zero, 'applied' => $zero, 'other_applied' => $zero, 'excess' => $zero, 'transferred' => $zero];
        }
        $payments = [];
        $instants = [];
        $authorityReceipts = $seen;
        foreach ($input['payments'] as $payment) {
            $this->require(is_array($payment), 'A157_PAYMENT_INVALID');
            $this->bind($payment, $input);
            $id = $payment['id'] ?? null;
            $this->require($this->identifier($id) && ! isset($seen[$id])
                && $this->identifier($payment['authority_receipt_id'] ?? null)
                && ! isset($authorityReceipts[$payment['authority_receipt_id']])
                && $this->identifier($payment['evidence_id'] ?? null) && ($payment['settled'] ?? null) === true
                && ($payment['payer_id'] ?? null) === $input['acquirer_id'], 'A157_PAYMENT_RECEIPT_CONFLICT');
            $instant = $this->instant($payment['settled_at'] ?? null);
            $tick = $payment['tax'].':'.$instant->format('U.u');
            $this->require(! isset($instants[$tick]) && $instant <= $known
                && $instant > $historyAt, 'A157_PAYMENT_TIME_CONFLICT');
            $instants[$tick] = true;
            $seen[$id] = true;
            $authorityReceipts[$payment['authority_receipt_id']] = true;
            $payment['parsed_at'] = $instant;
            $payment['amount_value'] = $this->money($payment['amount'] ?? null);
            $payment['event_kind'] = 'ACQUIRER_COLLECTION';
            $payments[] = $payment;
        }
        foreach ($input['other_extinctions'] as $event) {
            $this->require(is_array($event), 'A157_OTHER_EXTINCTION_INVALID');
            $this->bind($event, $input);
            $id = $event['id'] ?? null;
            $this->require($this->identifier($id) && ! isset($seen[$id])
                && $this->identifier($event['authority_receipt_id'] ?? null) && ! isset($authorityReceipts[$event['authority_receipt_id']])
                && $this->identifier($event['evidence_id'] ?? null) && ($event['applied'] ?? null) === true
                && in_array($event['modality'] ?? null, ['COMPENSATION', 'SUPPLIER_PAYMENT', 'SPLIT_PAYMENT', 'RESPONSIBLE_PAYMENT'], true), 'A157_OTHER_EXTINCTION_PROOF_REQUIRED');
            $instant = $this->instant($event['applied_at'] ?? null);
            $tick = $event['tax'].':'.$instant->format('U.u');
            $this->require($instant > $historyAt && $instant <= $known && ! isset($instants[$tick]), 'A157_OTHER_EXTINCTION_TIME_CONFLICT');
            $instants[$tick] = true;
            $authorityReceipts[$event['authority_receipt_id']] = true;
            $seen[$id] = true;
            $event['parsed_at'] = $instant;
            $event['amount_value'] = $this->money($event['amount'] ?? null);
            $event['event_kind'] = 'OTHER_EXTINCTION';
            $payments[] = $event;
        }
        usort($payments, static fn (array $a, array $b): int => ($a['parsed_at'] <=> $b['parsed_at']) ?: strcmp($a['tax'], $b['tax']));
        $allocations = [];
        $otherTrace = [];
        foreach ($payments as $payment) {
            $tax = $payment['tax'];
            $amount = $payment['amount_value'];
            $balance = $taxes[$tax];
            $remaining = $balance['remaining'];
            if ($payment['event_kind'] === 'OTHER_EXTINCTION') {
                $this->require($amount->compareTo($remaining) <= 0, 'A157_OTHER_EXTINCTION_EXCEEDS_REMAINING');
                $balance['remaining'] = $remaining->minus($amount);
                $balance['other_applied'] = $balance['other_applied']->plus($amount);
                $taxes[$tax] = $balance;
                $otherTrace[] = ['id' => $payment['id'], 'tax' => $tax, 'modality' => $payment['modality'],
                    'amount' => $amount->value(), 'applied_at' => $payment['applied_at'], 'authority_receipt_id' => $payment['authority_receipt_id']];

                continue;
            }
            $applied = $amount->compareTo($remaining) <= 0 ? $amount : $remaining;
            $excess = $amount->minus($applied);
            $taxes[$tax] = ['debit' => $balance['debit'], 'prior' => $balance['prior'], 'remaining' => $remaining->minus($applied),
                'received' => $balance['received']->plus($amount), 'applied' => $balance['applied']->plus($applied),
                'other_applied' => $balance['other_applied'], 'excess' => $balance['excess']->plus($excess), 'transferred' => $balance['transferred']];
            $allocations[$payment['id']] = ['collection_payment_id' => $payment['id'], 'tax' => $tax, 'amount' => $amount->value(), 'applied' => $applied->value(),
                'excess' => $excess->value(), 'transferred' => '0.00', 'settled_at' => $payment['settled_at'],
                'authority_receipt_id' => $payment['authority_receipt_id']];
        }
        $authorityTransfers = [];
        foreach ($input['transfers'] as $transfer) {
            $this->require(is_array($transfer), 'A157_TRANSFER_INVALID');
            $this->bind($transfer, $input);
            $id = $transfer['id'] ?? null;
            $paymentId = $transfer['collection_payment_id'] ?? null;
            $this->require($this->identifier($id) && ! isset($seen[$id]) && is_string($paymentId)
                && isset($allocations[$paymentId]) && $allocations[$paymentId]['tax'] === $transfer['tax']
                && ($transfer['beneficiary_id'] ?? null) === $input['supplier_id']
                && $this->identifier($transfer['authority_transfer_receipt_id'] ?? null)
                && ! isset($authorityTransfers[$transfer['authority_transfer_receipt_id']])
                && ($transfer['settled'] ?? null) === true && $this->identifier($transfer['evidence_id'] ?? null), 'A157_TRANSFER_LINK_CONFLICT');
            $instant = $this->instant($transfer['transferred_at'] ?? null);
            $this->require($instant <= $known && $instant >= $this->instant($allocations[$paymentId]['settled_at']), 'A157_TRANSFER_TIME_CONFLICT');
            $amount = $this->money($transfer['amount'] ?? null);
            $total = DecimalValue::fromString($allocations[$paymentId]['transferred'])->plus($amount);
            $this->require($total->compareTo(DecimalValue::fromString($allocations[$paymentId]['excess'])) <= 0, 'A157_TRANSFER_EXCEEDS_RECEIPT_EXCESS');
            $allocations[$paymentId]['transferred'] = $total->value();
            $balance = $taxes[$transfer['tax']];
            $balance['transferred'] = $balance['transferred']->plus($amount);
            $taxes[$transfer['tax']] = $balance;
            $seen[$id] = true;
            $authorityTransfers[$transfer['authority_transfer_receipt_id']] = true;
        }
        $result = [];
        foreach ($taxes as $tax => $amounts) {
            $result[$tax] = array_map(static fn (DecimalValue $value): string => $value->value(), $amounts);
            $result[$tax]['pending_transfer_to_supplier'] = $amounts['excess']->minus($amounts['transferred'])->value();
        }
        $report = ['status' => 'LOCAL_ACQUIRER_COLLECTION_RECONCILED', 'source_article' => 'LC214 art27 parágrafo únicoII; art36',
            'source_hash' => self::POLICY['source_hash'], 'input' => $input, 'taxes' => $result, 'receipts' => array_values($allocations),
            'other_extinctions' => $otherTrace,
            'option_exercised_in_supplied_receipts' => $taxes['IBS']['received']->plus($taxes['CBS']['received'])->compareTo($zero) > 0,
            'excess_rule' => 'TRANSFER_TO_SUPPLIER_ART36_PAR3_II', 'deadline_rule' => 'THREE_BUSINESS_DAYS',
            'deadline_date' => null, 'deadline_calendar_status' => 'QUALIFIED_OFFICIAL_CALENDAR_REQUIRED',
            'authenticated' => false, 'posted' => false, 'payment_performed' => false,
            'official' => false, 'extinction_status' => 'RECONCILED_FROM_SUPPLIED_RECEIPTS_NOT_POSTED'];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }

    /** @param array<string, mixed> $record
     * @param  array<string, mixed>  $input
     */
    private function bind(array $record, array $input): void
    {
        $this->require(in_array($record['tax'] ?? null, ['IBS', 'CBS'], true), 'A157_TAX_REQUIRED');
        foreach (['document_id', 'supplier_id', 'acquirer_id', 'payment_transaction_id', 'currency'] as $key) {
            $this->require(($record[$key] ?? null) === $input[$key], 'A157_OPERATION_LINK_CONFLICT:'.$key);
        }
    }

    private function money(mixed $value): DecimalValue
    {
        $this->require((new CreditEvidence156)->money($value), 'A157_CANONICAL_MONEY_REQUIRED');

        return DecimalValue::fromString((string) $value);
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && trim($value) === $value && (new CreditEvidence156)->reference($value)
            && preg_match('/\A\p{Z}|\p{Z}\z/u', $value) === 0;
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $this->require(is_string($value) && preg_match('/^\d{4}-\d\d-\d\dT(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value) === 1, 'A157_TIMESTAMP_REQUIRED');
        try {
            $date = new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new InvalidArgumentException('A157_TIMESTAMP_INVALID');
        }
        $this->require(DateTimeImmutable::getLastErrors() === false, 'A157_TIMESTAMP_INVALID');

        return $date;
    }

    /** @phpstan-assert true $condition */
    private function require(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($reason);
        }
    }
}
