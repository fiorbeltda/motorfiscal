<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split158_fixture.php';

it('reconciles the complete exact installment schedule and attributes only the documented operation share', function (): void {
    $service = new SplitLedger158;
    $report = $service->reconcile(split158Ledger());
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($report['taxes']['IBS']['debit'])->toBe('30.00')->and($report['taxes']['IBS']['allocated'])->toBe('30.00')
        ->and($report['taxes']['IBS']['segregated'])->toBe('30.00')->and($report['taxes']['IBS']['split_applied'])->toBe('25.00')
        ->and($report['taxes']['IBS']['remaining'])->toBe('0.00')->and($report['taxes']['IBS']['pending_transfer_to_supplier'])->toBe('3.00')
        ->and($report['taxes']['CBS']['split_applied'])->toBe('50.00')->and($report['taxes']['CBS']['pending_transfer_to_supplier'])->toBe('10.00')
        ->and($report['remittances'][0]['batch_received'])->toBe('100.00')
        ->and($report['remittances'][0]['operation_applied'])->toBe('25.00')
        ->and($report['remittances'][0]['batch_includes_other_operations'])->toBeTrue()
        ->and($report['has_batch_amount_discrepancy'])->toBeFalse()->and($report['has_unremitted_reports'])->toBeFalse()
        ->and($report['deadline_date'])->toBeNull()->and($report['posted'])->toBeFalse()
        ->and($report['credit_appropriated'])->toBeFalse()->and($service->replay($report))->toBeTrue();
    $report['taxes']['IBS']['split_applied'] = '100.00';
    unset($report['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($report);
    expect($service->replay($report))->toBeFalse();
});

it('does not turn a batch receipt into an operation extinction without applied authority records', function (): void {
    $input = split158Ledger();
    $input['applications'] = $input['excess_determinations'] = $input['transfers'] = [];
    $report = (new SplitLedger158)->reconcile(sealLedger158($input));
    expect($report['taxes']['IBS']['remaining'])->toBe('25.00')->and($report['taxes']['CBS']['remaining'])->toBe('50.00')
        ->and($report['taxes']['IBS']['split_applied'])->toBe('0.00')->and($report['taxes']['IBS']['excess'])->toBe('0.00')
        ->and($report['remittances'][0]['operation_reported_not_accounted'])->toBe('30.00');
});

it('exposes reported but unremitted amounts without inventing receipts', function (): void {
    $input = split158Ledger();
    $input['remittances'] = $input['applications'] = $input['excess_determinations'] = $input['transfers'] = [];
    $report = (new SplitLedger158)->reconcile(sealLedger158($input));
    expect($report['has_unremitted_reports'])->toBeTrue()->and($report['remittances'])->toBe([])
        ->and($report['taxes']['IBS']['reported'])->toBe('30.00')->and($report['taxes']['IBS']['remaining'])->toBe('25.00');
});

it('keeps unreported and unsettled installments separate from the operation debt', function (): void {
    $input = split158Ledger();
    $input['reports'] = $input['remittances'] = $input['applications'] = $input['excess_determinations'] = $input['transfers'] = [];
    $input['transactions'][2]['settlement'] = null;
    $report = (new SplitLedger158)->reconcile(sealLedger158($input));
    expect($report['schedule_status'])->toBe('COMPLETE_EXACT_PROPORTIONAL_SUPPLIED_SCHEDULE')
        ->and($report['taxes']['IBS']['allocated'])->toBe('30.00')->and($report['taxes']['IBS']['segregated'])->toBe('20.00')
        ->and($report['taxes']['IBS']['unreported'])->toBe('20.00')->and($report['taxes']['IBS']['remaining'])->toBe('25.00')
        ->and($report['transactions'][2]['financial_state'])->toBe('WAITING_FINANCIAL_SETTLEMENT');
});

it('records a batch shortfall without assuming that its full amount belongs to this operation', function (): void {
    $input = split158Ledger();
    $input['applications'] = $input['excess_determinations'] = $input['transfers'] = [];
    $input['remittances'][0]['amount'] = '22.00';
    $report = (new SplitLedger158)->reconcile(sealLedger158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')->and($report['has_batch_amount_discrepancy'])->toBeTrue()
        ->and($report['remittances'][0]['batch_difference'])->toBe('-78.00')
        ->and($report['taxes']['IBS']['remaining'])->toBe('25.00');
});

it('does not give the supplier excess funds merely because a consolidated batch was overpaid', function (): void {
    $input = split158Ledger();
    $input['remittances'][0]['amount'] = '120.00';
    $report = (new SplitLedger158)->reconcile(sealLedger158($input));
    expect($report['has_batch_amount_discrepancy'])->toBeTrue()->and($report['remittances'][0]['batch_difference'])->toBe('20.00')
        ->and($report['taxes']['IBS']['excess'])->toBe('5.00')->and($report['taxes']['IBS']['pending_transfer_to_supplier'])->toBe('3.00');
});

it('separates a documented compensation from split funds and never performs credit appropriation', function (): void {
    $input = split158Ledger();
    $other = $input['applications'][0];
    $other['id'] = 'OTHER.COMPENSATION';
    $other['application_receipt_id'] = 'SYNTHETIC.OTHER.RECEIPT';
    $other['modality'] = 'COMPENSATION';
    $other['amount'] = '10.00';
    $other['applied_at'] = '2026-10-05T12:30:00Z';
    unset($other['remittance_id']);
    $input['applications'][0]['amount'] = '15.00';
    $input['applications'][] = $other;
    $report = (new SplitLedger158)->reconcile(sealLedger158($input));
    expect($report['taxes']['IBS']['other_applied'])->toBe('10.00')->and($report['taxes']['IBS']['split_applied'])->toBe('15.00')
        ->and($report['taxes']['IBS']['remaining'])->toBe('0.00')
        ->and($report['remittances'][0]['operation_reported_not_accounted'])->toBe('10.00')
        ->and($report['credit_appropriated'])->toBeFalse();
});

it('preserves numeric looking report ids and accepts equivalent knowledge time offsets', function (): void {
    $input = split158Ledger();
    $input['reports'][0]['id'] = '0';
    foreach ($input['remittances'] as &$remittance) {
        $remittance['report_ids'] = ['0', 'R2'];
    }
    unset($remittance);
    $input['transactions'][0]['known_at'] = '2026-10-06T13:00:00-03:00';
    expect((new SplitLedger158)->reconcile(sealLedger158($input))['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED');
});

it('refuses a supplied schedule whose allocated taxes do not close the debit', function (): void {
    $input = split158Ledger();
    $input['debits']['IBS'] = '31.00';
    expect((new SplitLedger158)->reconcile(sealLedger158($input))['reason'])->toBe('A158_LEDGER_SCHEDULE_DEBIT_CONFLICT');
});

it('reconciles supplied residual cents while preserving the unresolved proportionality instead of inventing an allocation rule', function (): void {
    $input = split158Ledger();
    $input['debits']['IBS'] = '31.00';
    foreach (['10.33', '10.33', '10.34'] as $index => $amount) {
        $input['transactions'][$index]['taxes']['IBS'] = $input['transactions'][$index]['installment']['taxes']['IBS'] = $amount;
    }
    $input['applications'] = $input['excess_determinations'] = $input['transfers'] = [];
    $input['reports'][0]['operation_taxes']['IBS'] = '20.66';
    $input['reports'][0]['batch_taxes']['IBS'] = '60.66';
    $input['reports'][1]['operation_taxes']['IBS'] = '10.34';
    $input['reports'][1]['batch_taxes']['IBS'] = '40.34';
    $input['remittances'][0]['amount'] = '101.00';
    $report = (new SplitLedger158)->reconcile(sealLedger158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($report['allocation_proportionality_proven'])->toBeFalse()
        ->and($report['schedule_status'])->toBe('COMPLETE_SUPPLIED_SCHEDULE_PROPORTIONALITY_NOT_PROVEN')
        ->and($report['allocation_findings'][0]['exact_proportional_amount'])->toBe('31/3')
        ->and($report['allocation_findings'][0]['supplied_minus_exact'])->toBe('-1/300')
        ->and($report['allocation_findings'][2]['supplied_minus_exact'])->toBe('1/150')
        ->and($report['taxes']['IBS']['reported'])->toBe('31.00')
        ->and($report['taxes']['IBS']['remaining'])->toBe('26.00')
        ->and($report['transactions'][2]['segregation']['taxes']['IBS']['segregated'])->toBe('10.34');
});

it('does not attribute a foreign batch amount to an operation with zero taxes', function (): void {
    $input = split158Ledger();
    $input['debits'] = $input['previously_extinguished'] = ['IBS' => '0.00', 'CBS' => '0.00'];
    $input['prior_receipt_ids'] = [];
    $input['applications'] = $input['excess_determinations'] = $input['transfers'] = [];
    foreach ($input['transactions'] as &$transaction) {
        $transaction['taxes'] = $transaction['installment']['taxes'] = ['IBS' => '0.00', 'CBS' => '0.00'];
    }
    unset($transaction);
    foreach ($input['reports'] as &$report) {
        $report['operation_taxes'] = ['IBS' => '0.00', 'CBS' => '0.00'];
        $report['batch_taxes'] = $report['other_operation_taxes'];
    }
    unset($report);
    $input['remittances'][0]['amount'] = '70.00';
    $input['remittances'][1]['amount'] = '140.00';
    $report = (new SplitLedger158)->reconcile(sealLedger158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($report['remittances'][0]['batch_received'])->toBe('70.00')
        ->and($report['taxes']['IBS']['split_applied'])->toBe('0.00')->and($report['taxes']['IBS']['excess'])->toBe('0.00');
});

it('rejects contradictory records after both ledger and child envelopes were resealed', function (string $case): void {
    $input = split158Ledger();
    match ($case) {
        'contract' => $input['contract'] = 'OTHER',
        'currency' => $input['currency'] = 'USD',
        'official' => $input['source_kind'] = 'OFFICIAL',
        'source' => $input['source_hash'] = '',
        'incomplete schedule' => $input['schedule_complete'] = false,
        'incomplete applications' => $input['applications_complete'] = false,
        'prior exceeds debit' => $input['previously_extinguished']['IBS'] = '31.00',
        'missing prior proof' => $input['prior_receipt_ids'] = [],
        'wrong child document' => $input['transactions'][0]['document_id'] = 'OTHER',
        'wrong child knowledge' => $input['transactions'][0]['known_at'] = '2026-10-06T15:00:00Z',
        'mixed provenance' => $input['transactions'][0]['source_kind'] = 'DOCUMENTARY_INPUT',
        'duplicate transaction' => $input['transactions'][1] = $input['transactions'][0],
        'duplicate installment' => $input['transactions'][1]['installment']['number'] = 1,
        'wrong installment count' => $input['transactions'][1]['installment']['count'] = 4,
        'reused settlement receipt' => $input['transactions'][1]['settlement']['receipt_id'] = $input['transactions'][0]['settlement']['receipt_id'],
        'opening overlaps settlement' => $input['history_as_of'] = '2026-10-02T13:00:00Z',
        'report unknown transaction' => $input['reports'][0]['transaction_ids'][0] = 'OTHER',
        'report repeated transaction' => $input['reports'][1]['transaction_ids'][] = 'SYNTHETIC.TX1',
        'report wrong psp' => $input['reports'][0]['receiver_psp_id'] = 'OTHER',
        'report before settlement' => $input['reports'][0]['sent_at'] = '2026-10-02T12:04:00Z',
        'report wrong own sum' => $input['reports'][0]['operation_taxes']['IBS'] = '21.00',
        'report wrong batch sum' => $input['reports'][0]['batch_taxes']['IBS'] = '70.00',
        'report invalid date' => $input['reports'][0]['reference_date'] = '2026-02-30',
        'report duplicate acknowledgement' => $input['reports'][1]['authority_acknowledgement_id'] = $input['reports'][0]['authority_acknowledgement_id'],
        'wrong rail' => $input['remittances'][0]['rail'] = 'TES',
        'remittance unknown report' => $input['remittances'][0]['report_ids'][] = 'OTHER',
        'partial consolidation' => $input['remittances'][0]['report_ids'] = ['R1'],
        'remittance wrong psp' => $input['remittances'][0]['receiver_psp_id'] = 'OTHER',
        'remittance before report' => $input['remittances'][0]['received_at'] = '2026-10-02T20:00:00Z',
        'duplicate remittance' => $input['remittances'][] = $input['remittances'][0],
        'planned application' => $input['applications'][0]['applied'] = false,
        'application wrong document' => $input['applications'][0]['document_id'] = 'OTHER',
        'application wrong tax' => $input['applications'][0]['tax'] = 'CBS',
        'application exceeds debit' => $input['applications'][0]['amount'] = '26.00',
        'application before receipt' => $input['applications'][0]['applied_at'] = '2026-10-05T11:00:00Z',
        'application duplicate receipt' => $input['applications'][1]['application_receipt_id'] = $input['applications'][0]['application_receipt_id'],
        'array reference' => $input['applications'][0]['remittance_id'] = [],
        'own funds exceeded' => $input['excess_determinations'][0]['amount'] = '6.00',
        'actual funds exceeded' => $input['remittances'][0]['amount'] = '29.00',
        'excess before application' => $input['excess_determinations'][0]['determined_at'] = '2026-10-05T12:30:00Z',
        'excess before receipt' => $input['excess_determinations'][0]['determined_at'] = '2026-10-05T11:00:00Z',
        'excess wrong document' => $input['excess_determinations'][0]['document_id'] = 'OTHER',
        'transfer wrong beneficiary' => $input['transfers'][0]['beneficiary_id'] = 'OTHER',
        'transfer wrong tax' => $input['transfers'][0]['tax'] = 'CBS',
        'transfer exceeds excess' => $input['transfers'][0]['amount'] = '6.00',
        'transfer before determination' => $input['transfers'][0]['transferred_at'] = '2026-10-05T13:00:00Z',
        'future transfer' => $input['transfers'][0]['transferred_at'] = '2026-10-07T12:00:00Z',
    };
    $report = (new SplitLedger158)->reconcile(sealLedger158($input));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report)->not->toHaveKey('taxes');
})->with(['contract', 'currency', 'official', 'source', 'incomplete schedule', 'incomplete applications', 'prior exceeds debit',
    'missing prior proof', 'wrong child document', 'wrong child knowledge', 'mixed provenance', 'duplicate transaction',
    'duplicate installment', 'wrong installment count', 'reused settlement receipt', 'opening overlaps settlement',
    'report unknown transaction', 'report repeated transaction', 'report wrong psp', 'report before settlement',
    'report wrong own sum', 'report wrong batch sum', 'report invalid date', 'report duplicate acknowledgement',
    'wrong rail', 'remittance unknown report', 'partial consolidation', 'remittance wrong psp', 'remittance before report',
    'duplicate remittance', 'planned application', 'application wrong document', 'application wrong tax', 'application exceeds debit',
    'application before receipt', 'application duplicate receipt', 'array reference', 'own funds exceeded', 'actual funds exceeded',
    'excess before application', 'excess before receipt', 'excess wrong document', 'transfer wrong beneficiary', 'transfer wrong tax',
    'transfer exceeds excess', 'transfer before determination', 'future transfer']);
