<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitBoletoPayments161;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split161_fixture.php';

it('keeps one installment and segregates each supplied boleto payment without reallocating cents', function (): void {
    $service = new SplitBoletoPayments161;
    $report = $service->assess(split161Boleto());
    expect($report['status'])->toBe('LOCAL_SPLIT_BOLETO_PAYMENTS_ASSESSED')
        ->and($report['paid_amount'])->toBe('100.00')
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('9.99')
        ->and($report['segregation']['taxes']['CBS']['segregated'])->toBe('19.99')
        ->and($report['net_after_segregation'])->toBe('70.02')
        ->and($report['payment_records'])->toHaveCount(2)
        ->and($report['financial_state'])->toBe('INTEGRAL_PAYMENT_REPORTED')
        ->and($report['current_window_state'])->toBe('FULL_PAYMENT_REPORTED')
        ->and($report['integral_payment_is_inferred_from_amount'])->toBeFalse()
        ->and($report['segregation_is_extinction'])->toBeFalse()
        ->and($service->replay($report))->toBeTrue();
    $report['paid_amount'] = '99.99';
    expect($service->replay($report))->toBeFalse();
});

it('does not infer integral payment merely because supplied amounts add to the original', function (): void {
    $report = (new SplitBoletoPayments161)->assess(split161Boleto(false));
    expect($report['status'])->toBe('LOCAL_SPLIT_BOLETO_PAYMENTS_ASSESSED')
        ->and($report['paid_amount'])->toBe('100.00')
        ->and($report['financial_state'])->toBe('PARTIAL_PAYMENTS_RECORDED')
        ->and($report['current_window_state'])->toBe('OPEN');
});

it('refuses contradictory histories even after every local envelope is resealed', function (string $case): void {
    $input = split161Boleto();
    match ($case) {
        'history' => $input['history_complete'] = false,
        'duplicate id' => $input['payments'][1]['payment_id'] = $input['payments'][0]['payment_id'],
        'duplicate receipt' => $input['payments'][1]['transaction']['settlement']['receipt_id'] = $input['payments'][0]['transaction']['settlement']['receipt_id'],
        'sequence gap' => $input['payments'][1]['sequence'] = 3,
        'unknown source' => $input['source_kind'] = 'OFFICIAL',
        'wrong document' => $input['payments'][0]['transaction']['document_id'] = 'OTHER',
        'wrong boleto' => $input['payments'][0]['transaction']['transaction_id'] = 'OTHER',
        'wrong psp' => $input['payments'][0]['transaction']['receiver_psp_id'] = 'OTHER',
        'wrong currency' => $input['currency'] = 'USD',
        'future snapshot' => $input['payments'][1]['transaction']['known_at'] = '2026-11-01T12:00:00Z',
        'current settlement' => $input['current_snapshot']['settlement'] = $input['payments'][1]['transaction']['settlement'],
        'current tax' => $input['taxes']['IBS'] = '11.00',
        'extra field' => $input['inferred_full_payment'] = true,
        'no integral proof' => $input['payments'][1]['transaction']['platform_window_close_evidence_id'] = '',
        'partial closes' => $input['payments'][1]['integral_payment'] = false,
        'early integral' => $input['payments'][0]['integral_payment'] = true,
        'missing payment' => $input['payments'] = [],
        'missing current closure' => $input['current_snapshot']['platform_window_close_reason'] = 'CANCELLED',
    };
    expect((new SplitBoletoPayments161)->assess(sealBoleto161($input))['status'])->toBe('ACTION_REQUIRED');
})->with(['history', 'duplicate id', 'duplicate receipt', 'sequence gap', 'unknown source', 'wrong document',
    'wrong boleto', 'wrong psp', 'wrong currency', 'future snapshot', 'current settlement', 'current tax',
    'extra field', 'no integral proof', 'partial closes', 'early integral', 'missing payment', 'missing current closure']);

