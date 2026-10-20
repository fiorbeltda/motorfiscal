<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;

it('binds captured prior advances to the qualified final operation in both runners without posting', function (array $case): void {
    $root = dirname(__DIR__, 3);
    $bundle = 'bundles/consumption-phase4b-2026.10.15-91-draft1.json';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $bundle);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->load($root.'/'.$case['path']);
    $rtc = (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray();
    $full = (new ScenarioRunService($factory, resource_path('fiscal'), bundleManifestPath: $bundle))->run($root.'/'.$case['path'])->toArray();
    expect($rtc['status'])->toBe('ACTION_REQUIRED')->and($full['status'])->toBe('ACTION_REQUIRED')
        ->and($rtc['reason_codes'])->toContain($case['reason'])->and($full['reason_codes'])->toContain($case['reason']);
    $trace = array_column($rtc['trace'], null, 'stage');
    if ($case['expected_cbs_balance'] !== null) {
        $result = $trace['RECORDED_ADVANCE_RECONCILIATION'];
        expect($result['status'])->toBe('LOCAL_CAPTURED_ADVANCE_FINAL_RECONCILIATION_ONLY')
            ->and($result['taxes']['CBS']['arithmetic_balance'])->toBe($case['expected_cbs_balance'])
            ->and($result['prior_registration_locally_bound'])->toBeTrue()
            ->and($result['prior_advance_debits_proven'])->toBeFalse()->and($result['actual_registration_consumed'])->toBeFalse()
            ->and($result['posting_authorized'])->toBeFalse()->and($result['credit_appropriation_authorized'])->toBeFalse()
            ->and($result['taxes']['CBS']['cash_due'])->toBeNull()->and($result['taxes']['CBS']['credit_granted'])->toBe('0.00');
        expect($result['qualified_final_date_basis'])->toBe(match ($case['case']) {
            'document-issue-presumption' => 'DOCUMENT_ISSUE_PRESUMPTION',
            'document-expected-date-presumption' => 'DOCUMENT_EXPECTED_DATE_PRESUMPTION',
            default => 'ACTUAL_SUPPLY_EVIDENCE',
        });
        $events = array_column($full['trace'], 'payload', 'type');
        expect($events['ISS_CALCULATION']['tax_amount'])->toBe('20.00');
    }
    if ($case['case'] === 'opt-in-absent') {
        expect($trace)->not->toHaveKey('RECORDED_ADVANCE_RECONCILIATION');
    }
})->with(function (): array {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/storage/app/phase4b-advance91-integration-20261015/advance91-case-manifest-v2.json'), true, 512, JSON_THROW_ON_ERROR);
    $cases = [];
    foreach ($manifest['cases'] as $case) {
        $cases[$case['case']] = [$case];
    }

    return $cases;
});
