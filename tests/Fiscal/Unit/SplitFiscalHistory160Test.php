<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\ConsumptionReform\SplitTransaction158;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split160_fixture.php';

it('resets prior fiscal responses and selects only the active revision at freeze', function (): void {
    $service = new SplitTransaction158;
    $input = split160Transaction();
    $report = $service->assessHistory($input);
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['active_fiscal_revision'])->toBe('SYNTHETIC.A160.V2')
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('2.66')
        ->and($report['segregation']['taxes']['CBS']['segregated'])->toBe('6.66')
        ->and($report['selected_platform_values']['IBS']['CORRECTED']['message_id'])->toBe('SYNTHETIC.A160.NEW.CORRECTION')
        ->and($report['selected_platform_values'])->not->toHaveKey('CBS')
        ->and($report['platform_return_trace'][0]['inactive_revision_at_freeze'])->toBeTrue()
        ->and($service->replayHistory($report))->toBeTrue()
        ->and($service->replay($report))->toBeFalse();
    $report['active_fiscal_revision'] = 'SYNTHETIC.A160.V1';
    expect($service->replayHistory($report))->toBeFalse();
});

it('does not reuse old returns when no new return was processed before freeze', function (): void {
    $input = split160Transaction();
    $input['platform_returns'][3]['processed_at'] = '2026-10-02T12:04:01Z';
    $report = (new SplitTransaction158)->assessHistory(seal158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['selected_platform_values'])->toBe([])
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('3.33')
        ->and($report['segregation']['taxes']['CBS']['segregated'])->toBe('6.66');
});

