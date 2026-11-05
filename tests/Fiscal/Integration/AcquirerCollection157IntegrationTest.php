<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/agent_a/collection157_fixture.php';

it('integrates article36 reconciliation behind its own gate and withholds a synthetic final decision', function (bool $integrated): void {
    [$input, $knowledge] = collection157Scenario();
    $result = evaluate156($input, $knowledge, $integrated);
    if ($integrated) {
        $events = array_values(array_filter($result['trace'], static fn (array $event): bool => $event['type'] === 'CONSUMPTION_REFORM'));
        $result = $events[0]['payload'];
    }
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['official'])->toBeFalse()->and($result['decision'])->toBe([]);
    $trace = array_column($result['trace'], null, 'stage');
    $candidate = $trace['SYNTHETIC_ARITHMETIC156']['candidate_decision'];
    expect($trace['SYNTHETIC_ARITHMETIC156']['candidate_status'])->toBe('VALID')
        ->and($candidate['collection']['reconciliation157']['taxes']['IBS']['remaining'])->toBe('0.00')
        ->and($candidate['collection']['reconciliation157']['taxes']['IBS']['pending_transfer_to_supplier'])->toBe('0.20')
        ->and($candidate['collection']['reconciliation157']['taxes']['CBS']['remaining'])->toBe('1.00')
        ->and($candidate['ibs']['cash_due'])->toBe('1.00')->and($candidate['cbs']['cash_due'])->toBe('9.00');
})->with([false, true]);

it('preserves historical behavior with the new gate absent', function (): void {
    [$input, $knowledge] = collection157Scenario(false);
    unset($input['facts']['rtc.acquirer_collection_ledger']);
    $result = evaluate156($input, $knowledge);
    expect($result['status'])->toBe('VALID')->and($result['decision']['collection'])->not->toHaveKey('reconciliation157');
});

it('requires the complete ledger and refuses a debit different from the calculated liability', function (): void {
    [$input, $knowledge] = collection157Scenario();
    unset($input['facts']['rtc.acquirer_collection_ledger']);
    expect(evaluate156($input, $knowledge)['reason_codes'])->toContain('RTC_ACQUIRER_COLLECTION_LEDGER_REQUIRED');
    [$input, $knowledge] = collection157Scenario();
    $ledger = $input['facts']['rtc.acquirer_collection_ledger'][0];
    $ledger['debits']['IBS'] = '100.00';
    $input['facts']['rtc.acquirer_collection_ledger'] = [seal157($ledger)];
    expect(evaluate156($input, $knowledge)['reason_codes'])->toContain('RTC_ACQUIRER_COLLECTION_DEBIT_OR_INSTRUMENT_CONFLICT');
});

it('offers a deterministic native command and refuses an incomplete ledger with exit one', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'a157-');
    try {
        $ledger = collection157Ledger();
        file_put_contents($path, json_encode($ledger, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:reconcile-acquirer-collection', ['ledger' => $path]))->toBe(0);
        $first = Artisan::output();
        Artisan::call('fiscal:rtc:reconcile-acquirer-collection', ['ledger' => $path]);
        expect(Artisan::output())->toBe($first);
        $ledger['history_complete'] = false;
        file_put_contents($path, json_encode(seal157($ledger), JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:reconcile-acquirer-collection', ['ledger' => $path]))->toBe(1);
    } finally {
        unlink($path);
    }
});
