<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitDeadlineIntegration163;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split163_engine_fixture.php';

it('binds each clock to validated records in both engine entry points', function (string $mode, bool $integrated, bool $multiple): void {
    [$scenario, $knowledge] = split163EngineScenario($mode, $multiple);
    $result = evaluate156($scenario, $knowledge, $integrated);
    if ($integrated) {
        $events = array_values(array_filter($result['trace'], static fn (array $event): bool => $event['type'] === 'CONSUMPTION_REFORM'));
        $result = $events[0]['payload'];
    }
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($result['decision'])->toBe([])->and($candidate['candidate_status'])->toBe('VALID');
    $report = $candidate['candidate_decision']['collection']['deadlines163'];
    expect($report['coverage'])->toBe('ONLY_EXPLICIT_REQUESTS_NOT_COMPLETE_LEDGER')
        ->and($report['assessments'][0]['status'])->toBe('LOCAL_SPLIT_DEADLINE_ASSESSED')
        ->and($report['financial_completion_proven'])->toBeFalse();
})->with([['PSP_REMITTANCE', false, false], ['PSP_REMITTANCE', true, false], ['SEGREGATION_REPORT', false, false],
    ['SEGREGATION_REPORT', true, false], ['SIMPLIFIED_EXCESS_TRANSFER', false, false], ['SIMPLIFIED_EXCESS_TRANSFER', true, false],
    ['PSP_REMITTANCE', false, true], ['SEGREGATION_REPORT', true, true]]);

it('refuses caller-supplied clocks that differ from the financial book', function (string $case): void {
    [$scenario, $knowledge] = split163EngineScenario();
    $request = $scenario['facts'][SplitDeadlineIntegration163::FACT][0];
    match ($case) {
        'receipt' => $request['reference_id'] = 'OTHER.RECEIPT',
        'settlement' => $request['events']['financial_settlement']['at'] = '2026-09-26T12:06:00Z',
        'report' => $request['events']['segregation_report']['source_hash'] = str_repeat('0', 64),
        'performance' => $request['performance']['at'] = '2026-09-28T12:01:00Z',
        'omit performance' => $request['performance'] = null,
        'knowledge' => $request['known_at'] = '2026-11-23T16:00:00Z',
    };
    $scenario['facts'][SplitDeadlineIntegration163::FACT] = [seal158($request)];
    $result = evaluate156($scenario, $knowledge);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_status'])->toBe('ACTION_REQUIRED')->and($candidate['candidate_decision'])->toBe([]);
})->with(['receipt', 'settlement', 'report', 'performance', 'omit performance', 'knowledge']);

it('rejects duplicate requests and mismatched report reference dates', function (bool $duplicate): void {
    [$scenario, $knowledge] = split163EngineScenario();
    if ($duplicate) {
        $scenario['facts'][SplitDeadlineIntegration163::FACT][] = $scenario['facts'][SplitDeadlineIntegration163::FACT][0];
    } else {
        $book = $scenario['facts']['rtc.split_payment_ledger'][0];
        $on = (new DateTimeImmutable($book['reports'][0]['reference_date']))->modify('-1 day')->format('Y-m-d');
        $book['reports'][0]['reference_date'] = $on;
        $book['remittances'][0]['reference_date'] = $book['remittances'][1]['reference_date'] = $on;
        $scenario['facts']['rtc.split_payment_ledger'][0] = sealLedger158($book);
    }
    $candidate = array_column(evaluate156($scenario, $knowledge)['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_reasons'])->toContain($duplicate ? 'RTC_SPLIT_DEADLINE_DUPLICATE_REQUEST' : 'RTC_SPLIT_DEADLINE_REPORT_REFERENCE_DATE_CONFLICT');
})->with([false, true]);

it('loads hashed calendar policy and explicit questions without manufacturing an official calendar', function (): void {
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.23-a163-draft1.json');
    expect($knowledge['documents']['rtc_code_catalog'][SplitDeadlineIntegration163::KEY])->toBe(SplitDeadlineIntegration163::POLICY)
        ->and($knowledge['documents']['rtc_code_catalog'][SplitDeadlineIntegration163::CALENDARS])->toBe([])
        ->and(array_column($knowledge['documents']['questions'], 'fact_key'))->toContain(SplitDeadlineIntegration163::FACT);
});

it('binds simplified conclusion and excess events independently of caller dates', function (string $case): void {
    [$scenario, $knowledge] = split163EngineScenario('SIMPLIFIED_EXCESS_TRANSFER');
    $input = $scenario['facts'][SplitDeadlineIntegration163::FACT][0];
    match ($case) {
        'closure' => $input['events']['apuration_concluded']['at'] = '2026-10-01T13:00:00Z',
        'determination' => $input['events']['excess_determined']['source_hash'] = str_repeat('0', 64),
        'reference' => $input['reference_id'] = 'OTHER.EXCESS',
        'performance' => $input['performance'] = split163Event('UNKNOWN.TRANSFER', '2026-10-05T12:00:00Z'),
    };
    $scenario['facts'][SplitDeadlineIntegration163::FACT] = [seal158($input)];
    $candidate = array_column(evaluate156($scenario, $knowledge)['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_status'])->toBe('ACTION_REQUIRED')->and($candidate['candidate_decision'])->toBe([]);
})->with(['closure', 'determination', 'reference', 'performance']);

it('refuses unsupported policy rather than silently skipping timing', function (): void {
    [$scenario, $knowledge] = split163EngineScenario();
    unset($knowledge['documents']['rtc_code_catalog'][SplitDeadlineIntegration163::KEY]);
    expect(evaluate156($scenario, $knowledge)['reason_codes'])->toContain('RTC_SPLIT_DEADLINE_POLICY_INVALID_OR_NOT_ENABLED');
});

it('runs each installed deadline rule deterministically and refuses unsealed edits', function (string $rule): void {
    $path = tempnam(sys_get_temp_dir(), 'split163-');
    try {
        $input = split163Input($rule);
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        $args = ['input' => $path];
        expect(Artisan::call('fiscal:rtc:assess-split-deadline', $args))->toBe(0);
        $first = Artisan::output();
        expect(json_decode($first, true, 512, JSON_THROW_ON_ERROR)['replay_matches'])->toBeTrue();
        Artisan::call('fiscal:rtc:assess-split-deadline', $args);
        expect(Artisan::output())->toBe($first);
        $input['source_hash'] = str_repeat('0', 64);
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-deadline', $args))->toBe(1);
    } finally {
        unlink($path);
    }
})->with(['PSP_REMITTANCE', 'SEGREGATION_REPORT', 'SIMPLIFIED_EXCESS_TRANSFER']);
