<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitPaymentIntegrationB34;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split160_engine_fixture.php';

const A160_BUNDLE = 'bundles/consumption-phase4b-2026.11.23-a160-draft1.json';

it('activates fiscal revision histories from the installed A160 bundle in both engine routes', function (bool $integrated): void {
    [$scenario, $knowledge] = split160EngineScenario();
    $result = evaluate156($scenario, $knowledge, $integrated);
    if ($integrated) {
        $events = array_values(array_filter($result['trace'], static fn (array $event): bool => $event['type'] === 'CONSUMPTION_REFORM'));
        $result = $events[0]['payload'];
    }
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_status'])->toBe('VALID')
        ->and($result['decision'])->toBe([])
        ->and($candidate['candidate_decision']['collection']['split_ledger158']['transactions'][0]['active_fiscal_revision'])->toBe('SYNTHETIC.A160.V2')
        ->and($candidate['candidate_decision']['collection']['split_ledger158']['taxes']['IBS']['split_applied'])->toBe('1.00');
})->with([false, true]);

it('loads the exact lifecycle policy while preserving the old policy and transition catalog', function (): void {
    $factory = new FiscalEngineFactory;
    $new = $factory->captureKnowledge(resource_path('fiscal'), A160_BUNDLE);
    $old = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.23-a159-draft1.json');
    expect($new['documents']['rtc_code_catalog'][SplitPaymentIntegrationB34::KEY])->toBe(SplitPaymentIntegrationB34::LIFECYCLE_POLICY)
        ->and($old['documents']['rtc_code_catalog'][SplitPaymentIntegrationB34::KEY])->toBe(SplitPaymentIntegrationB34::SCHEDULE_POLICY)
        ->and(CanonicalJson::hash($new['documents']['rtc_transition_catalog']))->toBe(CanonicalJson::hash($old['documents']['rtc_transition_catalog']))
        ->and($new['documents']['bundle_manifest']['content_hashes']['fact_definitions'])->toBe($old['documents']['bundle_manifest']['content_hashes']['fact_definitions']);
});

it('keeps the deployment refusal and rejects an altered reset policy', function (): void {
    [$scenario, $knowledge] = split160EngineScenario();
    $knowledge['documents']['rtc_transition_catalog']['split_deployments'] = [];
    $result = evaluate156($scenario, $knowledge);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_reasons'])->toContain('RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN');
    $knowledge['documents']['rtc_code_catalog'][SplitPaymentIntegrationB34::KEY]['version'] = 'UNQUALIFIED';
    expect(SplitPaymentIntegrationB34::valid($knowledge['documents']['rtc_code_catalog']))->toBeFalse();
});

it('runs the fiscal history CLI with deterministic replay and refuses a missing reset proof', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'split160-');
    try {
        $input = split160Transaction();
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        $args = ['input' => $path, '--mode' => 'fiscal-history'];
        expect(Artisan::call('fiscal:rtc:assess-split-payment', $args))->toBe(0);
        $first = Artisan::output();
        $report = json_decode($first, true, 512, JSON_THROW_ON_ERROR);
        expect($report['replay_matches'])->toBeTrue()->and($report['report']['segregation']['taxes']['IBS']['segregated'])->toBe('2.66');
        Artisan::call('fiscal:rtc:assess-split-payment', $args);
        expect(Artisan::output())->toBe($first);
        $input['fiscal_revisions'][1]['reset_evidence_id'] = '';
        file_put_contents($path, json_encode(seal158($input), JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', $args))->toBe(1);
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($report['report']['reason'])->toBe('A160_FISCAL_RESET_PROOF_REQUIRED');
    } finally {
        unlink($path);
    }
});
