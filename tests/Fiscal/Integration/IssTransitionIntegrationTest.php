<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Engine\DecisionStatus;
use App\Modules\Fiscal\Infrastructure\MunicipalParameters\JsonMunicipalParameterProvider;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array{0: FiscalEngineFactory, 1: array<string, mixed>, 2: array<string, mixed>} */
function issTransitionFixture(): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.09.28-5.json');
    $scenario = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.09.28-1/regular-saas-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'iss-transition-integrated-2029';
    $scenario['competence_date'] = '2029-06-15';
    $scenario['knowledge_at'] = '2029-06-16T10:00:00-03:00';
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['facts']['rtc.evaluate'] = false;
    $scenario['facts']['rtc.operation_date'] = '2029-06-15';

    return [$factory, $knowledge, $scenario];
}

it('requires an official municipal reference rate in the integrated 2029 decision', function (): void {
    [$factory, $knowledge, $source] = issTransitionFixture();
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($result->reasonCodes)->toContain('ISS_2028_FROZEN_RATE_SOURCE_NOT_APPROVED');
});

it('uses the official 2028 reference rather than the current municipal rate in the integrated 2029 decision', function (): void {
    [$factory, $knowledge, $source] = issTransitionFixture();
    $provider = JsonMunicipalParameterProvider::fromArray([
        'catalog_id' => 'TEST.MUNICIPAL.ACT.3550308',
        'version' => '2028-12-31',
        'source_type' => 'OFFICIAL_ACT',
        'parameters' => [
            ['municipality_ibge' => '3550308', 'ctribnac_code' => '010101', 'effective_from' => '2028-01-01',
                'effective_to' => '2028-12-31', 'rate' => '2.00', 'responsibility' => 'PROVIDER'],
            ['municipality_ibge' => '3550308', 'ctribnac_code' => '010101', 'effective_from' => '2029-01-01',
                'effective_to' => null, 'rate' => '4.00', 'responsibility' => 'PROVIDER'],
        ],
    ]);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = $factory->makeFromKnowledge($knowledge, $provider)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::VALID_WITH_WARNINGS)
        ->and($result->decision['iss']['rate']['rate'])->toBe('1.800000')
        ->and($result->decision['iss']['calculation']['tax_amount'])->toBe('18.00')
        ->and($result->decision['iss']['transition_rate']['reference_rate_percent'])->toBe('2.00')
        ->and($result->decision['iss']['transition_rate']['factor'])->toBe('0.90');
});

it('does not calculate ISS after its 2033 extinction in the integrated flow', function (): void {
    [$factory, $knowledge, $source] = issTransitionFixture();
    $source['scenario_id'] = 'iss-transition-integrated-2033';
    $source['competence_date'] = '2033-06-15';
    $source['knowledge_at'] = '2033-06-16T10:00:00-03:00';
    $source['facts']['rtc.operation_date'] = '2033-06-15';
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($result->reasonCodes)->toContain('ISS_EXTINCT_INTEGRATED_FLOW_NOT_MATERIALIZED')
        ->and($result->decision)->toBe([]);
});
