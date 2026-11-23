<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AcquirerCollection157;
use App\Modules\Fiscal\ConsumptionReform\RefundDeadline164;
use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;

require_once __DIR__.'/split163_fixture.php';
require_once __DIR__.'/collection157_fixture.php';
require_once __DIR__.'/split161_fixture.php';

/** @return array<string, mixed> */
function refund164Input(string $procedure = 'STANDARD_FALLBACK', bool $delayed = false): array
{
    $book = $procedure === 'ACQUIRER_EXCESS' ? collection157Ledger() : split158Ledger();
    $book['known_at'] = '2026-11-23T16:00:00Z';
    foreach ($book['transactions'] ?? [] as $index => $transaction) {
        $transaction['known_at'] = $book['known_at'];
        $book['transactions'][$index] = seal158($transaction);
    }
    $book = seal158($book);
    if ($delayed && $procedure === 'ACQUIRER_EXCESS') {
        $book['transfers'][0]['transferred_at'] = '2026-10-09T12:00:00Z';
        $book = seal157($book);
    }
    $context = $procedure === 'ACQUIRER_EXCESS' ? (new AcquirerCollection157)->reconcile($book) : (new SplitLedger158)->reconcile($book);

    return refund164FromContext($context, $procedure, $delayed);
}

/** @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function refund164FromContext(array $context, string $procedure, bool $delayed = false): array
{
    $book = $context['input'];
    $input = ['contract' => RefundDeadline164::CONTRACT, 'currency' => 'BRL', 'source_kind' => 'SYNTHETIC_TEST',
        'source_hash' => hash('sha256', 'A164 synthetic refund view'), 'evidence_id' => 'SYNTHETIC.A164.VIEW',
        'known_at' => $book['known_at'], 'procedure' => $procedure, 'coverage' => 'FULL_EXCESSES', 'context_report' => $context, 'requests' => []];
    $rows = $procedure === 'ACQUIRER_EXCESS' ? array_values(array_filter($context['receipts'], static fn (array $row): bool => $row['excess'] !== '0.00')) : $book['excess_determinations'];
    foreach ($rows as $row) {
        $tax = $row['tax'];
        $excess = $procedure === 'ACQUIRER_EXCESS' ? $row['collection_payment_id'] : $row['id'];
        $first = $book['transactions'][0] ?? [];
        $payment = $procedure === 'ACQUIRER_EXCESS' ? $excess
            : ($first['settlement']['receipt_id'] ?? $first['payments'][0]['transaction']['settlement']['receipt_id']);
        $amount = $procedure === 'ACQUIRER_EXCESS' ? $row['excess'] : $row['amount'];
        $request = ['id' => 'SYNTHETIC.REFUND.'.$excess, 'tax' => $tax, 'excess_reference' => $excess, 'payment_reference' => $payment, 'amount' => $amount,
            'notice' => ['authority' => $tax === 'CBS' ? 'RFB' : 'CGIBS', 'procedure' => $procedure, 'supplier_id' => $book['supplier_id'],
                'document_id' => $book['document_id'], 'tax' => $tax, 'payment_reference' => $payment, 'excess_reference' => $excess, 'amount' => $amount,
                'information_received_at' => $delayed ? '2026-10-08T12:00:00Z' : '2026-10-03T12:00:00Z', 'delayed_information' => $delayed,
                'delay_evidence_id' => $delayed ? 'SYNTHETIC.DELAY.'.$excess : null,
                'delay_source_hash' => $delayed ? hash('sha256', 'A164 supplied documented late information') : null,
                'correct_linked_at' => $procedure === 'STANDARD_FALLBACK' ? '2026-10-05T12:00:00Z' : null,
                'correct_link_evidence_id' => $procedure === 'STANDARD_FALLBACK' ? 'SYNTHETIC.CORRECT.LINK.'.$excess : null,
                'correct_link_source_hash' => $procedure === 'STANDARD_FALLBACK' ? hash('sha256', 'A164 supplied authority correct link') : null,
                'consultation_failure' => $procedure === 'STANDARD_FALLBACK' ? split163Event('SYNTHETIC.FAILURE.'.$excess, '2026-10-02T12:01:00Z') : null,
                'recorded_at' => $delayed ? '2026-10-09T12:00:00Z' : '2026-10-05T13:00:00Z',
                'evidence_id' => 'SYNTHETIC.NOTICE.'.$excess, 'source_hash' => hash('sha256', 'A164 supplied notice '.$excess)],
            'calendar_id' => 'SYNTHETIC.CALENDAR.A163', 'synthetic_calendar' => split163Calendar($tax, 'TAX_AUTHORITY_EXPEDIENT'), 'transfer_allocations' => []];
        foreach ($book['transfers'] as $transfer) {
            $field = $procedure === 'ACQUIRER_EXCESS' ? 'collection_payment_id' : 'determination_id';
            if ($transfer[$field] === $excess) {
                $request['transfer_allocations'][] = ['transfer_id' => $transfer['id'], 'amount' => $transfer['amount']];
            }
        }
        $input['requests'][] = $request;
    }

    return seal158($input);
}
