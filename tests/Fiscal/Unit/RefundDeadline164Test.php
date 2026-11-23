<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\RefundDeadline164;
use App\Modules\Fiscal\ConsumptionReform\SplitBoletoPayments161;
use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use Brick\Math\BigDecimal;

require_once dirname(__DIR__, 3).'/scripts/agent_a/refund164_fixture.php';

it('distinguishes fallback, acquirer and documented delay clocks for each tax', function (string $procedure, bool $delayed, string $deadline, string $article): void {
    $input = refund164Input($procedure, $delayed);
    if ($delayed && $procedure === 'STANDARD_FALLBACK') {
        // A supplied transfer cannot predate the corrected eligibility of this delayed case.
        $book = $input['context_report']['input'];
        $book['transfers'][0]['transferred_at'] = '2026-10-09T12:00:00Z';
        $input['context_report'] = (new SplitLedger158)->reconcile(seal158($book));
    }
    $report = (new RefundDeadline164)->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_REFUND_DEADLINES_ASSESSED');
    foreach ($report['assessments'] as $row) {
        expect($row['deadline_date'])->toBe($deadline)->and($row['deadline_at'])->toBeNull()
            ->and($row['rule_article'])->toBe($article)->and($row['rule_source_hash'])->toBe(RefundDeadline164::SOURCES[$row['tax']]);
    }
    expect($report['posted'])->toBeFalse()->and($report['authenticated'])->toBeFalse();
})->with([['STANDARD_FALLBACK', false, '2026-10-08', 'ART29_PAR5_II_B'], ['STANDARD_FALLBACK', true, '2026-10-14', 'ART29_PAR6'],
    ['ACQUIRER_EXCESS', false, '2026-10-07', 'ART36_PAR3_II'], ['ACQUIRER_EXCESS', true, '2026-10-14', 'ART36_PAR4']]);

it('uses the later correct public link without rewriting the supplied arrangement link', function (): void {
    $input = refund164Input();
    foreach ($input['requests'] as &$request) {
        $request['notice']['correct_linked_at'] = '2026-10-13T12:00:00Z';
        $request['notice']['recorded_at'] = '2026-10-13T13:00:00Z';
    }
    unset($request);
    $book = $input['context_report']['input'];
    $book['transfers'] = [];
    $input['context_report'] = (new SplitLedger158)->reconcile(seal158($book));
    $input['requests'][0]['transfer_allocations'] = [];
    $report = (new RefundDeadline164)->assess(seal158($input));
    expect($report['assessments'][0]['deadline_date'])->toBe('2026-10-16')
        ->and($report['input']['context_report']['input']['transactions'][0]['document_link']['linked_at'])->toBe($book['transactions'][0]['document_link']['linked_at']);
});

it('preserves allocated excess and transfer conservation across a consolidated remittance', function (): void {
    $input = refund164Input();
    $first = $input['requests'][0];
    $first['amount'] = $first['notice']['amount'] = '2.50';
    $first['transfer_allocations'][0]['amount'] = '1.00';
    $other = $first;
    $other['id'] = 'SYNTHETIC.OTHER.PAYMENT';
    $other['payment_reference'] = $other['notice']['payment_reference'] = $input['context_report']['input']['transactions'][1]['settlement']['receipt_id'];
    $input['requests'][0] = $first;
    $input['requests'][] = $other;
    $report = (new RefundDeadline164)->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_REFUND_DEADLINES_ASSESSED')
        ->and($report['assessments'][0]['pending_transfer'])->toBe('1.50')
        ->and($report['assessments'][2]['pending_transfer'])->toBe('1.50');
    $input['requests'][2]['amount'] = $input['requests'][2]['notice']['amount'] = '2.51';
    expect((new RefundDeadline164)->assess(seal158($input))['reason'])->toBe('A164_REFUND_ALLOCATION_EXCEEDS_EXCESS');
});

