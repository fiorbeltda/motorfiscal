<?php

declare(strict_types=1);
use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;

require_once dirname(__DIR__, 3).'/scripts/agent_b/split158_engine_fixture.php';

// B34: A158 split-payment ledger integrated in the engine behind its own gate (in-memory candidate).

/** @return array<string, mixed> */
function b34Candidate(array $result): array
{
    return array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
}

it('reconciles the split ledger in the candidate decision and withholds a synthetic final decision', function (bool $integrated): void {
    [$input, $knowledge] = splitB34Scenario();
    $result = evaluate156($input, $knowledge, $integrated);
    if ($integrated) {
        $events = array_values(array_filter($result['trace'], static fn (array $event): bool => $event['type'] === 'CONSUMPTION_REFORM'));
        $result = $events[0]['payload'];
    }
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain('RTC_SYNTHETIC_TEST_ONLY')
        ->and($result['official'])->toBeFalse()->and($result['decision'])->toBe([]);
    $candidate = b34Candidate($result);
    $split = $candidate['candidate_decision']['collection']['split_ledger158'];
    expect($candidate['candidate_status'])->toBe('VALID')
        ->and($candidate['candidate_decision']['collection']['method'])->toBe('SPLIT_PAYMENT')
        ->and($candidate['candidate_decision']['collection']['collector'])->toBe('PAYMENT_SERVICE_PROVIDER')
        ->and($split['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($split['taxes']['IBS']['split_applied'])->toBe('1.00')->and($split['taxes']['CBS']['split_applied'])->toBe('9.00')
        ->and($split['taxes']['IBS']['remaining'])->toBe('0.00')->and($split['taxes']['CBS']['remaining'])->toBe('0.00')
        ->and($split['posted'])->toBeFalse()->and($split['deployment_proven'])->toBeFalse()
        ->and($candidate['candidate_decision']['ibs']['cash_due'])->toBe('1.00')
        ->and($candidate['candidate_decision']['cbs']['cash_due'])->toBe('9.00');
})->with([false, true]);

it('keeps the historical split route when the gate is absent', function (): void {
    [$input, $knowledge] = splitB34Scenario(false);
    unset($input['facts']['rtc.split_payment_ledger']);
    $result = evaluate156($input, $knowledge);
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['collection']['split_payment_status'])->toBe('ELIGIBLE_UNCONFIRMED')
        ->and($result['decision']['collection'])->not->toHaveKey('split_ledger158');
});

it('never reaches the ledger without a qualified deployment row', function (): void {
    [$input, $knowledge] = splitB34Scenario(true, false);
    $result = evaluate156($input, $knowledge);
    $candidate = b34Candidate($result);
    expect($candidate['candidate_reasons'])->toBe(['RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN'])
        ->and(array_column($candidate['trace'] ?? $result['trace'], 'stage'))->not->toContain('SPLIT_LEDGER158');
});

it('requires the ledger and refuses every binding conflict', function (string $case, string $reason): void {
    [$input, $knowledge] = splitB34Scenario();
    $ledger = $input['facts']['rtc.split_payment_ledger'][0];
    match ($case) {
        'missing' => $input['facts']['rtc.split_payment_ledger'] = [],
        'document' => $input['facts']['rtc.document_id'] = 'dps-other',
        'transaction' => $input['facts']['rtc.payment_transaction_id'] = 'PAY.OTHER',
        'debit' => $input['facts']['rtc.split_payment_ledger'] = [resealB34(array_replace($ledger, ['debits' => ['IBS' => '2.00', 'CBS' => '9.00']]))],
        'knowledge' => $input['knowledge_at'] = '2026-11-06T13:00:00Z',
        'incomplete' => $input['facts']['rtc.split_payment_ledger'] = [resealB34(array_replace($ledger, ['remittances_complete' => false]))],
    };
    $result = evaluate156($input, $knowledge);
    // Without a synthetic ledger the engine decides directly; otherwise the refusal lives in the withheld candidate.
    $reasons = $case === 'missing' ? $result['reason_codes'] : b34Candidate($result)['candidate_reasons'];
    expect($reasons)->toContain($reason);
    if ($case === 'missing') {
        expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['missing_facts'])->toBe(['rtc.split_payment_ledger']);
    }
})->with([
    ['missing', 'RTC_SPLIT_LEDGER_REQUIRED'],
    ['document', 'RTC_SPLIT_LEDGER_FACT_CONFLICT'],
    ['transaction', 'RTC_SPLIT_LEDGER_TRANSACTION_CONFLICT'],
    ['debit', 'RTC_SPLIT_LEDGER_DEBIT_CONFLICT'],
    ['knowledge', 'RTC_SPLIT_LEDGER_KNOWLEDGE_CONFLICT'],
    ['incomplete', 'A158_COMPLETE_LEDGER_REQUIRED'],
]);

it('refuses an altered gate policy', function (): void {
    [$input, $knowledge] = splitB34Scenario();
    $knowledge['documents']['rtc_code_catalog']['split_payment_a158']['posting'] = true;
    $result = evaluate156($input, $knowledge);
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toBe(['RTC_SPLIT_LEDGER_POLICY_INVALID']);
});

it('is deterministic and the embedded report replays through SplitLedger158', function (): void {
    [$input, $knowledge] = splitB34Scenario();
    $first = evaluate156($input, $knowledge);
    expect(evaluate156($input, $knowledge))->toBe($first);
    $report = b34Candidate($first)['candidate_decision']['collection']['split_ledger158'];
    expect((new SplitLedger158)->replay($report))->toBeTrue();
});
