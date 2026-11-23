<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitIntegration162;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split162_engine_fixture.php';

it('activates the qualified simplified calculation without demanding the standard ledger', function (bool $integrated): void {
    [$scenario, $knowledge] = split162EngineScenario();
    $result = evaluate156($scenario, $knowledge, $integrated);
    if ($integrated) {
        $events = array_values(array_filter($result['trace'], static fn (array $event): bool => $event['type'] === 'CONSUMPTION_REFORM'));
        $result = $events[0]['payload'];
    }
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($result['decision'])->toBe([])->and($candidate['candidate_status'])->toBe('VALID');
    $collection = $candidate['candidate_decision']['collection'];
    expect($collection['procedure'])->toBe('SIMPLIFIED')->and($collection)->not->toHaveKey('split_ledger158')
        ->and($collection['simplified_split162']['calculation']['taxes']['IBS']['segregated'])->toBe('8.33')
        ->and($collection['simplified_split162']['appropriable_credit_from_segregation'])->toBeFalse()
        ->and($candidate['candidate_decision']['ibs']['cash_due'])->toBe('1.00');
})->with([false, true]);

it('binds a period book to the supplier, current document and calculated nominal debit', function (): void {
    [$scenario, $knowledge] = split162EngineScenario(true, true);
    $result = evaluate156($scenario, $knowledge);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_status'])->toBe('VALID')
        ->and($candidate['candidate_decision']['collection']['simplified_split162']['period']['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED');
    $book = $scenario['facts'][SimplifiedSplitIntegration162::FACT][0]['period'];
    $book['debts'][3]['debits']['IBS'] = '2.00';
    $scenario['facts'][SimplifiedSplitIntegration162::FACT][0]['period'] = seal158($book);
    $result = evaluate156($scenario, $knowledge);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_reasons'])->toContain('RTC_SIMPLIFIED_PERIOD_CURRENT_DEBIT_CONFLICT');
});

it('never treats standard phase-one deployment evidence as simplified deployment proof', function (): void {
    [$scenario, $knowledge] = split162EngineScenario(false);
    $knowledge['documents']['rtc_transition_catalog']['split_deployments'] = [splitB34SyntheticDeployment()];
    $result = evaluate156($scenario, $knowledge);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_reasons'])->toContain('RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN');
});

it('loads explicit facts and policy while leaving official percentages, mandates and deployments empty', function (): void {
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.23-a162-draft1.json');
    $codes = $knowledge['documents']['rtc_code_catalog'];
    expect($codes[SimplifiedSplitIntegration162::KEY])->toBe(SimplifiedSplitIntegration162::POLICY)
        ->and($codes[SimplifiedSplitIntegration162::PARAMETERS])->toBe([])
        ->and($codes[SimplifiedSplitIntegration162::MANDATES])->toBe([])
        ->and($codes[SimplifiedSplitIntegration162::DEPLOYMENTS])->toBe([])
        ->and(array_column($knowledge['documents']['fact_definitions'], 'key'))->toContain('rtc.split_procedure', SimplifiedSplitIntegration162::FACT);
});

it('refuses a malformed simplified policy instead of treating it as a standard request', function (): void {
    [$scenario, $knowledge] = split162EngineScenario();
    $knowledge['documents']['rtc_code_catalog'][SimplifiedSplitIntegration162::KEY]['posting'] = true;
    expect(evaluate156($scenario, $knowledge)['reason_codes'])->toContain('RTC_SIMPLIFIED_SPLIT_POLICY_INVALID_OR_NOT_ENABLED');
});

it('refuses conflicting parties, payment identity and operation facts in the supplied calculation', function (string $case): void {
    [$scenario, $knowledge] = split162EngineScenario();
    $calculation = $scenario['facts'][SimplifiedSplitIntegration162::FACT][0]['calculation'];
    match ($case) {
        'document' => $calculation['document_id'] = 'OTHER',
        'supplier' => $calculation['supplier_id'] = 'OTHER',
        'payer' => $calculation['acquirer_id'] = 'OTHER',
        'payment' => $calculation['payment_transaction_id'] = 'OTHER',
        'gross' => $calculation['operation_value'] = '999.99',
        'known' => $calculation['known_at'] = '2026-11-22T20:00:00Z',
        'sector' => $calculation['sector'] = 'OTHER',
        'tax information' => $calculation['nominal_values_identified'] = true,
    };
    $scenario['facts'][SimplifiedSplitIntegration162::FACT][0]['calculation'] = seal158($calculation);
    $result = evaluate156($scenario, $knowledge);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_status'])->toBe('ACTION_REQUIRED')->and($candidate['candidate_decision'])->toBe([]);
})->with(['document', 'supplier', 'payer', 'payment', 'gross', 'known', 'sector', 'tax information']);

it('runs both simplified modes through the installed native command and refuses tampering', function (string $mode): void {
    $path = tempnam(sys_get_temp_dir(), 'split162-');
    try {
        $input = $mode === 'calculation' ? split162Calculation() : split162Period();
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        $args = ['input' => $path, '--mode' => $mode];
        expect(Artisan::call('fiscal:rtc:assess-simplified-split', $args))->toBe(0);
        $first = Artisan::output();
        expect(json_decode($first, true, 512, JSON_THROW_ON_ERROR)['replay_matches'])->toBeTrue();
        Artisan::call('fiscal:rtc:assess-simplified-split', $args);
        expect(Artisan::output())->toBe($first);
        $input['source_hash'] = str_repeat('0', 64);
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-simplified-split', $args))->toBe(1);
    } finally {
        unlink($path);
    }
})->with(['calculation', 'period']);
