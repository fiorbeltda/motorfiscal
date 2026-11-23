<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\ConsumptionReform\SplitPaymentIntegrationB34;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split160_engine_fixture.php';
require_once dirname(__DIR__, 3).'/scripts/agent_a/split161_fixture.php';

const A161_BUNDLE = 'bundles/consumption-phase4b-2026.11.23-a161-draft1.json';

it('activates one installment with two boleto payments from the installed A161 bundle', function (bool $integrated): void {
    [$scenario] = split160EngineScenario();
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), A161_BUNDLE);
    $knowledge['documents']['rtc_transition_catalog']['split_deployments'] = [splitB34SyntheticDeployment()];
    $ledger = splitB34Ledger($scenario['facts'], $scenario['knowledge_at']);
    $ledger['contract'] = SplitLedger158::PAYMENTS_CONTRACT;
    $ledger['transactions'][0] = boleto161FromTransaction($ledger['transactions'][0], ['500.00', '500.00']);
    $ledger['reports'] = $ledger['remittances'] = $ledger['applications'] = [];
    $scenario['facts']['rtc.split_payment_ledger'] = [sealLedger158($ledger)];
    $result = evaluate156($scenario, $knowledge, $integrated);
    if ($integrated) {
        $events = array_values(array_filter($result['trace'], static fn (array $event): bool => $event['type'] === 'CONSUMPTION_REFORM'));
        $result = $events[0]['payload'];
    }
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_status'])->toBe('VALID')->and($result['decision'])->toBe([]);
    $report = $candidate['candidate_decision']['collection']['split_ledger158'];
    expect($report['financial_payment_count'])->toBe(2)
        ->and($report['transactions'])->toHaveCount(1)
        ->and($report['taxes']['IBS']['allocated'])->toBe('1.00')
        ->and($report['taxes']['IBS']['segregated'])->toBe('1.00')
        ->and($report['taxes']['CBS']['segregated'])->toBe('9.00')
        ->and($report['taxes']['IBS']['split_applied'])->toBe('0.00')
        ->and((new SplitLedger158)->replayPayments($report))->toBeTrue();
})->with([false, true]);

it('loads the exact payment policy without changing the official deployment catalog', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), A161_BUNDLE);
    expect($knowledge['documents']['rtc_code_catalog'][SplitPaymentIntegrationB34::KEY])->toBe(SplitPaymentIntegrationB34::PAYMENTS_POLICY)
        ->and($knowledge['documents']['rtc_transition_catalog']['split_deployments'])->toBe([]);
});

it('executes both native payment modes with deterministic replay', function (string $mode): void {
    $path = tempnam(sys_get_temp_dir(), 'split161-');
    try {
        $input = $mode === 'boleto' ? split161Boleto() : split161Ledger();
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        $args = ['input' => $path, '--mode' => $mode];
        expect(Artisan::call('fiscal:rtc:assess-split-payment', $args))->toBe(0);
        $first = Artisan::output();
        expect(json_decode($first, true, 512, JSON_THROW_ON_ERROR)['replay_matches'])->toBeTrue();
        Artisan::call('fiscal:rtc:assess-split-payment', $args);
        expect(Artisan::output())->toBe($first);
        $input['source_hash'] = str_repeat('0', 64);
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', $args))->toBe(1);
    } finally {
        unlink($path);
    }
})->with(['boleto', 'payment-ledger']);
