<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split159_fixture.php';

it('reconciles the complete allocation while segregating only the settled installment', function (): void {
    $service = new SplitLedger158;
    $report = $service->reconcileSchedule(split159Ledger());
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($report['planned_installment_count'])->toBe(2)
        ->and($report['initiated_transaction_count'])->toBe(1)
        ->and($report['taxes']['IBS']['allocated'])->toBe('30.00')
        ->and($report['taxes']['CBS']['allocated'])->toBe('60.00')
        ->and($report['taxes']['IBS']['segregated'])->toBe('10.00')
        ->and($report['taxes']['CBS']['segregated'])->toBe('20.00')
        ->and($report['taxes']['IBS']['remaining'])->toBe('30.00')
        ->and($report['transactions'][1]['financial_state'])->toBe('NOT_INITIATED')
        ->and($report['transactions'][1]['segregation'])->toBeNull()
        ->and($report['transactions'][1]['extinction_proven'])->toBeFalse()
        ->and($report['posted'])->toBeFalse()
        ->and($service->replaySchedule($report))->toBeTrue()
        ->and($service->replay($report))->toBeFalse();
    $report['planned_installment_count'] = 1;
    expect($service->replaySchedule($report))->toBeFalse();
});

it('accepts an entirely future schedule without inventing a settlement', function (): void {
    $ledger = split159Ledger();
    $ledger['transactions'][0] = planned159($ledger['transactions'][0], '2026-11-01T12:00:00Z');
    $report = (new SplitLedger158)->reconcileSchedule(sealLedger158($ledger));
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($report['planned_installment_count'])->toBe(3)
        ->and($report['taxes']['IBS']['segregated'])->toBe('0.00')
        ->and($report['taxes']['CBS']['segregated'])->toBe('0.00');
});

it('reconciles reports and remittances for paid installments without consuming future allocations', function (): void {
    $ledger = split158Ledger();
    $ledger['contract'] = SplitLedger158::SCHEDULE_CONTRACT;
    $ledger['transactions'][2] = planned159($ledger['transactions'][2], '2026-12-01T12:00:00Z');
    $ledger['reports'] = [$ledger['reports'][0]];
    foreach ($ledger['remittances'] as &$remittance) {
        $remittance['report_ids'] = ['R1'];
        $remittance['amount'] = $remittance['tax'] === 'IBS' ? '60.00' : '120.00';
    }
    unset($remittance);
    $ledger['applications'] = $ledger['excess_determinations'] = $ledger['transfers'] = [];
    $report = (new SplitLedger158)->reconcileSchedule(sealLedger158($ledger));
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($report['planned_installment_count'])->toBe(1)
        ->and($report['taxes']['IBS']['reported'])->toBe('20.00')
        ->and($report['taxes']['CBS']['reported'])->toBe('40.00')
        ->and($report['has_batch_amount_discrepancy'])->toBeFalse()
        ->and($report['taxes']['IBS']['split_applied'])->toBe('0.00')
        ->and($report['taxes']['IBS']['remaining'])->toBe('25.00');
});

