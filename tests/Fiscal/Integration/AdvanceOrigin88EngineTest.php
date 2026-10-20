<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;

it('qualifies an advance before supply in both runners without registering tax or changing ISS', function (array $case): void {
    $root = dirname(__DIR__, 3);
    $bundle = 'bundles/consumption-phase4b-2026.10.15-88-draft1.json';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $bundle);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->load($root.'/'.$case['path']);
    $rtc = (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray();
    $full = (new ScenarioRunService($factory, resource_path('fiscal'), bundleManifestPath: $bundle))
        ->run($root.'/'.$case['path'])->toArray();
    expect($rtc['status'])->toBe('ACTION_REQUIRED')->and($full['status'])->toBe('ACTION_REQUIRED')
        ->and($rtc['reason_codes'])->toContain($case['reason'])->and($full['reason_codes'])->toContain($case['reason']);
    $trace = array_column($rtc['trace'], null, 'stage');
    expect($trace['ADVANCE_ORIGIN']['production_ready'])->toBeFalse();
    if ($case['expected_nominal'] !== null) {
        $nominal = $trace['ADVANCE_ORIGIN_NOMINAL'];
        $expected = $case['expected_nominal'];
        expect($nominal['status'])->toBe('LOCAL_ADVANCE_NOMINAL_ONLY')
            ->and($nominal['registration_proven'])->toBeFalse()->and($nominal['cash_relief_qualified'])->toBeFalse()
            ->and($nominal['posting_authorized'])->toBeFalse()->and($nominal['credit_appropriation_authorized'])->toBeFalse();
        foreach (['event_date', 'rate_date', 'base_amount'] as $field) {
            expect($nominal['origin']['event'][$field])->toBe($expected[$field]);
        }
        foreach ($expected['taxes'] as $tax => $amount) {
            expect($nominal['taxes'][$tax]['nominal_amount'])->toBe($amount)
                ->and($nominal['taxes'][$tax]['cash_due'])->toBeNull();
        }
        $fullTrace = array_column($full['trace'], 'payload', 'type');
        expect($fullTrace['ISS_CALCULATION']['tax_amount'])->toBe('20.00');
    }
})->with(function (): array {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/storage/app/phase4b-advance88-preparation-20261015/advance88-case-manifest-v2.json'), true, 512, JSON_THROW_ON_ERROR);
    $cases = [];
    foreach ($manifest['cases'] as $case) {
        $cases[$case['case']] = [$case];
    }

    return $cases;
});
