<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AcquirerCollection157;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/collection157_fixture.php';

it('applies receipts only to outstanding operation debt and tracks excess transfers separately', function (): void {
    $service = new AcquirerCollection157;
    $report = $service->reconcile(collection157Ledger());
    expect($report['status'])->toBe('LOCAL_ACQUIRER_COLLECTION_RECONCILED')
        ->and($report['taxes']['IBS'])->toBe(['debit' => '100.00', 'prior' => '20.00', 'remaining' => '0.00',
            'received' => '110.00', 'applied' => '80.00', 'other_applied' => '0.00', 'excess' => '30.00', 'transferred' => '20.00', 'pending_transfer_to_supplier' => '10.00'])
        ->and($report['taxes']['CBS']['applied'])->toBe('80.00')->and($report['taxes']['CBS']['pending_transfer_to_supplier'])->toBe('10.00')
        ->and($report['option_exercised_in_supplied_receipts'])->toBeTrue()->and($report['posted'])->toBeFalse()
        ->and($report['payment_performed'])->toBeFalse()->and($report['deadline_date'])->toBeNull()
        ->and($service->replay($report))->toBeTrue();
    $report['taxes']['IBS']['applied'] = '100.00';
    unset($report['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($report);
    expect($service->replay($report))->toBeFalse();
});

it('does not turn eligibility or an empty receipt list into an exercised option', function (): void {
    $input = collection157Ledger();
    $input['payments'] = $input['transfers'] = [];
    $report = (new AcquirerCollection157)->reconcile(seal157($input));
    expect($report['option_exercised_in_supplied_receipts'])->toBeFalse()->and($report['taxes']['IBS']['remaining'])->toBe('80.00')
        ->and($report['taxes']['CBS']['remaining'])->toBe('80.00');
});

it('refuses rehashed contradictions rather than reporting a paid or transferred amount', function (string $case): void {
    $input = collection157Ledger();
    match ($case) {
        'history incomplete' => $input['history_complete'] = false,
        'payments incomplete' => $input['payments_complete'] = false,
        'transfers incomplete' => $input['transfers_complete'] = false,
        'split' => $input['instrument_split_capable'] = true,
        'nonregular' => $input['acquirer_regime'] = 'SIMPLES',
        'future operation' => $input['operation_date'] = '2027-01-01',
        'excess prior' => $input['previously_extinguished']['IBS'] = '101.00',
        'missing prior' => $input['prior_receipt_ids'] = [],
        'reused authority receipt' => $input['payments'][1]['authority_receipt_id'] = $input['payments'][0]['authority_receipt_id'],
        'prior authority receipt' => $input['payments'][0]['authority_receipt_id'] = 'PRIOR.IBS',
        'duplicate id' => $input['payments'][1]['id'] = $input['payments'][0]['id'],
        'noncanonical id' => $input['payments'][0]['authority_receipt_id'] = ' RECEIPT ',
        'unknown tax' => $input['payments'][0]['tax'] = 'ISS',
        'wrong document' => $input['payments'][0]['document_id'] = 'OTHER',
        'wrong supplier' => $input['payments'][0]['supplier_id'] = 'OTHER',
        'wrong acquirer' => $input['payments'][0]['acquirer_id'] = 'OTHER',
        'wrong transaction' => $input['payments'][0]['payment_transaction_id'] = 'OTHER',
        'wrong payer' => $input['payments'][0]['payer_id'] = 'OTHER',
        'unsettled' => $input['payments'][0]['settled'] = false,
        'negative' => $input['payments'][0]['amount'] = '-1.00',
        'float' => $input['payments'][0]['amount'] = 1.0,
        'currency' => $input['payments'][0]['currency'] = 'USD',
        'overlap history' => $input['history_as_of'] = '2026-10-02T12:00:00Z',
        'future history' => $input['history_as_of'] = '2027-01-01T00:00:00Z',
        'future payment' => $input['payments'][0]['settled_at'] = '2027-01-01T00:00:00Z',
        'invalid timestamp' => $input['payments'][0]['settled_at'] = '2026-02-30T00:00:00Z',
        'ambiguous time' => $input['payments'][1]['settled_at'] = $input['payments'][0]['settled_at'],
        'cross tax transfer' => $input['transfers'][0]['tax'] = 'CBS',
        'transfer excess' => $input['transfers'][0]['amount'] = '31.00',
        'transfer beneficiary' => $input['transfers'][0]['beneficiary_id'] = 'ACQUIRER157',
        'early transfer' => $input['transfers'][0]['transferred_at'] = '2026-10-01T00:00:00Z',
    };
    $report = (new AcquirerCollection157)->reconcile(seal157($input));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report)->not->toHaveKey('taxes');
})->with(['history incomplete', 'payments incomplete', 'transfers incomplete', 'split', 'nonregular', 'future operation',
    'excess prior', 'missing prior', 'reused authority receipt', 'prior authority receipt', 'duplicate id', 'noncanonical id', 'unknown tax',
    'wrong document', 'wrong supplier', 'wrong acquirer', 'wrong transaction', 'wrong payer', 'unsettled', 'negative', 'float',
    'currency', 'overlap history', 'future history', 'future payment', 'invalid timestamp', 'ambiguous time', 'cross tax transfer', 'transfer excess', 'transfer beneficiary', 'early transfer']);

it('keeps receipts scoped to a tax and reproduces partial full and excess collection in four periods', function (int $year, string $received, string $applied, string $remaining, string $excess): void {
    $input = collection157Ledger();
    foreach (['known_at', 'operation_date', 'operation_at', 'history_as_of'] as $key) {
        $input[$key] = str_replace('2026', (string) $year, $input[$key]);
    }
    $input['payments'] = [$input['payments'][0], $input['payments'][2]];
    $input['payments'][0]['amount'] = $received;
    foreach ($input['payments'] as &$payment) {
        $payment['settled_at'] = str_replace('2026', (string) $year, $payment['settled_at']);
    }
    unset($payment);
    $input['transfers'] = [];
    $report = (new AcquirerCollection157)->reconcile(seal157($input));
    expect($report['taxes']['IBS']['applied'])->toBe($applied)->and($report['taxes']['IBS']['remaining'])->toBe($remaining)
        ->and($report['taxes']['IBS']['excess'])->toBe($excess)->and($report['taxes']['CBS']['applied'])->toBe('80.00')
        ->and($report['official'])->toBeFalse();
})->with([2026, 2027, 2030, 2033])->with([['0.00', '0.00', '80.00', '0.00'], ['40.00', '40.00', '40.00', '0.00'],
    ['80.00', '80.00', '0.00', '0.00'], ['100.00', '80.00', '0.00', '20.00']]);

it('sorts actual settlement instants and rejects a repeated authority transfer despite different internal IDs', function (): void {
    $service = new AcquirerCollection157;
    $input = collection157Ledger();
    $normal = $service->reconcile($input);
    $input['payments'] = array_reverse($input['payments']);
    $reordered = $service->reconcile(seal157($input));
    expect($reordered['taxes'])->toBe($normal['taxes'])->and($reordered['receipts'])->toBe($normal['receipts']);
    $input['transfers'][0]['amount'] = '5.00';
    $input['transfers'][] = [...$input['transfers'][0], 'id' => 'ANOTHER_INTERNAL_ID'];
    expect($service->reconcile(seal157($input))['reason'])->toBe('A157_TRANSFER_LINK_CONFLICT');
});

it('consumes independently evidenced intervening extinctions without treating a credit plan as payment', function (): void {
    $input = collection157Ledger();
    $event = ['id' => 'OTHER1', 'tax' => 'IBS', 'currency' => 'BRL', 'document_id' => $input['document_id'],
        'supplier_id' => $input['supplier_id'], 'acquirer_id' => $input['acquirer_id'], 'payment_transaction_id' => $input['payment_transaction_id'],
        'modality' => 'COMPENSATION', 'applied' => true, 'amount' => '10.00', 'applied_at' => '2026-10-02T12:00:00Z',
        'authority_receipt_id' => 'SYNTHETIC.APPLIED.COMPENSATION1', 'evidence_id' => 'SYNTHETIC.EVIDENCE.OTHER1'];
    $input['other_extinctions'] = [$event];
    $service = new AcquirerCollection157;
    $report = $service->reconcile(seal157($input));
    expect($report['taxes']['IBS']['applied'])->toBe('70.00')->and($report['taxes']['IBS']['other_applied'])->toBe('10.00')
        ->and($report['taxes']['IBS']['excess'])->toBe('40.00')->and($report['taxes']['IBS']['pending_transfer_to_supplier'])->toBe('20.00');
    $input['other_extinctions'][0]['applied'] = false;
    expect($service->reconcile(seal157($input))['reason'])->toBe('A157_OTHER_EXTINCTION_PROOF_REQUIRED');
    $input['other_extinctions'] = [[...$event, 'amount' => '41.00']];
    expect($service->reconcile(seal157($input))['reason'])->toBe('A157_OTHER_EXTINCTION_EXCEEDS_REMAINING');
});

it('requires the snapshot to cover all acquirer receipts and rejects unsupported monetary components', function (): void {
    $input = collection157Ledger();
    $input['history_excludes_acquirer_collections'] = false;
    expect((new AcquirerCollection157)->reconcile(seal157($input))['reason'])->toBe('A157_COMPLETE_LEDGER_REQUIRED');
    $input = collection157Ledger();
    $input['debits']['ISS'] = '5.00';
    expect((new AcquirerCollection157)->reconcile(seal157($input))['reason'])->toBe('A157_TWO_TAX_COMPONENTS_REQUIRED');
    $input = collection157Ledger();
    $input['payments'][0]['amount'] = '1.00';
    expect((new AcquirerCollection157)->reconcile($input)['reason'])->toBe('A157_INPUT_HASH_CONFLICT');
});

it('preserves numeric-looking receipt identities as strings in a stable output list', function (): void {
    $input = collection157Ledger();
    $input['payments'][0]['id'] = '0';
    $input['payments'][1]['id'] = '0001';
    $input['transfers'][0]['collection_payment_id'] = '0001';
    $report = (new AcquirerCollection157)->reconcile(seal157($input));
    expect(array_is_list($report['receipts']))->toBeTrue()
        ->and(array_column($report['receipts'], 'collection_payment_id'))->toContain('0', '0001')
        ->and($report['taxes']['IBS']['pending_transfer_to_supplier'])->toBe('10.00');
});