it('refuses unsupported allocation, false provenance and edited financial context', function (string $case): void {
    $input = refund164Input();
    match ($case) {
        'amount' => $input['requests'][0]['amount'] = '5.01',
        'notice amount' => $input['requests'][0]['notice']['amount'] = '4.99',
        'tax' => $input['requests'][0]['tax'] = 'CBS',
        'authority' => $input['requests'][0]['notice']['authority'] = 'RFB',
        'supplier' => $input['requests'][0]['notice']['supplier_id'] = 'OTHER',
        'document' => $input['requests'][0]['notice']['document_id'] = 'OTHER',
        'no failure' => $input['requests'][0]['notice']['consultation_failure'] = null,
        'late failure' => $input['requests'][0]['notice']['consultation_failure']['at'] = '2026-10-03T12:00:00Z',
        'no delay proof' => $input['requests'][0]['notice']['delayed_information'] = true,
        'future info' => $input['requests'][0]['notice']['information_received_at'] = '2026-12-01T12:00:00Z',
        'before payment' => $input['requests'][0]['notice']['information_received_at'] = '2026-10-01T12:00:00Z',
        'link before supplied' => $input['requests'][0]['notice']['correct_linked_at'] = '2026-09-30T12:00:00Z',
        'future link' => $input['requests'][0]['notice']['correct_linked_at'] = '2026-12-01T12:00:00Z',
        'payment' => $input['requests'][0]['payment_reference'] = 'OTHER',
        'excess' => $input['requests'][0]['excess_reference'] = 'OTHER',
        'transfer' => $input['requests'][0]['transfer_allocations'][0]['amount'] = '2.01',
        'wrong transfer' => $input['requests'][1]['transfer_allocations'] = $input['requests'][0]['transfer_allocations'],
        'missing excess' => array_pop($input['requests']),
        'omit transferred' => $input['requests'][0]['transfer_allocations'] = [],
        'context edit' => $input['context_report']['taxes']['IBS']['excess'] = '5.01',
        'knowledge' => $input['known_at'] = '2026-11-01T12:00:00Z',
        'documentary' => $input['source_kind'] = 'DOCUMENTARY_INPUT',
        'bank calendar' => $input['requests'][0]['synthetic_calendar'] = split163Calendar('IBS'),
        'missing calendar' => $input['requests'][0]['synthetic_calendar'] = null,
        'self official' => $input['requests'][0]['synthetic_calendar']['source_kind'] = 'OFFICIAL_PUBLISHED',
    };
    expect((new RefundDeadline164)->assess(seal158($input))['status'])->toBe('ACTION_REQUIRED');
})->with(['amount', 'notice amount', 'tax', 'authority', 'supplier', 'document', 'no failure', 'late failure', 'no delay proof',
    'future info', 'before payment', 'link before supplied', 'future link', 'payment', 'excess', 'transfer', 'wrong transfer',
    'missing excess', 'omit transferred', 'context edit', 'knowledge', 'documentary', 'bank calendar', 'missing calendar', 'self official']);

it('keeps selected coverage explicit and replays only the exact supplied context', function (): void {
    $service = new RefundDeadline164;
    $input = refund164Input();
    $input['coverage'] = 'SELECTED_EXCESSES';
    array_pop($input['requests']);
    $report = $service->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_REFUND_DEADLINES_ASSESSED')->and($report['coverage'])->toBe('SELECTED_EXCESSES')
        ->and($report['assessments'][0]['pending_transfer'])->toBeNull()
        ->and($report['assessments'][0]['pending_timing'])->toBe('NOT_DETERMINABLE_FROM_SELECTED_ALLOCATIONS')
        ->and($service->replay($report))->toBeTrue();
    $report['assessments'][0]['deadline_date'] = '2026-10-09';
    expect($service->replay($report))->toBeFalse();
});

