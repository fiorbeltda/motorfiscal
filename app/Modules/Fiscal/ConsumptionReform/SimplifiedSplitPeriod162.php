<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** LC214 art33 period priorities and supplied authority events; proposals never become postings. */
final readonly class SimplifiedSplitPeriod162
{
    public const CONTRACT = 'RTC-SIMPLIFIED-SPLIT-PERIOD-A162-V1';

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function reconcile(array $input): array
    {
        try {
            return $this->calculate($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(), 'official' => false,
                'posted' => false, 'payment_performed' => false, 'credit_generated' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null) && CanonicalJson::hash($this->reconcile($report['input'])) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function calculate(array $input): array
    {
        $g = new SplitLegalEvidence162;
        $g->envelope($input, ['contract', 'currency', 'source_kind', 'source_hash', 'evidence_id', 'input_hash', 'supplier_id',
            'period_start', 'period_end', 'as_of', 'known_at', 'phase', 'period_closed_at', 'closure_evidence_id', 'closure_source_hash',
            'debts_complete', 'receipts_complete', 'events_complete', 'opening_excludes_current_receipts',
            'ordering_evidence_id', 'ordering_source_hash', 'document_order', 'debts', 'receipts', 'events'], self::CONTRACT);
        $supplier = $g->id($input['supplier_id']);
        $start = $g->date($input['period_start']);
        $end = $g->date($input['period_end']);
        $zone = new DateTimeZone('America/Sao_Paulo');
        $period = new DateTimeImmutable($start.'T00:00:00', $zone);
        $g->must(substr($start, -2) === '01' && $period->modify('last day of this month')->format('Y-m-d') === $end, 'A162_MONTHLY_PERIOD_REQUIRED');
        $asOf = $g->instant($input['as_of']);
        $known = $g->instant($input['known_at']);
        $g->must($period <= $asOf && $asOf <= $known, 'A162_PERIOD_KNOWLEDGE_CONFLICT');
        $closed = null;
        if ($input['phase'] === 'CLOSED') {
            $closed = $g->instant($input['period_closed_at']);
            $g->must($closed >= $period->modify('first day of next month') && $closed <= $asOf, 'A162_PERIOD_CLOSURE_DATE_CONFLICT');
            $g->proof(['evidence_id' => $input['closure_evidence_id'], 'source_hash' => $input['closure_source_hash']]);
        } else {
            $g->must($input['phase'] === 'DURING_PERIOD' && $asOf < $period->modify('first day of next month')
                && $input['period_closed_at'] === null && $input['closure_evidence_id'] === null && $input['closure_source_hash'] === null, 'A162_PERIOD_PHASE_CONFLICT');
        }
        foreach (['debts_complete', 'receipts_complete', 'events_complete', 'opening_excludes_current_receipts'] as $key) {
            $g->must($input[$key] === true, 'A162_COMPLETE_PERIOD_BOOK_REQUIRED');
        }
        foreach (['document_order', 'debts', 'receipts', 'events'] as $key) {
            $g->must(is_array($input[$key]) && array_is_list($input[$key]), 'A162_PERIOD_LIST_REQUIRED');
        }
        $g->proof(['evidence_id' => $input['ordering_evidence_id'], 'source_hash' => $input['ordering_source_hash']]);
        $receiptsSeen = [];
        $debts = [];
        foreach ($input['debts'] as $debt) {
            $g->must(is_array($debt), 'A162_DEBT_RECORD_REQUIRED');
            $g->fields($debt, ['document_id', 'operation_id', 'supplier_id', 'acquirer_regime', 'document_issued_at', 'opening_at',
                'operation_on', 'debits', 'previously_extinguished', 'prior_receipt_ids', 'evidence_id', 'source_hash']);
            $g->proof($debt);
            $id = $g->id($debt['document_id'] ?? null);
            $g->must(! isset($debts['#'.$id]) && ($debt['supplier_id'] ?? null) === $supplier
                && in_array($debt['acquirer_regime'] ?? null, ['REGULAR', 'NON_REGULAR'], true), 'A162_DEBT_PARTY_OR_DUPLICATE_CONFLICT');
            $g->id($debt['operation_id'] ?? null);
            $issued = $g->instant($debt['document_issued_at'] ?? null);
            $opening = $g->instant($debt['opening_at'] ?? null);
            $operation = $g->date($debt['operation_on'] ?? null);
            $g->must($issued <= $opening && $opening <= $asOf && $operation <= $end
                && $operation <= $opening->setTimezone($zone)->format('Y-m-d'), 'A162_DEBT_DATE_CONFLICT');
            $nominal = $g->taxes($debt['debits'] ?? null);
            $prior = $g->taxes($debt['previously_extinguished'] ?? null);
            $priorIds = $debt['prior_receipt_ids'] ?? null;
            $g->must(is_array($priorIds) && array_is_list($priorIds), 'A162_PRIOR_RECEIPTS_REQUIRED');
            foreach ($priorIds as $priorId) {
                $this->unique($priorId, $receiptsSeen);
            }
            foreach (['IBS', 'CBS'] as $tax) {
                $g->must($prior[$tax]->compareTo($nominal[$tax]) <= 0 && (! $prior[$tax]->isPositive() || $priorIds !== []), 'A162_PRIOR_EXTINCTION_CONFLICT');
            }
            $remaining = ['IBS' => $nominal['IBS']->minus($prior['IBS']), 'CBS' => $nominal['CBS']->minus($prior['CBS'])];
            $debts['#'.$id] = ['input' => $debt, 'issued' => $issued, 'opening' => $opening, 'remaining' => $remaining,
                'priority' => $operation >= $start && $debt['acquirer_regime'] === 'NON_REGULAR' ? 1 : 2];
        }
        $ordered = [];
        $lastIssue = null;
        foreach ($input['document_order'] as $id) {
            $key = '#'.$g->id($id);
            $g->must(isset($debts[$key]) && ! isset($ordered[$key]), 'A162_DOCUMENT_ORDER_CONFLICT');
            $record = $debts[$key];
            $g->must($lastIssue === null || $record['issued'] >= $lastIssue, 'A162_DOCUMENT_ORDER_NOT_CHRONOLOGICAL');
            $ordered[$key] = $record;
            $lastIssue = $record['issued'];
        }
        $g->must(count($ordered) === count($debts), 'A162_DOCUMENT_ORDER_INCOMPLETE');
        $debts = $ordered;
        $funds = [];
        $fundIds = [];
        foreach ($input['receipts'] as $receipt) {
            $g->must(is_array($receipt), 'A162_RECEIPT_RECORD_REQUIRED');
            $g->fields($receipt, ['id', 'supplier_id', 'period', 'received', 'collected_at', 'amounts', 'authority_receipt_id', 'evidence_id', 'source_hash']);
            $g->proof($receipt);
            $this->unique($receipt['id'] ?? null, $fundIds);
            $this->unique($receipt['authority_receipt_id'] ?? null, $receiptsSeen);
            $at = $g->instant($receipt['collected_at'] ?? null);
            $g->must(($receipt['supplier_id'] ?? null) === $supplier && ($receipt['period'] ?? null) === substr($start, 0, 7)
                && ($receipt['received'] ?? null) === true && $at <= $asOf
                && $at->setTimezone($zone)->format('Y-m-d') >= $start && $at->setTimezone($zone)->format('Y-m-d') <= $end, 'A162_RECEIPT_PERIOD_OR_PARTY_CONFLICT');
            $amounts = $g->taxes($receipt['amounts'] ?? null);
            $g->must($amounts['IBS']->plus($amounts['CBS'])->isPositive(), 'A162_POSITIVE_RECEIPT_REQUIRED');
            $funds[] = ['at' => $at, 'amounts' => $amounts];
        }
        $zero = BigDecimal::of('0.00');
        $applied = $reserved = $transferred = ['IBS' => $zero, 'CBS' => $zero];
        $determinations = [];
        $events = [];
        $eventIds = [];
        $previousAt = null;
        foreach ($input['events'] as $index => $event) {
            $g->must(is_array($event) && ($event['sequence'] ?? null) === $index + 1, 'A162_AUTHORITY_EVENT_ORDER_REQUIRED');
            $g->fields($event, ['id', 'sequence', 'kind', 'tax', 'amount', 'at', 'supplier_id', 'document_id', 'determination_id',
                'beneficiary_id', 'authority_receipt_id', 'evidence_id', 'source_hash']);
            $g->proof($event);
            $id = $g->id($event['id'] ?? null);
            $this->unique($id, $eventIds);
            $this->unique($event['authority_receipt_id'] ?? null, $receiptsSeen);
            $tax = $event['tax'] ?? null;
            $g->must(in_array($tax, ['IBS', 'CBS'], true) && ($event['supplier_id'] ?? null) === $supplier, 'A162_EVENT_TAX_OR_PARTY_CONFLICT');
            $at = $g->instant($event['at'] ?? null);
            $g->must($at <= $asOf && ($previousAt === null || $at >= $previousAt), 'A162_AUTHORITY_EVENT_DATE_CONFLICT');
            $previousAt = $at;
            $amount = $g->money($event['amount'] ?? null);
            $g->must($amount->isPositive(), 'A162_POSITIVE_EVENT_REQUIRED');
            $available = $this->cash($funds, $tax, $at)->minus($applied[$tax])->minus($reserved[$tax]);
            if (($event['kind'] ?? null) === 'APPLICATION') {
                $g->must($event['determination_id'] === null && $event['beneficiary_id'] === null, 'A162_APPLICATION_SCOPE_CONFLICT');
                $keys = $this->eligible($debts, $at, $closed, $tax);
                $key = '#'.$g->id($event['document_id'] ?? null);
                $g->must(isset($debts[$key]) && ($keys[0] ?? null) === $key, 'A162_APPLICATION_PRIORITY_CONFLICT');
                $g->must($amount->compareTo($available) <= 0 && $amount->compareTo($debts[$key]['remaining'][$tax]) <= 0, 'A162_APPLICATION_EXCEEDS_FUNDS_OR_DEBIT');
                $debts[$key]['remaining'][$tax] = $debts[$key]['remaining'][$tax]->minus($amount);
                $applied[$tax] = $applied[$tax]->plus($amount);
            } elseif (($event['kind'] ?? null) === 'EXCESS_DETERMINATION') {
                $g->must($event['document_id'] === null && $event['determination_id'] === null && $event['beneficiary_id'] === null, 'A162_EXCESS_SCOPE_CONFLICT');
                $g->must($closed !== null && $at >= $closed && $this->eligible($debts, $at, $closed, $tax) === []
                    && $amount->compareTo($available) <= 0, 'A162_EXCESS_NOT_ESTABLISHED');
                $reserved[$tax] = $reserved[$tax]->plus($amount);
                $determinations['#'.$id] = ['tax' => $tax, 'at' => $at, 'amount' => $amount, 'transferred' => $zero];
            } elseif (($event['kind'] ?? null) === 'TRANSFER') {
                $g->must($event['document_id'] === null, 'A162_TRANSFER_SCOPE_CONFLICT');
                $key = '#'.$g->id($event['determination_id'] ?? null);
                $g->must(isset($determinations[$key]) && $determinations[$key]['tax'] === $tax
                    && $at >= $determinations[$key]['at'] && ($event['beneficiary_id'] ?? null) === $supplier, 'A162_TRANSFER_SCOPE_CONFLICT');
                $determinations[$key]['transferred'] = $determinations[$key]['transferred']->plus($amount);
                $g->must($determinations[$key]['transferred']->compareTo($determinations[$key]['amount']) <= 0, 'A162_TRANSFER_EXCEEDS_EXCESS');
                $transferred[$tax] = $transferred[$tax]->plus($amount);
            } else {
                throw new InvalidArgumentException('A162_AUTHORITY_EVENT_KIND_REQUIRED');
            }
            $events[] = ['id' => $id, 'kind' => $event['kind'], 'tax' => $tax, 'amount' => (string) $amount, 'posted' => false];
        }
        $balances = [];
        $proposal = [];
        foreach (['IBS', 'CBS'] as $tax) {
            $received = $this->cash($funds, $tax, $asOf);
            $unused = $received->minus($applied[$tax])->minus($reserved[$tax]);
            $budget = $unused;
            foreach ($this->eligible($debts, $asOf, $closed, $tax) as $key) {
                $amount = $budget->compareTo($debts[$key]['remaining'][$tax]) < 0 ? $budget : $debts[$key]['remaining'][$tax];
                if ($amount->isPositive()) {
                    $proposal[] = ['document_id' => $debts[$key]['input']['document_id'], 'tax' => $tax,
                        'amount' => (string) $amount, 'priority' => $debts[$key]['priority'], 'applied' => false];
                    $budget = $budget->minus($amount);
                }
            }
            $balances[$tax] = ['received' => (string) $received, 'applied_from_supplied_records' => (string) $applied[$tax],
                'excess_determined' => (string) $reserved[$tax], 'transferred_to_supplier' => (string) $transferred[$tax],
                'pending_transfer' => (string) $reserved[$tax]->minus($transferred[$tax]), 'unreserved_cash' => (string) $unused,
                'proposed_for_debts' => (string) $unused->minus($budget), 'unreserved_after_proposal' => (string) $budget,
                'potential_excess_after_proposal' => $closed === null ? null : (string) $budget];
        }
        $remaining = [];
        foreach ($debts as $record) {
            $remaining[] = ['document_id' => $record['input']['document_id'], 'priority' => $record['priority'],
                'IBS' => (string) $record['remaining']['IBS'], 'CBS' => (string) $record['remaining']['CBS']];
        }
        $report = ['status' => 'LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED', 'input' => $input,
            'law_hash' => SplitLegalEvidence162::LAW_HASH, 'phase' => $input['phase'], 'balances' => $balances,
            'allocation_proposal' => $proposal, 'remaining_debts' => $remaining, 'supplied_authority_events' => $events,
            'proposal_is_extinction' => false, 'appropriable_credit_from_segregation' => false,
            'extinction_status' => 'FROM_SUPPLIED_AUTHORITY_RECORDS_NOT_POSTED',
            'deadline_rule' => 'ART33_PAR4_THREE_BUSINESS_DAYS_FROM_APURATION_CONCLUSION', 'transfer_deadline' => null,
            'deadline_status' => 'QUALIFIED_CALENDAR_REQUIRED', 'official' => false, 'authenticated' => false,
            'posted' => false, 'payment_performed' => false];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }

    /** @param array<string, array<string, mixed>> $debts
     * @return list<string>
     */
    private function eligible(array $debts, DateTimeImmutable $at, ?DateTimeImmutable $closed, string $tax): array
    {
        $keys = [];
        foreach ([1, 2] as $priority) {
            if ($priority === 2 && ($closed === null || $at < $closed)) {
                continue;
            }
            foreach ($debts as $key => $debt) {
                if ($debt['priority'] === $priority && $debt['opening'] <= $at && $debt['remaining'][$tax]->isPositive()) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /** @param list<array{at: DateTimeImmutable, amounts: array{IBS: BigDecimal, CBS: BigDecimal}}> $funds */
    private function cash(array $funds, string $tax, DateTimeImmutable $at): BigDecimal
    {
        $amount = BigDecimal::of('0.00');
        foreach ($funds as $fund) {
            if ($fund['at'] <= $at) {
                $amount = $amount->plus($fund['amounts'][$tax]);
            }
        }

        return $amount;
    }

    /** @param array<string, bool> $seen */
    private function unique(mixed $value, array &$seen): void
    {
        $g = new SplitLegalEvidence162;
        $id = $g->id($value);
        $g->must(! isset($seen['#'.$id]), 'A162_DUPLICATE_ID_OR_RECEIPT');
        $seen['#'.$id] = true;
    }
}