it('replays separate snapshots before and after a planned installment is initiated', function (): void {
    $service = new SplitLedger158;
    $before = $service->reconcileSchedule(split159Ledger());
    $ledger = split159Ledger();
    $active = split158Ledger()['transactions'][1];
    $active['initiated_at'] = '2026-12-01T12:00:00Z';
    $active['settlement'] = $active['receivable_advance'] = null;
    $ledger['transactions'][1] = $active;
    $ledger['known_at'] = '2026-12-01T16:00:00Z';
    foreach ($ledger['transactions'] as &$transaction) {
        $transaction['known_at'] = $ledger['known_at'];
    }
    unset($transaction);
    $after = $service->reconcileSchedule(sealLedger158($ledger));
    expect($after['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($after['planned_installment_count'])->toBe(1)
        ->and($after['initiated_transaction_count'])->toBe(2)
        ->and($after['transactions'][1]['financial_state'])->toBe('WAITING_FINANCIAL_SETTLEMENT')
        ->and($after['taxes'])->toBe($before['taxes'])
        ->and($service->replaySchedule($before))->toBeTrue()
        ->and($service->replaySchedule($after))->toBeTrue();
});

it('keeps initiated but unpaid installments distinct from future installments', function (): void {
    $ledger = split159Ledger();
    $ledger['transactions'][0]['settlement'] = null;
    $report = (new SplitLedger158)->reconcileSchedule(sealLedger158($ledger));
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($report['transactions'][0]['financial_state'])->toBe('WAITING_FINANCIAL_SETTLEMENT')
        ->and($report['transactions'][1]['financial_state'])->toBe('NOT_INITIATED')
        ->and($report['taxes']['IBS']['segregated'])->toBe('0.00');
});

it('refuses contradictory future schedules even after all envelopes are resealed', function (string $case): void {
    $ledger = split159Ledger();
    match ($case) {
        'past' => $ledger['transactions'][1]['planned_initiation_at'] = '2026-10-01T12:00:00Z',
        'present' => $ledger['transactions'][1]['planned_initiation_at'] = $ledger['known_at'],
        'invalid date' => $ledger['transactions'][1]['planned_initiation_at'] = '2026-11-31T12:00:00Z',
        'no timezone' => $ledger['transactions'][1]['planned_initiation_at'] = '2026-12-01T12:00:00',
        'future operation' => $ledger['transactions'][1]['operation_at'] = '2026-12-01T12:00:00Z',
        'fake initiation' => $ledger['transactions'][1]['initiated_at'] = '2026-12-01T12:00:00Z',
        'fake settlement' => $ledger['transactions'][1]['settlement'] = null,
        'fake returns' => $ledger['transactions'][1]['platform_returns'] = [],
        'document' => $ledger['transactions'][1]['document_id'] = 'OTHER',
        'allocation document' => $ledger['transactions'][1]['installment']['document_id'] = 'OTHER',
        'knowledge' => $ledger['transactions'][1]['known_at'] = '2026-10-05T12:00:00Z',
        'source' => $ledger['transactions'][1]['source_kind'] = 'OFFICIAL',
        'missing proof' => $ledger['transactions'][1]['evidence_id'] = '',
        'missing hash' => $ledger['transactions'][1]['source_hash'] = '',
        'missing allocation' => $ledger['transactions'][1]['installment']['allocation_evidence_id'] = '',
        'negative amount' => $ledger['transactions'][1]['original_amount'] = '-100.00',
        'zero amount' => $ledger['transactions'][1]['original_amount'] = '0.00',
        'numeric amount' => $ledger['transactions'][1]['original_amount'] = 100,
        'extra tax' => $ledger['transactions'][1]['taxes']['ISS'] = '1.00',
        'tax sum' => $ledger['transactions'][1]['taxes']['IBS'] = '200.00',
        'duplicate number' => $ledger['transactions'][1]['installment']['number'] = 1,
        'duplicate id' => $ledger['transactions'][1]['transaction_id'] = $ledger['transactions'][0]['transaction_id'],
        'missing installment' => array_pop($ledger['transactions']),
        'incomplete' => $ledger['schedule_complete'] = false,
        'principal' => $ledger['operation_amount'] = '400.00',
        'debit' => $ledger['debits']['CBS'] = '61.00',
        'report future' => $ledger['reports'] = split158Ledger()['reports'],
    };
    $report = (new SplitLedger158)->reconcileSchedule(sealLedger158($ledger));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report)->not->toHaveKey('taxes');
})->with(['past', 'present', 'invalid date', 'no timezone', 'future operation', 'fake initiation', 'fake settlement',
    'fake returns', 'document', 'allocation document', 'knowledge', 'source', 'missing proof', 'missing hash',
    'missing allocation', 'negative amount', 'zero amount', 'numeric amount', 'extra tax', 'tax sum',
    'duplicate number', 'duplicate id', 'missing installment', 'incomplete', 'principal', 'debit', 'report future']);

it('requires explicit selection of the versioned schedule contract', function (): void {
    $service = new SplitLedger158;
    expect($service->reconcile(split159Ledger())['status'])->toBe('ACTION_REQUIRED')
        ->and($service->reconcileSchedule(split158Ledger())['status'])->toBe('ACTION_REQUIRED');
    $legacy = split159Ledger();
    $legacy['contract'] = SplitLedger158::CONTRACT;
    expect($service->reconcile(sealLedger158($legacy))['status'])->toBe('ACTION_REQUIRED');
});