it('does not let an old revision processed later supersede the selected new response', function (): void {
    $input = split160Transaction();
    $input['platform_returns'][0]['processed_at'] = '2026-10-02T12:03:00Z';
    $report = (new SplitTransaction158)->assessHistory(seal158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('2.66');
});

it('uses the revised original amount as the proportional denominator', function (): void {
    $input = split160Transaction();
    $input['original_amount'] = $input['installment']['original_amount'] = '200.00';
    $input['fiscal_revisions'][1]['original_amount'] = '200.00';
    $report = (new SplitTransaction158)->assessHistory(seal158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('1.33')
        ->and($report['segregation']['taxes']['CBS']['segregated'])->toBe('3.33');
});

it('does not reopen the upward correction window after a fiscal reset', function (): void {
    $input = split160Transaction();
    $input['platform_returns'][3]['amount'] = '11.00';
    expect((new SplitTransaction158)->assessHistory(seal158($input))['reason'])->toBe('A158_UPWARD_CORRECTION_OUTSIDE_WINDOW');
});

it('handles successive proven resets without retaining an intermediate revision return', function (): void {
    $input = split160Transaction();
    $third = $input['fiscal_revisions'][1];
    $third['sequence'] = 3;
    $third['version'] = 'SYNTHETIC.A160.V3';
    $third['effective_at'] = '2026-10-02T11:00:00Z';
    $third['taxes']['IBS'] = '6.00';
    $third['reset_evidence_id'] = 'SYNTHETIC.A160.RESET3';
    $input['fiscal_revisions'][] = $third;
    $input['fiscal_input_version'] = $third['version'];
    $input['taxes'] = $input['installment']['taxes'] = $third['taxes'];
    // Historical V2 response belongs to the period before the V3 reset.
    $input['platform_returns'][3]['available_at'] = '2026-10-02T10:00:00Z';
    $input['platform_returns'][3]['processed_at'] = '2026-10-02T10:01:00Z';
    $input['platform_returns'] = [$input['platform_returns'][0], $input['platform_returns'][3]];
    $report = (new SplitTransaction158)->assessHistory(seal158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['active_fiscal_revision'])->toBe('SYNTHETIC.A160.V3')
        ->and($report['selected_platform_values'])->toBe([])
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('1.99');
});

it('accepts a stable single-revision Pix Automatico without allowing an update', function (): void {
    $input = split160Transaction();
    $input['arrangement'] = 'PIX_AUTOMATIC';
    $input['settlement']['milestone'] = 'PIX_SPI_OR_INTRABANK_SETTLED';
    $input['fiscal_inputs_unchanged'] = true;
    $row = $input['fiscal_revisions'][1];
    $row['sequence'] = 1;
    $row['effective_at'] = $input['initiated_at'];
    $row['reset_returns'] = false;
    $row['reset_evidence_id'] = null;
    $input['fiscal_revisions'] = [$row];
    $input['platform_returns'] = [$input['platform_returns'][3]];
    expect((new SplitTransaction158)->assessHistory(seal158($input))['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED');
});

it('selects a fiscal revision processed exactly at freeze and ignores a revision processed later', function (bool $after): void {
    $input = split160Transaction();
    $input['platform_returns'] = [];
    $freeze = $input['settlement']['calculation_frozen_at'];
    $input['fiscal_revisions'][1]['effective_at'] = $after ? '2026-10-02T12:04:00.000001Z' : $freeze;
    if ($after) {
        $input['fiscal_input_version'] = 'SYNTHETIC.A160.V1';
        $input['taxes'] = $input['installment']['taxes'] = ['IBS' => '0.00', 'CBS' => '0.00'];
    }
    $report = (new SplitTransaction158)->assessHistory(seal158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['active_fiscal_revision'])->toBe($after ? 'SYNTHETIC.A160.V1' : 'SYNTHETIC.A160.V2');
})->with([false, true]);

it('enforces outstanding monotonicity within each revision while permitting a documented reset', function (): void {
    $input = split160Transaction();
    $input['platform_returns'][0]['tax'] = 'CBS';
    $input['platform_returns'][0]['kind'] = 'OUTSTANDING';
    $input['platform_returns'][0]['amount'] = '1.00';
    $input['platform_returns'] = [$input['platform_returns'][0], $input['platform_returns'][3]];
    $input['platform_returns'][1]['tax'] = 'CBS';
    $input['platform_returns'][1]['kind'] = 'OUTSTANDING';
    $input['platform_returns'][1]['amount'] = '8.00';
    $report = (new SplitTransaction158)->assessHistory(seal158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['selected_platform_values']['CBS']['OUTSTANDING']['amount'])->toBe('8.00');
    $next = $input['platform_returns'][1];
    $next['id'] = 'SYNTHETIC.A160.OPEN.INCREASE';
    $next['sequence'] = 5;
    $next['amount'] = '9.00';
    $input['platform_returns'][] = $next;
    expect((new SplitTransaction158)->assessHistory(seal158($input))['reason'])->toBe('A158_OUTSTANDING_MUST_NOT_INCREASE');
});

it('refuses unproven or inconsistent revision/reset histories after resealing', function (string $case): void {
    $input = split160Transaction();
    match ($case) {
        'incomplete' => $input['fiscal_revision_history_complete'] = false,
        'stable lie' => $input['fiscal_inputs_unchanged'] = true,
        'missing reset' => $input['fiscal_revisions'][1]['reset_returns'] = false,
        'reset proof' => $input['fiscal_revisions'][1]['reset_evidence_id'] = '',
        'initial reset' => $input['fiscal_revisions'][0]['reset_returns'] = true,
        'gap' => $input['fiscal_revisions'][1]['sequence'] = 3,
        'duplicate sequence' => $input['fiscal_revisions'][1]['sequence'] = 1,
        'duplicate version' => $input['fiscal_revisions'][1]['version'] = 'SYNTHETIC.A160.V1',
        'unknown version' => $input['platform_returns'][3]['fiscal_input_version'] = 'UNKNOWN',
        'future revision' => $input['fiscal_revisions'][1]['effective_at'] = '2026-12-01T12:00:00Z',
        'initial time' => $input['fiscal_revisions'][0]['effective_at'] = '2026-10-01T12:01:00Z',
        'retroactive update' => $input['fiscal_revisions'][1]['effective_at'] = '2026-10-01T11:00:00Z',
        'same instant' => $input['fiscal_revisions'][1]['effective_at'] = $input['initiated_at'],
        'no material change' => $input['fiscal_revisions'][0]['taxes'] = $input['fiscal_revisions'][1]['taxes'],
        'wrong selected' => $input['fiscal_input_version'] = 'SYNTHETIC.A160.V1',
        'amount conflict' => $input['fiscal_revisions'][1]['original_amount'] = '101.00',
        'tax conflict' => $input['fiscal_revisions'][1]['taxes']['IBS'] = '9.00',
        'negative tax' => $input['fiscal_revisions'][0]['taxes']['IBS'] = '-1.00',
        'tax sum' => $input['fiscal_revisions'][0]['taxes']['IBS'] = '101.00',
        'source hash' => $input['fiscal_revisions'][1]['source_hash'] = '',
        'extra field' => $input['fiscal_revisions'][1]['assume_reset'] = true,
        'wrong return document' => $input['platform_returns'][0]['document_id'] = 'UNKNOWN',
        'return before revision' => $input['fiscal_revisions'][1]['effective_at'] = '2026-10-02T12:03:00Z',
        'pix automatic' => $input['arrangement'] = 'PIX_AUTOMATIC',
    };
    if ($case === 'pix automatic') {
        $input['settlement']['milestone'] = 'PIX_SPI_OR_INTRABANK_SETTLED';
        expect((new SplitTransaction158)->assessHistory(seal158($input))['reason'])->toBe('A160_ARRANGEMENT_UPDATE_NOT_ALLOWED');
    }
    expect((new SplitTransaction158)->assessHistory(seal158($input))['status'])->toBe('ACTION_REQUIRED');
})->with(['incomplete', 'stable lie', 'missing reset', 'reset proof', 'initial reset', 'gap', 'duplicate sequence',
    'duplicate version', 'unknown version', 'future revision', 'initial time', 'retroactive update', 'same instant',
    'no material change', 'wrong selected', 'amount conflict', 'tax conflict', 'negative tax', 'tax sum', 'source hash',
    'extra field', 'wrong return document', 'return before revision', 'pix automatic']);

it('preserves document revision history without applying returns for the old document', function (): void {
    $input = split160Transaction();
    $input['fiscal_revisions'][0]['document_id'] = 'SYNTHETIC.OLD.DF';
    foreach ($input['platform_returns'] as &$message) {
        if ($message['fiscal_input_version'] === 'SYNTHETIC.A160.V1') {
            $message['document_id'] = 'SYNTHETIC.OLD.DF';
        }
    }
    unset($message);
    expect((new SplitTransaction158)->assessHistory(seal158($input))['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED');
});

it('embeds revised transactions beside future installments in the versioned lifecycle ledger', function (): void {
    $input = split159Ledger();
    $input['contract'] = SplitLedger158::LIFECYCLE_CONTRACT;
    $input['transactions'][0] = history160($input['transactions'][0]);
    $service = new SplitLedger158;
    $report = $service->reconcileLifecycle(sealLedger158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($report['planned_installment_count'])->toBe(2)
        ->and($report['transactions'][0]['active_fiscal_revision'])->toBe('SYNTHETIC.A160.V2')
        ->and($service->replayLifecycle($report))->toBeTrue();
    $input['contract'] = SplitLedger158::SCHEDULE_CONTRACT;
    expect($service->reconcileSchedule(sealLedger158($input))['status'])->toBe('ACTION_REQUIRED');
});
