<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitPeriod162;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split162_fixture.php';

it('prioritizes current-period nonregular debts before older regular debts at closing', function (): void {
    $service = new SimplifiedSplitPeriod162;
    $report = $service->reconcile(split162Period());
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED')
        ->and(array_column(array_slice($report['allocation_proposal'], 0, 3), 'document_id'))
        ->toBe(['SYNTHETIC.CURRENT.NONREG1', 'SYNTHETIC.CURRENT.NONREG2', 'SYNTHETIC.OLD.REGULAR'])
        ->and($report['balances']['IBS']['proposed_for_debts'])->toBe('20.00')
        ->and($report['balances']['IBS']['potential_excess_after_proposal'])->toBe('5.00')
        ->and($report['balances']['IBS']['applied_from_supplied_records'])->toBe('0.00')
        ->and($report['remaining_debts'][0]['IBS'])->toBe('10.00')
        ->and($report['proposal_is_extinction'])->toBeFalse()
        ->and($service->replay($report))->toBeTrue();
});

it('does not allocate to regular or earlier-period debts before period closing', function (): void {
    $report = (new SimplifiedSplitPeriod162)->reconcile(split162Period(false));
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED')
        ->and($report['allocation_proposal'])->toHaveCount(4)
        ->and($report['balances']['IBS']['proposed_for_debts'])->toBe('10.00')
        ->and($report['balances']['IBS']['unreserved_after_proposal'])->toBe('15.00')
        ->and($report['balances']['IBS']['potential_excess_after_proposal'])->toBeNull();
});

it('reconciles actual applications, separate excess determinations and transfers without consuming funds twice', function (): void {
    $input = split162Period();
    $sequence = 0;
    foreach (['IBS' => ['5.00', '5.00', '10.00'], 'CBS' => ['10.00', '10.00', '20.00']] as $tax => $amounts) {
        foreach (['SYNTHETIC.CURRENT.NONREG1', 'SYNTHETIC.CURRENT.NONREG2', 'SYNTHETIC.OLD.REGULAR'] as $index => $doc) {
            $input['events'][] = split162Event('APP.'.$tax.'.'.$index, ++$sequence, 'APPLICATION', $tax, $amounts[$index], '2026-11-01T13:00:00Z', $doc);
        }
        $input['events'][] = split162Event('EXCESS.'.$tax, ++$sequence, 'EXCESS_DETERMINATION', $tax, $tax === 'IBS' ? '5.00' : '10.00', '2026-11-01T13:00:00Z');
        $input['events'][] = split162Event('TRANSFER.'.$tax, ++$sequence, 'TRANSFER', $tax, $tax === 'IBS' ? '2.00' : '4.00', '2026-11-01T13:00:00Z', null, 'EXCESS.'.$tax);
    }
    $service = new SimplifiedSplitPeriod162;
    $report = $service->reconcile(seal158($input));
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED')
        ->and($report['balances']['IBS']['applied_from_supplied_records'])->toBe('20.00')
        ->and($report['balances']['IBS']['pending_transfer'])->toBe('3.00')
        ->and($report['balances']['CBS']['pending_transfer'])->toBe('6.00')
        ->and($report['balances']['IBS']['unreserved_cash'])->toBe('0.00')
        ->and($report['allocation_proposal'])->toBe([])
        ->and($report['posted'])->toBeFalse()
        ->and($service->replay($report))->toBeTrue();
});

it('allows partial authority applications but keeps the same head debt until it is satisfied', function (): void {
    $input = split162Period(false);
    $input['events'] = [split162Event('APP.PARTIAL', 1, 'APPLICATION', 'IBS', '2.00', '2026-10-06T12:00:00Z', 'SYNTHETIC.CURRENT.NONREG1')];
    $report = (new SimplifiedSplitPeriod162)->reconcile(seal158($input));
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED')
        ->and($report['balances']['IBS']['applied_from_supplied_records'])->toBe('2.00')
        ->and($report['allocation_proposal'][0]['amount'])->toBe('3.00');
    $input['events'][] = split162Event('APP.WRONG.NEXT', 2, 'APPLICATION', 'IBS', '1.00', '2026-10-06T13:00:00Z', 'SYNTHETIC.CURRENT.NONREG2');
    expect((new SimplifiedSplitPeriod162)->reconcile(seal158($input))['reason'])->toBe('A162_APPLICATION_PRIORITY_CONFLICT');
});

it('requires authority events rather than turning its proposal into an excess determination', function (): void {
    $input = split162Period();
    $input['events'] = [split162Event('EXCESS.TOO.EARLY', 1, 'EXCESS_DETERMINATION', 'IBS', '5.00', '2026-11-02T12:00:00Z')];
    expect((new SimplifiedSplitPeriod162)->reconcile(seal158($input))['reason'])->toBe('A162_EXCESS_NOT_ESTABLISHED');
});

it('does not use cash received after the supplied application instant', function (): void {
    $input = split162Period(false);
    $input['events'] = [split162Event('APP.BEFORE.CASH', 1, 'APPLICATION', 'IBS', '1.00', '2026-10-03T12:00:00Z', 'SYNTHETIC.CURRENT.NONREG1')];
    expect((new SimplifiedSplitPeriod162)->reconcile(seal158($input))['reason'])->toBe('A162_APPLICATION_EXCEEDS_FUNDS_OR_DEBIT');
});

