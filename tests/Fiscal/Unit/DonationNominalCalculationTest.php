<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string, mixed> */
function donationNominalResult(string $name): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-17.json');
    $source = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-17/'.$name.'.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('calculates separate 2026 nominal taxes from documented donation market value', function (): void {
    $result = donationNominalResult('donation-market-nominal-2026');
    $nominal = array_values(array_filter($result['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'DONATION_NOMINAL_CALCULATION'));

    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_DONATION_DOCUMENT_OBLIGATION_NOT_MATERIALIZED')
        ->and($nominal[0]['market_value_brl'])->toBe('1000.00')
        ->and($nominal[0]['ibs_state_nominal_brl'])->toBe('1.00')
        ->and($nominal[0]['ibs_municipal_nominal_brl'])->toBe('0.00')
        ->and($nominal[0]['cbs_nominal_brl'])->toBe('9.00')
        ->and($nominal[0]['destination_evidence_id'])->toBe('contract-establishment-1');
});

it('does not infer destination or future rates for the donation', function (): void {
    $destination = donationNominalResult('donation-market-destination-proof-missing-2026');
    expect($destination['missing_facts'])->toContain('rtc.destination_evidence_id');

    $unregistered = donationNominalResult('donation-market-unregistered-2026');
    expect($unregistered['reason_codes'])->toContain('RTC_DONATION_UNREGISTERED_DESTINATION_NOT_MATERIALIZED');

    $special = donationNominalResult('donation-market-special-treatment-2026');
    expect($special['reason_codes'])->toContain('RTC_DONATION_SPECIAL_STRATEGY_NOT_MATERIALIZED');

    $future = donationNominalResult('donation-market-rate-missing-2027');
    expect($future['status'])->toBe('ACTION_REQUIRED')
        ->and($future['reason_codes'])->toContain('RTC_RATE_NOT_PROVEN:CBS');
});
