<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;

it('binds the H1 election through RTC and ISS without inventing future CBS or authenticating government receipts', function (string $name): void {
    $root = dirname(__DIR__, 3);
    $corpus = json_decode((string) file_get_contents($root.'/storage/app/phase4b-simples78-cli-v2-20261015/simples78-cli-corpus-validation.json'), true, 512, JSON_THROW_ON_ERROR);
    $case = current(array_filter($corpus['cases'], static fn (array $row): bool => $row['case'] === $name));
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $corpus['bundle']);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load($root.'/'.$case['scenario']);
    $rtc = (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray();
    $full = (new ScenarioRunService($factory, resource_path('fiscal'), bundleManifestPath: $corpus['bundle']))
        ->run($root.'/'.$case['scenario'])->toArray();
    $rtcSnapshot = json_decode((string) file_get_contents($root.'/'.$case['rtc_snapshot']), true, 512, JSON_THROW_ON_ERROR);
    $fullSnapshot = json_decode((string) file_get_contents($root.'/'.$case['integrated_snapshot']), true, 512, JSON_THROW_ON_ERROR);
    expect($rtc['status'])->toBe($case['expected_status'])
        ->and($rtc['reason_codes'])->toContain($case['expected_reason'])
        ->and($rtc)->toBe($rtcSnapshot['result'])
        ->and($full)->toBe($fullSnapshot['result'])
        ->and((new RtcScenarioRunService($factory))->replay($rtcSnapshot))->toBeTrue();
    $replayed = $factory->scenarioLoaderFromKnowledge($fullSnapshot['knowledge'])->fromArray($fullSnapshot['input']);
    expect($factory->makeFromKnowledge($fullSnapshot['knowledge'])->evaluate($replayed->input)->toArray())->toBe($full);
    $events = array_column($full['trace'], 'payload', 'type');
    expect($events['CONSUMPTION_REFORM']['reason_codes'])->toBe($rtc['reason_codes']);
    $trace = array_column($rtc['trace'], null, 'stage');
    if ($case['election_locally_qualified']) {
        expect($events['ISS_CALCULATION']['tax_amount'])->toBe('20.00')
            ->and($trace['SIMPLES_REGULAR_ELECTION_194']['government_receipt_authenticated'])->toBeFalse()
            ->and($trace['SIMPLES_REGULAR_ELECTION_194']['production_ready'])->toBeFalse()
            ->and($trace['SIMPLES_REGULAR_OPTION']['scope'])->toBe('2027_FIRST_SEMESTER')
            ->and($rtc['reason_codes'])->toContain('RTC_RATE_NOT_PROVEN:CBS');
    } elseif ($name === 'second-semester-unchanged') {
        expect($trace)->not->toHaveKey('SIMPLES_REGULAR_ELECTION_194')
            ->and($trace['SIMPLES_REGULAR_OPTION']['scope'])->toBe('2027_SECOND_SEMESTER');
    }
})->with(['standard-last-filing-day', 'first-effective-day', 'last-effective-day', 'registered-last-standard-day',
    'registration-2026-10-31', 'registration-2026-12-31', 'outside-filing-2026-10-31',
    'registration-option-not-same-day', 'missing-provider.cnpj_registered_on', 'activity-date-not-registration-date',
    'status-CANCELLED', 'expired-status-after-cancellation', 'mei-excluded',
    'missing-rtc.simples_option_route_evidence_id', 'second-semester-unchanged']);

it('replays accepted predecessor foreign-buyer and partial-export decisions without rewriting snapshots', function (): void {
    $root = dirname(__DIR__, 3);
    $corpus = json_decode((string) file_get_contents($root.'/storage/app/phase4b-simples78-cli-v2-20261015/simples78-cli-corpus-validation.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($corpus['prior_compatibility'] as $case) {
        $path = $root.'/'.$case['path'];
        expect(hash_file('sha256', $path))->toBe($case['sha256']);
        $snapshot = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $factory = new FiscalEngineFactory;
        if (str_contains($case['path'], 'export-snapshot')) {
            expect((new RtcScenarioRunService($factory))->replay($snapshot))->toBeTrue();
        } else {
            $scenario = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
            expect($factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($scenario->input)->toArray())->toBe($snapshot['result']);
        }
        expect(hash_file('sha256', $path))->toBe($case['sha256']);
    }
});