it('places prior-period nonregular debts in the second priority and respects book opening evidence', function (): void {
    $input = split162Period(false);
    $input['debts'][0]['acquirer_regime'] = 'NON_REGULAR';
    $input['debts'][1]['previously_extinguished'] = ['IBS' => '4.00', 'CBS' => '8.00'];
    $input['debts'][1]['prior_receipt_ids'] = ['PRIOR.IBS', 'PRIOR.CBS'];
    $report = (new SimplifiedSplitPeriod162)->reconcile(seal158($input));
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED')
        ->and($report['remaining_debts'][0]['priority'])->toBe(2)
        ->and($report['allocation_proposal'][0]['amount'])->toBe('1.00')
        ->and($report['balances']['IBS']['proposed_for_debts'])->toBe('6.00');
});

it('does not allow applications from a previously reserved excess pool', function (): void {
    $input = split162Period();
    $input['debts'] = $input['document_order'] = [];
    $input['events'] = [split162Event('EXCESS.ALL', 1, 'EXCESS_DETERMINATION', 'IBS', '25.00', '2026-11-01T13:00:00Z'),
        split162Event('TRANSFER.ALL', 2, 'TRANSFER', 'IBS', '25.00', '2026-11-01T14:00:00Z', null, 'EXCESS.ALL')];
    $report = (new SimplifiedSplitPeriod162)->reconcile(seal158($input));
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED')
        ->and($report['balances']['IBS']['unreserved_cash'])->toBe('0.00')
        ->and($report['balances']['IBS']['pending_transfer'])->toBe('0.00');
    $input['events'][] = split162Event('EXCESS.DUPLICATE.FUNDS', 3, 'EXCESS_DETERMINATION', 'IBS', '0.01', '2026-11-01T15:00:00Z');
    expect((new SimplifiedSplitPeriod162)->reconcile(seal158($input))['reason'])->toBe('A162_EXCESS_NOT_ESTABLISHED');
});

it('refuses a transfer to another beneficiary or beyond the separate determination', function (bool $wrongBeneficiary): void {
    $input = split162Period();
    $input['debts'] = $input['document_order'] = [];
    $input['events'] = [split162Event('EXCESS.ONE', 1, 'EXCESS_DETERMINATION', 'IBS', '1.00', '2026-11-01T13:00:00Z'),
        split162Event('TRANSFER.ONE', 2, 'TRANSFER', 'IBS', $wrongBeneficiary ? '1.00' : '1.01', '2026-11-01T14:00:00Z', null, 'EXCESS.ONE')];
    if ($wrongBeneficiary) {
        $input['events'][1]['beneficiary_id'] = 'OTHER';
    }
    expect((new SimplifiedSplitPeriod162)->reconcile(seal158($input))['reason'])
        ->toBe($wrongBeneficiary ? 'A162_TRANSFER_SCOPE_CONFLICT' : 'A162_TRANSFER_EXCEEDS_EXCESS');
})->with([false, true]);

it('refuses incomplete, temporal, duplicate or identity contradictions in a period book', function (string $case): void {
    $input = split162Period();
    match ($case) {
        'incomplete debts' => $input['debts_complete'] = false,
        'incomplete receipts' => $input['receipts_complete'] = false,
        'incomplete events' => $input['events_complete'] = false,
        'opening overlaps' => $input['opening_excludes_current_receipts'] = false,
        'not monthly' => $input['period_start'] = '2026-10-02',
        'wrong end' => $input['period_end'] = '2026-10-30',
        'premature closing' => $input['period_closed_at'] = '2026-10-31T12:00:00Z',
        'future closing' => $input['period_closed_at'] = '2026-11-30T12:00:00Z',
        'closure proof' => $input['closure_evidence_id'] = '',
        'order proof' => $input['ordering_source_hash'] = '',
        'incomplete order' => array_pop($input['document_order']),
        'wrong order' => $input['document_order'] = array_reverse($input['document_order']),
        'duplicate order' => $input['document_order'][] = $input['document_order'][0],
        'duplicate debt' => $input['debts'][] = $input['debts'][0],
        'wrong supplier' => $input['debts'][0]['supplier_id'] = 'OTHER',
        'future opening' => $input['debts'][0]['opening_at'] = '2026-11-30T12:00:00Z',
        'prior above debit' => $input['debts'][0]['previously_extinguished']['IBS'] = '11.00',
        'prior without proof' => $input['debts'][0]['previously_extinguished']['IBS'] = '1.00',
        'wrong period receipt' => $input['receipts'][0]['period'] = '2026-11',
        'not received' => $input['receipts'][0]['received'] = false,
        'receipt outside period' => $input['receipts'][0]['collected_at'] = '2026-11-01T12:00:00Z',
        'duplicate receipt' => $input['receipts'][] = $input['receipts'][0],
        'one tax only' => $input['receipts'][0]['amounts'] = ['IBS' => '25.00'],
        'unproven credit field' => $input['credit_granted'] = true,
    };
    expect((new SimplifiedSplitPeriod162)->reconcile(seal158($input))['status'])->toBe('ACTION_REQUIRED');
})->with(['incomplete debts', 'incomplete receipts', 'incomplete events', 'opening overlaps', 'not monthly', 'wrong end',
    'premature closing', 'future closing', 'closure proof', 'order proof', 'incomplete order', 'wrong order', 'duplicate order',
    'duplicate debt', 'wrong supplier', 'future opening', 'prior above debit', 'prior without proof', 'wrong period receipt',
    'not received', 'receipt outside period', 'duplicate receipt', 'one tax only', 'unproven credit field']);