it('rejects refunding a consolidated excess beyond the selected payment own segregation', function (): void {
    $book = split158Ledger();
    $book['transactions'][0]['settlement']['paid_amount'] = '33.33';
    $book['reports'][0]['operation_taxes'] = ['IBS' => '13.33', 'CBS' => '26.66'];
    $book['reports'][0]['batch_taxes'] = ['IBS' => '53.33', 'CBS' => '106.66'];
    foreach ($book['remittances'] as &$remit) {
        $remit['amount'] = $remit['tax'] === 'IBS' ? '93.33' : '186.66';
    }
    unset($remit);
    foreach ($book['applications'] as &$application) {
        $application['modality'] = 'COMPENSATION';
        $application['remittance_id'] = null;
    }
    unset($application);
    $context = (new SplitLedger158)->reconcile(sealLedger158($book));
    expect($context['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED');
    $input = refund164FromContext($context, 'STANDARD_FALLBACK');
    expect((new RefundDeadline164)->assess($input)['reason'])->toBe('A164_REFUND_ALLOCATION_EXCEEDS_PAYMENT_SEGREGATION');
});

it('binds a consolidated boleto refund to the individual payment that funded its remittance', function (): void {
    $book = split161Ledger();
    $book['known_at'] = '2026-11-23T16:00:00Z';
    foreach ($book['transactions'] as &$transaction) {
        $transaction['known_at'] = $book['known_at'];
        if (($transaction['contract'] ?? null) === SplitBoletoPayments161::CONTRACT) {
            $transaction['current_snapshot']['known_at'] = $book['known_at'];
            $transaction = sealBoleto161($transaction);
        } else {
            $transaction = seal158($transaction);
        }
    }
    unset($transaction);
    $seed = $book['applications'][0];
    $book['applications'] = [];
    foreach (['IBS', 'CBS'] as $tax) {
        $amount = BigDecimal::of($book['debits'][$tax])->minus($book['previously_extinguished'][$tax]);
        $book['applications'][] = array_replace($seed, ['id' => 'COMP.'.$tax, 'tax' => $tax, 'modality' => 'COMPENSATION',
            'remittance_id' => null, 'amount' => (string) $amount, 'applied_at' => '2026-10-05T12:00:00Z', 'application_receipt_id' => 'RECEIPT.COMP.'.$tax]);
    }
    $binding = array_intersect_key($book, array_flip(['operation_id', 'document_id', 'supplier_id', 'acquirer_id']));
    $book['excess_determinations'] = [$binding + ['id' => 'EXCESS.IBS', 'tax' => 'IBS', 'remittance_id' => 'A161.REM.R2.IBS',
        'amount' => '6.66', 'determined_at' => '2026-10-05T13:00:00Z', 'authority_receipt_id' => 'RECEIPT.EXCESS',
        'source_hash' => hash('sha256', 'A164 synthetic multiple-payment excess'), 'evidence_id' => 'PROOF.EXCESS']];
    $book['transfers'] = [];
    $context = (new SplitLedger158)->reconcilePayments(sealLedger158($book));
    expect($context['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED');
    $input = refund164FromContext($context, 'STANDARD_FALLBACK');
    $secondReceipt = $book['transactions'][0]['payments'][1]['transaction']['settlement']['receipt_id'];
    $input['requests'][0]['payment_reference'] = $input['requests'][0]['notice']['payment_reference'] = $secondReceipt;
    $input['requests'][0]['notice']['information_received_at'] = '2026-10-04T12:00:00Z';
    $report = (new RefundDeadline164)->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_REFUND_DEADLINES_ASSESSED')
        ->and($report['assessments'][0]['pending_transfer'])->toBe('6.66');
    $input['requests'][0]['payment_reference'] = $input['requests'][0]['notice']['payment_reference'] = $book['transactions'][0]['payments'][0]['transaction']['settlement']['receipt_id'];
    expect((new RefundDeadline164)->assess(seal158($input))['reason'])->toBe('A164_PAYMENT_NOT_IN_EXCESS_REMITTANCE');
});
