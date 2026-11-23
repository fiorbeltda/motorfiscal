<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\RefundIntegration164;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/agent_a/refund164_engine_fixture.php';

it('integrates both refund procedures with the exact current collection report', function (string $procedure, bool $integrated): void {
    [$scenario, $knowledge] = refund164EngineScenario($procedure);
    $result = evaluate156($scenario, $knowledge, $integrated);
    if ($integrated) {
        $events = array_values(array_filter($result['trace'], static fn (array $event): bool => $event['type'] === 'CONSUMPTION_REFORM'));
        $result = $events[0]['payload'];
    }
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($result['decision'])->toBe([])->and($candidate['candidate_status'])->toBe('VALID');
    $report = $candidate['candidate_decision']['collection']['refund_deadlines164'];
    expect($report['status'])->toBe('LOCAL_REFUND_DEADLINES_ASSESSED')->and($report['coverage'])->toBe('FULL_EXCESSES')
        ->and($report['posted'])->toBeFalse();
})->with([['STANDARD_FALLBACK', false], ['STANDARD_FALLBACK', true], ['ACQUIRER_EXCESS', false], ['ACQUIRER_EXCESS', true]]);

it('rejects a valid refund book belonging to a different collection snapshot', function (): void {
    [$scenario, $knowledge] = refund164EngineScenario();
    $scenario['facts'][RefundIntegration164::FACT] = [refund164Input()];
    $candidate = array_column(evaluate156($scenario, $knowledge)['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_reasons'])->toContain('RTC_REFUND_CURRENT_COLLECTION_CONTEXT_CONFLICT');
});

it('loads the immutable refund policy and question without assuming official calendars', function (): void {
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.23-a164-draft1.json');
    expect(RefundIntegration164::valid($knowledge['documents']['rtc_code_catalog']))->toBeTrue()
        ->and($knowledge['documents']['rtc_code_catalog']['split_calendars_a163'])->toBe([])
        ->and(array_column($knowledge['documents']['questions'], 'fact_key'))->toContain(RefundIntegration164::FACT);
});

it('requires its own valid policy even when collection already has a valid report', function (): void {
    [$scenario, $knowledge] = refund164EngineScenario();
    $knowledge['documents']['rtc_code_catalog'][RefundIntegration164::KEY]['posting'] = true;
    expect(evaluate156($scenario, $knowledge)['reason_codes'])->toContain('RTC_REFUND_POLICY_INVALID_OR_NOT_ENABLED');
});

it('executes both native refund procedures deterministically with tamper refusal', function (string $procedure): void {
    $path = tempnam(sys_get_temp_dir(), 'refund164-');
    try {
        $input = refund164Input($procedure);
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        $args = ['input' => $path];
        expect(Artisan::call('fiscal:rtc:assess-refund-deadlines', $args))->toBe(0);
        $first = Artisan::output();
        expect(json_decode($first, true, 512, JSON_THROW_ON_ERROR)['replay_matches'])->toBeTrue();
        Artisan::call('fiscal:rtc:assess-refund-deadlines', $args);
        expect(Artisan::output())->toBe($first);
        $input['source_hash'] = str_repeat('0', 64);
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-refund-deadlines', $args))->toBe(1);
    } finally {
        unlink($path);
    }
})->with(['STANDARD_FALLBACK', 'ACQUIRER_EXCESS']);
