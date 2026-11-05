<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitTransaction158;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split158_fixture.php';

it('selects values processed before freeze rather than the latest available or known message', function (): void {
    $service = new SplitTransaction158;
    $report = $service->assess(split158Transaction());
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('3.99')
        ->and($report['segregation']['taxes']['CBS']['segregated'])->toBe('5.00')
        ->and($report['net_after_segregation'])->toBe('24.34')
        ->and($report['selected_platform_values']['IBS'])->not->toHaveKey('OUTSTANDING')
        ->and($report['platform_return_trace'][2]['usable_at_freeze'])->toBeFalse()
        ->and($report['settled_at'])->toBe('2026-10-02T12:05:00.000000+00:00')
        ->and($report['remittance_status'])->toBe('NOT_RECONCILED')->and($report['extinction_proven'])->toBeFalse()
        ->and($report['posted'])->toBeFalse()->and($report['official'])->toBeFalse()->and($service->replay($report))->toBeTrue();
    $report['segregation']['taxes']['IBS']['segregated'] = '1.00';
    unset($report['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($report);
    expect($service->replay($report))->toBeFalse();
});

it('does not turn an advance of receivables into settlement or segregation', function (): void {
    $input = split158Transaction();
    $input['settlement'] = null;
    $report = (new SplitTransaction158)->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['financial_state'])->toBe('WAITING_FINANCIAL_SETTLEMENT')
        ->and($report['settled_at'])->toBeNull()->and($report['segregation'])->toBeNull()
        ->and($report['net_after_segregation'])->toBeNull()->and($report['advance_is_settlement'])->toBeFalse()
        ->and($report['payment_performed'])->toBeFalse();
});

it('uses informed values when no platform response was processed in time', function (): void {
    $input = split158Transaction();
    foreach ($input['platform_returns'] as &$message) {
        $message['processed_at'] = null;
    }
    unset($message);
    $report = (new SplitTransaction158)->assess(seal158($input));
    expect($report['selected_platform_values'])->toBe([])
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('3.33')
        ->and($report['segregation']['taxes']['CBS']['segregated'])->toBe('6.66');
});

it('uses a return processed exactly at the freeze instant including equivalent time offsets', function (): void {
    $input = split158Transaction();
    $input['platform_returns'][2]['processed_at'] = '2026-10-02T09:02:00-03:00';
    $report = (new SplitTransaction158)->assess(seal158($input));
    expect($report['segregation']['taxes']['IBS']['segregated'])->toBe('1.00')
        ->and($report['selected_platform_values']['IBS']['OUTSTANDING']['message_id'])->toBe('SYNTHETIC.MSG2');
});

it('does not let out of order processing overwrite a newer supplied message version', function (): void {
    $input = split158Transaction();
    $input['platform_returns'] = [$input['platform_returns'][0]];
    $input['platform_returns'][0]['processed_at'] = '2026-10-02T12:01:00Z';
    $newer = $input['platform_returns'][0];
    $newer['id'] = 'SYNTHETIC.NEW';
    $newer['sequence'] = 2;
    $newer['amount'] = '9.00';
    $newer['available_at'] = '2026-10-02T11:00:00Z';
    $newer['processed_at'] = '2026-10-02T11:01:00Z';
    $input['platform_returns'][] = $newer;
    $report = (new SplitTransaction158)->assess(seal158($input));
    expect($report['segregation']['taxes']['IBS']['segregated'])->toBe('2.99')
        ->and($report['selected_platform_values']['IBS']['CORRECTED']['message_id'])->toBe('SYNTHETIC.NEW');
    $input['platform_returns'] = array_reverse($input['platform_returns']);
    $reordered = (new SplitTransaction158)->assess(seal158($input));
    expect($reordered['selected_platform_values'])->toBe($report['selected_platform_values']);
});

it('allows upward correction through the next Brasilia day 0030 and downward correction later', function (): void {
    $input = split158Transaction();
    $input['platform_returns'] = [$input['platform_returns'][0]];
    $input['platform_returns'][0]['available_at'] = '2026-10-02T00:30:00-03:00';
    $input['platform_returns'][0]['processed_at'] = '2026-10-02T03:31:00Z';
    expect((new SplitTransaction158)->assess(seal158($input))['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED');
    $input['platform_returns'][0]['available_at'] = '2026-10-02T00:30:00.000001-03:00';
    expect((new SplitTransaction158)->assess(seal158($input))['reason'])->toBe('A158_UPWARD_CORRECTION_OUTSIDE_WINDOW');
    $input['platform_returns'][0]['amount'] = '9.00';
    $report = (new SplitTransaction158)->assess(seal158($input));
    expect($report['segregation']['taxes']['IBS']['segregated'])->toBe('2.99');
});

it('allows a receiver originator to link directly with government after initiation and to receive from a PF', function (): void {
    $input = split158Transaction();
    $input['document_link']['channel'] = 'GOVERNMENT';
    $input['document_link']['linked_at'] = '2026-10-01T13:00:00Z';
    $input['effective_payer_kind'] = 'PF';
    expect((new SplitTransaction158)->assess(seal158($input))['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED');
});

it('supports each payer arrangement without a super intelligent response window', function (string $arrangement, string $milestone): void {
    $input = split158Transaction();
    $input['arrangement'] = $arrangement;
    $input['originator'] = 'PAYER';
    $input['platform_returns'] = [];
    $input['settlement']['milestone'] = $milestone;
    $report = (new SplitTransaction158)->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED')
        ->and($report['segregation']['taxes']['IBS']['segregated'])->toBe('10.00')
        ->and($report['segregation']['taxes']['CBS']['segregated'])->toBe('20.00');
})->with([['PIX_STATIC', 'PIX_SPI_OR_INTRABANK_SETTLED'], ['TED', 'TED_STR_OR_SITRAF_SETTLED'], ['TEF', 'TEF_PAYER_DEBITED']]);

it('keeps the financial settlement time when the contractual credit availability is later', function (): void {
    $input = split158Transaction();
    $input['settlement']['contractual_credit_available_at'] = '2026-10-03T12:00:00Z';
    $report = (new SplitTransaction158)->assess(seal158($input));
    expect($report['settled_at'])->toBe('2026-10-02T12:05:00.000000+00:00');
});

it('rejects contradictory supplied facts even with a valid envelope hash', function (string $case): void {
    $input = split158Transaction();
    match ($case) {
        'contract' => $input['contract'] = 'OTHER',
        'manual' => $input['times_manual_hash'] = str_repeat('0', 64),
        'simplified' => $input['procedure'] = 'SIMPLIFIED',
        'USD' => $input['currency'] = 'USD',
        'official' => $input['source_kind'] = 'OFFICIAL',
        'empty party' => $input['supplier_id'] = '',
        'source' => $input['source_hash'] = 'NO_HASH',
        'future issue' => $input['document_issued_at'] = '2026-10-05T00:00:00Z',
        'invalid date' => $input['operation_at'] = '2026-02-30T00:00:00Z',
        'future initiation' => $input['initiated_at'] = '2026-10-05T00:00:00Z',
        'incomplete history' => $input['platform_history_complete'] = false,
        'changed inputs' => $input['fiscal_inputs_unchanged'] = false,
        'card' => $input['arrangement'] = 'CARD',
        'originator' => $input['originator'] = 'PAYER',
        'supplier PF' => $input['supplier_kind'] = 'PF',
        'original PF' => $input['original_payer_kind'] = 'PF',
        'unregistered' => $input['original_payer_cnpj_registered'] = false,
        'open finance' => $input['open_finance_initiated'] = true,
        'zero original' => $input['original_amount'] = '0.00',
        'float tax' => $input['taxes']['IBS'] = 10.0,
        'sum taxes' => $input['taxes']['IBS'] = '100.00',
        'link document' => $input['document_link']['document_id'] = 'OTHER',
        'link transaction' => $input['document_link']['transaction_id'] = 'OTHER',
        'link before issue' => $input['document_link']['linked_at'] = '2026-10-01T08:00:00Z',
        'installment document' => $input['installment']['document_id'] = 'OTHER',
        'installment amount' => $input['installment']['original_amount'] = '99.00',
        'installment tax' => $input['installment']['taxes']['CBS'] = '19.00',
        'installment zero' => $input['installment']['number'] = 0,
        'installment overflow' => $input['installment']['number'] = 4,
        'closed window' => $input['platform_window_closed_at'] = '2026-10-01T14:00:00Z',
        'wrong milestone' => $input['settlement']['milestone'] = 'TEF_PAYER_DEBITED',
        'wrong settled transaction' => $input['settlement']['transaction_id'] = 'OTHER',
        'settlement without receipt' => $input['settlement']['receipt_id'] = '',
        'premature settled' => $input['settlement']['settled_at'] = '2026-10-02T12:04:00Z',
        'late freeze' => $input['settlement']['calculation_frozen_at'] = '2026-10-02T12:06:00Z',
        'zero payment' => $input['settlement']['paid_amount'] = '0.00',
        'advance transaction' => $input['receivable_advance']['transaction_id'] = 'OTHER',
        'future advance' => $input['receivable_advance']['at'] = '2026-10-05T12:00:00Z',
        'duplicate message id' => $input['platform_returns'][1]['id'] = $input['platform_returns'][0]['id'],
        'duplicate sequence' => $input['platform_returns'][1]['sequence'] = 1,
        'message document' => $input['platform_returns'][0]['document_id'] = 'OTHER',
        'old fiscal input' => $input['platform_returns'][0]['fiscal_input_version'] = 'OTHER',
        'message evidence' => $input['platform_returns'][0]['source_hash'] = '',
        'message before link' => $input['platform_returns'][0]['available_at'] = '2026-10-01T09:30:00Z',
        'processing before availability' => $input['platform_returns'][0]['processed_at'] = '2026-10-01T14:59:00Z',
        'unknown tax' => $input['platform_returns'][0]['tax'] = 'ISS',
        'unknown kind' => $input['platform_returns'][0]['kind'] = 'RATE',
        'negative amount' => $input['platform_returns'][0]['amount'] = '-1.00',
    };
    $report = (new SplitTransaction158)->assess(seal158($input));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report)->not->toHaveKey('segregation');
})->with(['contract', 'manual', 'simplified', 'USD', 'official', 'empty party', 'source', 'future issue',
    'invalid date', 'future initiation', 'incomplete history', 'changed inputs', 'card', 'originator', 'supplier PF',
    'original PF', 'unregistered', 'open finance', 'zero original', 'float tax', 'sum taxes', 'link document',
    'link transaction', 'link before issue', 'installment document', 'installment amount', 'installment tax',
    'installment zero', 'installment overflow', 'closed window', 'wrong milestone', 'wrong settled transaction',
    'settlement without receipt', 'premature settled', 'late freeze', 'zero payment', 'advance transaction',
    'future advance', 'duplicate message id', 'duplicate sequence', 'message document', 'old fiscal input',
    'message evidence', 'message before link', 'processing before availability', 'unknown tax', 'unknown kind', 'negative amount']);

it('rejects an outstanding amount increasing in a later platform version', function (): void {
    $input = split158Transaction();
    $message = $input['platform_returns'][1];
    $message['id'] = 'SYNTHETIC.INCREASE';
    $message['sequence'] = 4;
    $message['amount'] = '6.00';
    $message['available_at'] = '2026-10-02T12:03:00Z';
    $message['processed_at'] = null;
    $input['platform_returns'][] = $message;
    expect((new SplitTransaction158)->assess(seal158($input))['reason'])->toBe('A158_OUTSTANDING_MUST_NOT_INCREASE');
});

it('does not make installment tax field ordering a substantive contradiction', function (): void {
    $input = split158Transaction();
    $input['installment']['taxes'] = ['CBS' => '20.00', 'IBS' => '10.00'];
    expect((new SplitTransaction158)->assess(seal158($input))['status'])->toBe('LOCAL_SPLIT_TRANSACTION_ASSESSED');
});

it('records a proved cancellation without inventing a future payment', function (): void {
    $input = split158Transaction();
    $input['settlement'] = null;
    $input['platform_window_closed_at'] = '2026-10-03T00:00:00Z';
    $input['platform_window_close_reason'] = 'CANCELLED';
    $input['platform_window_close_evidence_id'] = 'SYNTHETIC.CANCELLED';
    $report = (new SplitTransaction158)->assess(seal158($input));
    expect($report['financial_state'])->toBe('CANCELLED_WITHOUT_SETTLEMENT')->and($report['segregation'])->toBeNull();
    $input['settlement'] = split158Transaction()['settlement'];
    expect((new SplitTransaction158)->assess(seal158($input))['reason'])->toBe('A158_CANCELLED_TRANSACTION_CANNOT_SETTLE');
});

it('rejects actual contractual availability after the observation time', function (): void {
    $input = split158Transaction();
    $input['settlement']['contractual_credit_available_at'] = '2026-10-05T12:00:00Z';
    expect((new SplitTransaction158)->assess(seal158($input))['reason'])->toBe('A158_CONTRACTUAL_CREDIT_DATE_CONFLICT');
});