it('rejects a nominal change between stable snapshots without a fiscal revision chain', function (): void {
    $input = split161Boleto();
    $input['payments'][0]['transaction']['taxes']['IBS'] = '9.00';
    $input['payments'][0]['transaction']['installment']['taxes']['IBS'] = '9.00';
    $report = (new SplitBoletoPayments161)->assess(sealBoleto161($input));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe('A161_FISCAL_CONTINUITY_REQUIRED');
});

/** @return array<string, mixed> */
function changedBoleto161(): array
{
    $input = split161Boleto();
    $next = $input['payments'][1]['transaction'];
    $next['taxes'] = $next['installment']['taxes'] = ['IBS' => '8.00', 'CBS' => '16.00'];
    $next = history160($next);
    $next['fiscal_revisions'][0]['version'] = $input['payments'][0]['transaction']['fiscal_input_version'];
    $next['fiscal_revisions'][0]['taxes'] = $input['payments'][0]['transaction']['taxes'];
    $next['fiscal_revisions'][1]['effective_at'] = '2026-10-03T11:00:00Z';
    $input['payments'][1]['transaction'] = $next;
    $input['current_snapshot'] = $next;
    $input['current_snapshot']['settlement'] = null;
    $input['current_snapshot']['known_at'] = $input['known_at'];
    $input['taxes'] = $input['installment']['taxes'] = $next['taxes'];

    return sealBoleto161($input);
}

it('preserves each frozen calculation across a proven revision between payments', function (): void {
    $report = (new SplitBoletoPayments161)->assess(changedBoleto161());
    expect($report['status'])->toBe('LOCAL_SPLIT_BOLETO_PAYMENTS_ASSESSED')
        ->and($report['payment_records'][0]['assessment']['segregation']['taxes']['IBS']['segregated'])->toBe('3.33')
        ->and($report['payment_records'][1]['assessment']['segregation']['taxes']['IBS']['segregated'])->toBe('5.33')
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('8.66')
        ->and($report['segregation']['taxes']['CBS']['segregated'])->toBe('17.32')
        ->and($report['segregation_is_extinction'])->toBeFalse();
});

it('rejects a later capture that rewrites an already supplied revision', function (): void {
    $input = changedBoleto161();
    $input['current_snapshot']['fiscal_revisions'][0]['source_hash'] = hash('sha256', 'different supplied historical bytes');
    $report = (new SplitBoletoPayments161)->assess(sealBoleto161($input));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe('A161_REWRITTEN_FISCAL_HISTORY');
});

it('rejects backdated revisions that contradict a previous payment snapshot', function (): void {
    $input = changedBoleto161();
    $input['payments'][1]['transaction']['fiscal_revisions'][1]['effective_at'] = '2026-10-02T11:00:00Z';
    $input['current_snapshot']['fiscal_revisions'][1]['effective_at'] = '2026-10-02T11:00:00Z';
    $report = (new SplitBoletoPayments161)->assess(sealBoleto161($input));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe('A161_REWRITTEN_FISCAL_SNAPSHOT');
});

it('never subtracts an earlier segregation from a supplied outstanding amount on its own', function (): void {
    $input = split161Boleto();
    $snapshot = split158Transaction()['platform_returns'][1];
    $snapshot['sequence'] = 1;
    $snapshot['tax'] = 'IBS';
    $snapshot['amount'] = '2.00';
    $snapshot['available_at'] = '2026-10-03T12:00:00Z';
    $snapshot['processed_at'] = '2026-10-03T12:00:01Z';
    $input['payments'][1]['transaction']['platform_returns'] = [$snapshot];
    $input['current_snapshot']['platform_returns'] = [$snapshot];
    $report = (new SplitBoletoPayments161)->assess(sealBoleto161($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_BOLETO_PAYMENTS_ASSESSED')
        ->and($report['payment_records'][1]['assessment']['segregation']['taxes']['IBS']['segregated'])->toBe('2.00')
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('5.33');
});
