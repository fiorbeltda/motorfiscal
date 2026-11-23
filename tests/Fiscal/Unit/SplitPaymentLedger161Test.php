<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split161_fixture.php';

it('allocates a boleto installment once and reconciles reports and applications per payment', function (): void {
    $service = new SplitLedger158;
    $report = $service->reconcilePayments(split161Ledger());
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($report['transactions'])->toHaveCount(3)
        ->and($report['planned_installment_count'])->toBe(2)
        ->and($report['initiated_transaction_count'])->toBe(1)
        ->and($report['financial_payment_count'])->toBe(2)
        ->and($report['taxes']['IBS']['allocated'])->toBe('30.00')
        ->and($report['taxes']['IBS']['segregated'])->toBe('9.99')
        ->and($report['taxes']['CBS']['segregated'])->toBe('19.99')
        ->and($report['taxes']['IBS']['split_applied'])->toBe('9.99')
        ->and($report['taxes']['IBS']['remaining'])->toBe('20.01')
        ->and($report['taxes']['CBS']['remaining'])->toBe('40.01')
        ->and($report['taxes']['IBS']['unreported'])->toBe('0.00')
        ->and($service->replayPayments($report))->toBeTrue()
        ->and($service->replayLifecycle($report))->toBeFalse();
});

it('requires a payment-specific reference instead of an ambiguous boleto identifier', function (): void {
    $input = split161Ledger();
    unset($input['reports'][0]['payment_references']);
    $input['reports'][0]['transaction_ids'] = [$input['transactions'][0]['transaction_id']];
    $report = (new SplitLedger158)->reconcilePayments(sealLedger158($input));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe('A161_REPORT_MUST_IDENTIFY_PAYMENT');
});

it('rejects duplicate, unknown, premature or ambiguous references to payments', function (string $case): void {
    $input = split161Ledger();
    match ($case) {
        'unknown payment' => $input['reports'][0]['payment_references'][0]['payment_id'] = 'OTHER',
        'unknown boleto' => $input['reports'][0]['payment_references'][0]['transaction_id'] = 'OTHER',
        'missing id' => $input['reports'][0]['payment_references'][0]['payment_id'] = '',
        'duplicate reference' => $input['reports'][0]['payment_references'][] = $input['reports'][0]['payment_references'][0],
        'repeated across reports' => $input['reports'][1]['payment_references'] = $input['reports'][0]['payment_references'],
        'early report' => $input['reports'][0]['sent_at'] = '2026-10-02T12:04:00Z',
        'two reference formats' => $input['reports'][0]['transaction_ids'] = ['SYNTHETIC.TX1'],
        'extra key' => $input['reports'][0]['payment_references'][0]['assume_receipt'] = true,
        'opening overlaps first' => $input['history_as_of'] = '2026-10-02T13:00:00Z',
    };
    expect((new SplitLedger158)->reconcilePayments(sealLedger158($input))['status'])->toBe('ACTION_REQUIRED');
})->with(['unknown payment', 'unknown boleto', 'missing id', 'duplicate reference', 'repeated across reports',
    'early report', 'two reference formats', 'extra key', 'opening overlaps first']);

it('does not let an old ledger version silently accept a multiple-payment transaction', function (): void {
    $input = split161Ledger();
    $input['contract'] = SplitLedger158::LIFECYCLE_CONTRACT;
    expect((new SplitLedger158)->reconcileLifecycle(sealLedger158($input))['status'])->toBe('ACTION_REQUIRED');
});
