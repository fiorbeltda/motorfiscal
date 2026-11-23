<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Source-bound refund allocations and clocks; supplied authority evidence is not authenticated. */
final readonly class RefundDeadline164
{
    public const CONTRACT = 'RTC-REFUND-DEADLINES-A164-V1';

    public const SOURCES = ['CBS' => '8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235',
        'IBS' => '9ed7032ef9c25bbee51bdb5e90f60e1ad01a2bf56cb41a28221c3db7f61d419a'];

    /** @param list<array<string, mixed>> $calendars */
    public function __construct(private array $calendars = []) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        try {
            return $this->calculate($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(), 'official' => false, 'posted' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null) && CanonicalJson::hash($this->assess($report['input'])) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function calculate(array $input): array
    {
        $g = new SplitLegalEvidence162;
        $g->envelope($input, ['contract', 'currency', 'source_kind', 'source_hash', 'evidence_id', 'input_hash', 'known_at',
            'procedure', 'coverage', 'context_report', 'requests'], self::CONTRACT);
        $g->must(in_array($input['procedure'], ['STANDARD_FALLBACK', 'ACQUIRER_EXCESS'], true)
            && in_array($input['coverage'], ['SELECTED_EXCESSES', 'FULL_EXCESSES'], true), 'A164_PROCEDURE_OR_COVERAGE_REQUIRED');
        $known = $g->instant($input['known_at']);
        $context = $input['context_report'];
        $g->must(is_array($context) && is_array($context['input'] ?? null), 'A164_VALIDATED_CONTEXT_REQUIRED');
        $book = $context['input'];
        if ($input['procedure'] === 'ACQUIRER_EXCESS') {
            $valid = (new AcquirerCollection157)->replay($context) && ($context['status'] ?? null) === 'LOCAL_ACQUIRER_COLLECTION_RECONCILED';
        } else {
            $ledger = new SplitLedger158;
            $valid = match ($book['contract'] ?? null) {
                SplitLedger158::CONTRACT => $ledger->replay($context),
                SplitLedger158::SCHEDULE_CONTRACT => $ledger->replaySchedule($context),
                SplitLedger158::LIFECYCLE_CONTRACT => $ledger->replayLifecycle($context),
                SplitLedger158::PAYMENTS_CONTRACT => $ledger->replayPayments($context),
                default => false,
            };
            $valid = $valid && ($context['status'] ?? null) === 'LOCAL_SPLIT_LEDGER_RECONCILED';
        }
        $g->must($valid, 'A164_CONTEXT_REPLAY_CONFLICT');
        $g->must($g->instant($book['known_at']) == $known, 'A164_CONTEXT_KNOWLEDGE_CONFLICT');
        $g->must($book['source_kind'] !== 'SYNTHETIC_TEST' || $input['source_kind'] === 'SYNTHETIC_TEST', 'A164_MIXED_PROVENANCE');
        $g->must(is_array($input['requests']) && array_is_list($input['requests']) && count($input['requests']) >= 1
            && count($input['requests']) <= 500, 'A164_REQUESTS_REQUIRED');
        $zero = BigDecimal::of('0.00');
        $excesses = [];
        if ($input['procedure'] === 'ACQUIRER_EXCESS') {
            foreach ($context['receipts'] as $receipt) {
                if ($g->money($receipt['excess'])->isPositive()) {
                    $excesses['#'.$receipt['collection_payment_id']] = $receipt;
                    $excesses['#'.$receipt['collection_payment_id']]['amount'] = $receipt['excess'];
                }
            }
        } else {
            foreach ($book['excess_determinations'] as $excess) {
                if ($g->money($excess['amount'])->isPositive()) {
                    $excesses['#'.$excess['id']] = $excess;
                }
            }
        }
        $assigned = [];
        $paymentAssigned = [];
        $transferAssigned = [];
        $seen = [];
        $pairs = [];
        $assessments = [];
        foreach ($input['requests'] as $request) {
            $g->must(is_array($request), 'A164_REQUEST_RECORD_REQUIRED');
            $g->fields($request, ['id', 'tax', 'excess_reference', 'payment_reference', 'amount', 'notice', 'calendar_id', 'synthetic_calendar', 'transfer_allocations']);
            $id = $g->id($request['id']);
            $g->must(! in_array($id, $seen, true), 'A164_DUPLICATE_REQUEST');
            $seen[] = $id;
            $tax = $request['tax'];
            $g->must(in_array($tax, ['IBS', 'CBS'], true), 'A164_TAX_REQUIRED');
            $excessKey = '#'.$g->id($request['excess_reference']);
            $paymentReference = $g->id($request['payment_reference']);
            $g->must(isset($excesses[$excessKey]) && $excesses[$excessKey]['tax'] === $tax, 'A164_EXCESS_REFERENCE_CONFLICT');
            $amount = $g->money($request['amount']);
            $g->must($amount->isPositive(), 'A164_POSITIVE_REFUND_ALLOCATION_REQUIRED');
            $pair = CanonicalJson::hash([$excessKey, $paymentReference]);
            $g->must(! in_array($pair, $pairs, true), 'A164_DUPLICATE_PAYMENT_ALLOCATION');
            $pairs[] = $pair;
            $assigned[$excessKey] = ($assigned[$excessKey] ?? $zero)->plus($amount);
            $g->must($assigned[$excessKey]->compareTo($g->money($excesses[$excessKey]['amount'])) <= 0, 'A164_REFUND_ALLOCATION_EXCEEDS_EXCESS');
            [$paymentAt, $transaction] = $this->payment($input['procedure'], $paymentReference, $request['excess_reference'], $tax, $book);
            if ($transaction !== null) {
                $service = new SplitTransaction158;
                $paymentReport = ($transaction['contract'] ?? null) === SplitTransaction158::HISTORY_CONTRACT
                    ? $service->assessHistory($transaction) : $service->assess($transaction);
                $paymentKey = CanonicalJson::hash([$paymentReference, $tax]);
                $paymentAssigned[$paymentKey] = ($paymentAssigned[$paymentKey] ?? $zero)->plus($amount);
                $g->must($paymentAssigned[$paymentKey]->compareTo($g->money($paymentReport['segregation']['taxes'][$tax]['segregated'])) <= 0,
                    'A164_REFUND_ALLOCATION_EXCEEDS_PAYMENT_SEGREGATION');
            }
            $notice = $request['notice'];
            $g->must(is_array($notice), 'A164_AUTHORITY_NOTICE_REQUIRED');
            $g->fields($notice, ['authority', 'procedure', 'supplier_id', 'document_id', 'tax', 'payment_reference', 'excess_reference',
                'amount', 'information_received_at', 'delayed_information', 'delay_evidence_id', 'delay_source_hash',
                'correct_linked_at', 'correct_link_evidence_id', 'correct_link_source_hash', 'consultation_failure', 'recorded_at', 'evidence_id', 'source_hash']);
            $g->proof($notice);
            foreach (['tax', 'payment_reference', 'excess_reference', 'amount'] as $key) {
                $g->must($notice[$key] === $request[$key], 'A164_AUTHORITY_ALLOCATION_CONFLICT');
            }
            $g->must($notice['authority'] === ($tax === 'CBS' ? 'RFB' : 'CGIBS') && $notice['procedure'] === $input['procedure']
                && $notice['supplier_id'] === $book['supplier_id'] && $notice['document_id'] === $book['document_id'], 'A164_AUTHORITY_SCOPE_CONFLICT');
            $information = $g->instant($notice['information_received_at']);
            $recorded = $g->instant($notice['recorded_at']);
            $g->must($information >= $paymentAt && $information <= $recorded && $recorded <= $known
                && is_bool($notice['delayed_information']), 'A164_INFORMATION_CHRONOLOGY_CONFLICT');
            if ($notice['delayed_information']) {
                $g->id($notice['delay_evidence_id']);
                $g->proof(['evidence_id' => $notice['delay_evidence_id'], 'source_hash' => $notice['delay_source_hash']]);
            } else {
                $g->must($notice['delay_evidence_id'] === null && $notice['delay_source_hash'] === null, 'A164_UNDECLARED_DELAY_EVIDENCE');
            }
            $trigger = $notice['delayed_information'] ? $information : $paymentAt;
            if ($input['procedure'] === 'STANDARD_FALLBACK') {
                $g->must(is_array($transaction), 'A164_STANDARD_TRANSACTION_REQUIRED');
                $link = $g->instant($notice['correct_linked_at']);
                $g->proof(['evidence_id' => $notice['correct_link_evidence_id'], 'source_hash' => $notice['correct_link_source_hash']]);
                $g->must($link >= $g->instant($transaction['document_link']['linked_at']) && $link <= $recorded, 'A164_CORRECT_LINK_CHRONOLOGY_CONFLICT');
                $failure = $notice['consultation_failure'];
                $g->must(is_array($failure), 'A164_FAILED_CONSULTATION_REQUIRED');
                $g->fields($failure, ['at', 'evidence_id', 'source_hash']);
                $g->proof($failure);
                $failedAt = $g->instant($failure['at']);
                $g->must($failedAt >= $g->instant($transaction['initiated_at']) && $failedAt <= $paymentAt, 'A164_CONSULTATION_FAILURE_CHRONOLOGY_CONFLICT');
                $trigger = max($trigger, $link);
            } else {
                $g->must($notice['correct_linked_at'] === null && $notice['correct_link_evidence_id'] === null
                    && $notice['correct_link_source_hash'] === null && $notice['consultation_failure'] === null, 'A164_ACQUIRER_NOT_STANDARD_FALLBACK');
            }
            $calendarRecord = $this->calendar($request, $input['source_kind']);
            $calendar = new SplitCalendar163($calendarRecord, 'TAX_AUTHORITY_EXPEDIENT', $tax, $known, $input['source_kind'] === 'SYNTHETIC_TEST');
            $counted = $calendar->following($trigger->setTimezone(new DateTimeZone('Etc/GMT+3'))->format('Y-m-d'), 3);
            $deadline = $counted[2];
            $g->must(is_array($request['transfer_allocations']) && array_is_list($request['transfer_allocations']), 'A164_TRANSFER_ALLOCATIONS_REQUIRED');
            $transferred = $zero;
            $transfers = [];
            foreach ($request['transfer_allocations'] as $allocation) {
                $g->must(is_array($allocation), 'A164_TRANSFER_ALLOCATION_REQUIRED');
                $g->fields($allocation, ['transfer_id', 'amount']);
                $transferId = $g->id($allocation['transfer_id']);
                $rows = array_values(array_filter($book['transfers'], static fn (array $row): bool => $row['id'] === $transferId));
                $g->must(count($rows) === 1, 'A164_TRANSFER_REFERENCE_CONFLICT');
                $row = $rows[0];
                $referenceField = $input['procedure'] === 'ACQUIRER_EXCESS' ? 'collection_payment_id' : 'determination_id';
                $g->must($row[$referenceField] === $request['excess_reference'] && $row['tax'] === $tax, 'A164_TRANSFER_EXCESS_CONFLICT');
                $value = $g->money($allocation['amount']);
                $g->must($value->isPositive(), 'A164_POSITIVE_TRANSFER_ALLOCATION_REQUIRED');
                $transferKey = '#'.$transferId;
                $g->must(! in_array($transferId, array_column($transfers, 'transfer_id'), true), 'A164_DUPLICATE_TRANSFER_IN_REQUEST');
                $transferAssigned[$transferKey] = ($transferAssigned[$transferKey] ?? $zero)->plus($value);
                $g->must($transferAssigned[$transferKey]->compareTo($g->money($row['amount'])) <= 0, 'A164_TRANSFER_ALLOCATION_EXCEEDS_RECEIPT');
                $transferred = $transferred->plus($value);
                $g->must($transferred->compareTo($amount) <= 0, 'A164_TRANSFER_ALLOCATION_EXCEEDS_REFUND');
                $at = $g->instant($row['transferred_at']);
                $g->must($at >= $paymentAt && $at >= $trigger, 'A164_TRANSFER_BEFORE_ELIGIBILITY');
                $transfers[] = ['transfer_id' => $transferId, 'amount' => (string) $value, 'at' => $row['transferred_at'],
                    'timing' => $at->setTimezone(new DateTimeZone('Etc/GMT+3'))->format('Y-m-d') > $deadline ? 'LATE_DATE' : 'WITHIN_DATE'];
            }
            $pending = $amount->minus($transferred);
            $assessments[] = ['request_id' => $id, 'tax' => $tax, 'excess_reference' => $request['excess_reference'], 'payment_reference' => $paymentReference,
                'refund_allocated' => (string) $amount, 'transferred_allocated' => (string) $transferred,
                'amount_without_transfer_allocation' => (string) $pending,
                'pending_transfer' => $input['coverage'] === 'FULL_EXCESSES' ? (string) $pending : null,
                'notice_hash' => CanonicalJson::hash($notice), 'calendar_hash' => CanonicalJson::hash($calendarRecord),
                'clock_started_at' => $trigger->format('Y-m-d\TH:i:s.uP'), 'counted_business_dates' => $counted,
                'deadline_date' => $deadline, 'deadline_at' => null, 'precision' => 'DATE', 'transfers' => $transfers,
                'pending_timing' => ! $pending->isPositive() ? 'NO_PENDING_ALLOCATED_AMOUNT'
                    : ($input['coverage'] === 'SELECTED_EXCESSES' ? 'NOT_DETERMINABLE_FROM_SELECTED_ALLOCATIONS'
                        : ($known->setTimezone(new DateTimeZone('Etc/GMT+3'))->format('Y-m-d') > $deadline ? 'LATE_DATE' : 'WITHIN_DATE')),
                'rule_source_hash' => self::SOURCES[$tax], 'rule_article' => $input['procedure'] === 'STANDARD_FALLBACK'
                    ? ($notice['delayed_information'] ? 'ART29_PAR6' : 'ART29_PAR5_II_B') : ($notice['delayed_information'] ? 'ART36_PAR4' : 'ART36_PAR3_II')];
        }
        if ($input['coverage'] === 'FULL_EXCESSES') {
            foreach ($excesses as $key => $excess) {
                $g->must(($assigned[$key] ?? $zero)->compareTo($g->money($excess['amount'])) === 0, 'A164_FULL_EXCESS_COVERAGE_REQUIRED');
            }
            foreach ($book['transfers'] as $row) {
                $g->must(($transferAssigned['#'.$row['id']] ?? $zero)->compareTo($g->money($row['amount'])) === 0, 'A164_FULL_TRANSFER_COVERAGE_REQUIRED');
            }
        }
        $report = ['status' => 'LOCAL_REFUND_DEADLINES_ASSESSED', 'input' => $input, 'coverage' => $input['coverage'],
            'assessments' => $assessments, 'context_report_hash' => $context['report_hash'], 'official' => false,
            'authenticated' => false, 'posted' => false, 'payment_performed' => false, 'legal_liability_adjudicated' => false];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }

    /** @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function calendar(array $request, string $sourceKind): array
    {
        $g = new SplitLegalEvidence162;
        $records = $this->calendars;
        if ($request['synthetic_calendar'] !== null) {
            $candidate = $request['synthetic_calendar'];
            $g->must($sourceKind === 'SYNTHETIC_TEST' && is_array($candidate) && ($candidate['source_kind'] ?? null) === 'SYNTHETIC_TEST', 'A164_SYNTHETIC_CALENDAR_NOT_OFFICIAL');
            $records[] = $candidate;
        }
        $ids = [];
        $selected = null;
        $calendarId = $g->id($request['calendar_id']);
        foreach ($records as $record) {
            $id = $g->id($record['id'] ?? null);
            $g->must(! in_array($id, $ids, true), 'A164_CALENDAR_ID_AMBIGUOUS');
            $ids[] = $id;
            if ($id === $calendarId) {
                $selected = $record;
            }
        }
        $g->must(is_array($selected), 'A164_QUALIFIED_CALENDAR_REQUIRED');

        return $selected;
    }

    /** @param array<string, mixed> $book
     * @return array{0: DateTimeImmutable, 1: array<string, mixed>|null}
     */
    private function payment(string $procedure, string $reference, string $excessReference, string $tax, array $book): array
    {
        $g = new SplitLegalEvidence162;
        if ($procedure === 'ACQUIRER_EXCESS') {
            $rows = array_values(array_filter($book['payments'], static fn (array $row): bool => $row['id'] === $reference && $row['tax'] === $tax));
            $g->must($reference === $excessReference && count($rows) === 1, 'A164_ACQUIRER_PAYMENT_REFERENCE_CONFLICT');

            return [$g->instant($rows[0]['settled_at']), null];
        }
        $matches = [];
        foreach ($book['transactions'] as $container) {
            $payments = ($container['contract'] ?? null) === SplitBoletoPayments161::CONTRACT
                ? $container['payments'] : [['transaction' => $container, 'payment_id' => null]];
            foreach ($payments as $payment) {
                if (($payment['transaction']['settlement']['receipt_id'] ?? null) === $reference) {
                    $matches[] = $payment;
                }
            }
        }
        $g->must(count($matches) === 1, 'A164_STANDARD_PAYMENT_REFERENCE_CONFLICT');
        $payment = $matches[0];
        $tx = $payment['transaction'];
        $excess = array_values(array_filter($book['excess_determinations'], static fn (array $row): bool => $row['id'] === $excessReference))[0];
        $remit = array_values(array_filter($book['remittances'], static fn (array $row): bool => $row['id'] === $excess['remittance_id']))[0];
        $linked = false;
        foreach ($book['reports'] as $segregation) {
            if (! in_array($segregation['id'], $remit['report_ids'], true)) {
                continue;
            }
            if ($payment['payment_id'] === null && in_array($tx['transaction_id'], $segregation['transaction_ids'] ?? [], true)) {
                $linked = true;
            }
            foreach ($segregation['payment_references'] ?? [] as $ref) {
                if ($ref['transaction_id'] === $tx['transaction_id'] && $ref['payment_id'] === $payment['payment_id']) {
                    $linked = true;
                }
            }
        }
        $g->must($linked, 'A164_PAYMENT_NOT_IN_EXCESS_REMITTANCE');

        return [$g->instant($tx['settlement']['settled_at']), $tx];
    }
}
