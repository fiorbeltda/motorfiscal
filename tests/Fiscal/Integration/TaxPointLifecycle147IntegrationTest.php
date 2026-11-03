<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AdvanceContext88;
use App\Modules\Fiscal\ConsumptionReform\RecordedAdvanceContext91;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

require_once dirname(__DIR__, 3).'/scripts/agent_a/temporal147_fixture.php';

it('propagates the exact lifecycle boundary through both native runners and whole RTC replay', function (string $key, string $known, string $status): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.15-91-draft1.json');
    // Synthetic lifecycle row exercises mechanics; no installed catalog or official source is changed.
    $knowledge['documents']['rtc_transition_catalog']['tax_point_event_policy'][$key] = '2026-10-15T23:00:00.000001Z';
    $knowledge['content_hashes']['rtc_transition_catalog'] = CanonicalJson::hash($knowledge['documents']['rtc_transition_catalog']);
    $knowledge['documents']['bundle_manifest']['content_hashes']['rtc_transition_catalog'] = $knowledge['content_hashes']['rtc_transition_catalog'];
    $knowledge['content_hashes']['bundle_manifest'] = CanonicalJson::hash($knowledge['documents']['bundle_manifest']);
    $source = temporal147Scenario();
    $source['knowledge_at'] = $known;
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $service = new RtcScenarioRunService($factory);
    $rtc = $service->evaluate($knowledge, $loaded)->toArray();
    $full = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
    expect($rtc['status'])->toBe($status)
        ->and($full['status'])->toBe($status === 'VALID' ? 'VALID_WITH_WARNINGS' : $status)
        ->and($service->replay(['input' => $source, 'knowledge' => $knowledge, 'result' => $rtc]))->toBeTrue();
    if ($status === 'VALID') {
        expect($rtc['decision']['cbs']['nominal_amount'])->toBe('9.00')
            ->and($full['decision']['consumption_reform']['decision'])->toBe($rtc['decision']);
    } else {
        expect($rtc['reason_codes'])->toContain('RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE')
            ->and($full['reason_codes'])->toContain('RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE');
    }
})->with((function (): array {
    $rows = [];
    foreach (['revoked_at', 'superseded_at'] as $key) {
        $rows[] = [$key, '2026-10-15T23:00:00Z', 'VALID'];
        $rows[] = [$key, '2026-10-15T23:00:00.000001Z', 'ACTION_REQUIRED'];
        $rows[] = [$key, '2026-10-15T23:00:00.000002Z', 'ACTION_REQUIRED'];
    }

    return $rows;
})());

it('shares the corrected source-lifecycle guard with origin88 and recorded-final91 without bypassing their facts', function (): void {
    $policy = [...temporal147Policy(), 'revoked_at' => '2026-10-15T23:00:00.000001Z'];
    $known = new DateTimeImmutable('2026-10-15T23:00:00Z');
    $origin = (new AdvanceContext88)->qualify([], $known, $policy);
    $recorded = (new RecordedAdvanceContext91(new TemporalRateCatalog([])))->reconcile([], [], $known, $policy);
    expect($origin['reason'])->toBe('RTC_ADVANCE_SINGLE_CONTEXT_REQUIRED')
        ->and($recorded['reason'])->toBe('RTC_RECORDED_ADVANCE_FINAL_CONTEXT_REQUIRED');
    $at = new DateTimeImmutable('2026-10-15T23:00:00.000001Z');
    expect((new AdvanceContext88)->qualify([], $at, $policy)['reason'])->toBe('RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE')
        ->and((new RecordedAdvanceContext91(new TemporalRateCatalog([])))->reconcile([], [], $at, $policy)['reason'])
        ->toBe('RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE');
});
