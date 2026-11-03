<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CollectionDecisionEngine;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

require_once dirname(__DIR__, 3).'/scripts/agent_a/collection148_fixture.php';

it('asks the exact correction fact instead of qualifying a blank or ambiguous collection reference', function (string $key, string $value): void {
    $result = collection148Run([$key => $value]);
    foreach (['rtc', 'full'] as $runner) {
        expect($result[$runner]['status'])->toBe('ACTION_REQUIRED')
            ->and($result[$runner]['missing_facts'])->toContain($key);
    }
})->with((function (): array {
    $rows = [];
    foreach (['rtc.payment_instrument_evidence_id', 'rtc.payment_transaction_id', 'rtc.supplier_id', 'rtc.acquirer_id'] as $key) {
        foreach ([' ', "\t", "\u{00A0}", ' padded ', "reference\0suffix", "reference\nsuffix"] as $value) {
            $rows[] = [$key, $value];
        }
    }

    return $rows;
})());

it('preserves valid textual ids and the distinct eligibility and extinction states', function (): void {
    $result = collection148Run();
    expect($result['rtc']['status'])->toBe('VALID')->and($result['full']['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($result['rtc']['decision']['collection']['eligible_payer'])->toBe('22222222000191')
        ->and($result['rtc']['decision']['collection']['collector'])->toBe('11111111000191')
        ->and($result['rtc']['decision']['collection']['extinction_status'])->toBe('UNCONFIRMED');
    $trace = array_column($result['rtc']['trace'], null, 'stage');
    expect($trace['COLLECTION_ELIGIBILITY']['extinction_status'])->toBe('UNCONFIRMED')
        ->and((new RtcScenarioRunService(new FiscalEngineFactory))->replay($result['snapshot']))->toBeTrue();
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($result['snapshot']['knowledge'])->fromArray($result['snapshot']['input']);
    $direct = (new CollectionDecisionEngine)->resolve($loaded->input->context->facts, 'DOMESTIC', false);
    expect($direct['payment_transaction_id'])->toBe('0001');
});

it('preserves identifier spelling including zero and valid non-ASCII references', function (string $id): void {
    $result = collection148Run(['rtc.payment_transaction_id' => $id, 'rtc.payment_instrument_evidence_id' => $id]);
    expect($result['rtc']['status'])->toBe('VALID');
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($result['snapshot']['knowledge'])->fromArray($result['snapshot']['input']);
    $direct = (new CollectionDecisionEngine)->resolve($loaded->input->context->facts, 'DOMESTIC', false);
    expect($direct['payment_transaction_id'])->toBe($id)->and($direct['instrument_evidence_id'])->toBe($id);
})->with(['0', '01', '0001', 'referência-ação-1']);

it('requires a usable deployment reference even with an explicitly synthetic active-deployment parameter', function (string $value): void {
    $source = collection148Scenario(['rtc.collection_method' => 'SPLIT_PAYMENT',
        'rtc.collection_legal_basis' => 'LC214 arts. 31-35', 'rtc.payment_instrument_split_capable' => true,
        'rtc.split_deployment_evidence_id' => $value]);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.09.28-3.json');
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $direct = (new CollectionDecisionEngine)->resolve($loaded->input->context->facts, 'DOMESTIC', true);
    expect($direct['status'])->toBe('ACTION_REQUIRED')
        ->and($direct['missing_facts'])->toContain('rtc.split_deployment_evidence_id');
})->with([' ', ' padded ', "deployment\0suffix"]);

it('keeps split deployment absent and instrument contradictions separate from payment evidence', function (): void {
    $split = collection148Run(['rtc.collection_method' => 'SPLIT_PAYMENT', 'rtc.collection_legal_basis' => 'LC214 arts. 31-35',
        'rtc.payment_instrument_split_capable' => true, 'rtc.split_deployment_evidence_id' => 'FIXTURE.CLAIM']);
    expect($split['rtc']['reason_codes'])->toContain('RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN');
    $conflict = collection148Run(['rtc.payment_instrument_split_capable' => true]);
    expect($conflict['rtc']['status'])->toBe('INVALID')
        ->and($conflict['rtc']['reason_codes'])->toContain('RTC_ACQUIRER_COLLECTION_CONDITIONS_CONFLICT');
});
