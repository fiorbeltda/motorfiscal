<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentIssIntegration134;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;

require_once __DIR__.'/phase4b_iss133_fixture.php';

/** @return array<string, mixed> */
function phase4bIss134Fixture(string $seed = '000-no-adjustments-gross-before-all-discounts', bool $reverseResults = false): array
{
    $component = phase4bIss133Fixture($seed, reverseResults: $reverseResults, knowledgeAt: '2026-10-22T18:36:00.000000+00:00');
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-'.DocumentIssIntegration134::VERSION.'.json');
    $scenario = $component['scenario'];
    $scenario['scenario_id'] = 'ISS134-SYNTHETIC-'.$seed;
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['facts'][DocumentIssIntegration134::PROOFS] = $component['proofs'];
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $result = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);

    return ['knowledge' => $knowledge, 'scenario' => $scenario,
        'snapshot' => (new ScenarioExecution($loaded, $result, $knowledge))->snapshot(), 'component' => $component];
}

/** Independent documentary declarations, not inferred from the RTC acquirer country.
 * @return list<array<string, mixed>>
 */
function phase4bIss134Options(array $snapshot, bool $issuerRate = true): array
{
    $rtc = array_column($snapshot['result']['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $stage = array_column($rtc['trace'], null, 'stage')['PARTIAL_EXPORT_DOCUMENT_ISS134'];

    return array_map(static fn (array $document): array => ['role' => $document['role'],
        'document_reference' => $document['document_reference'], 'withholding' => '1',
        'rate_source' => $document['iss_base_brl'] === null ? 'NOT_APPLICABLE'
            : ($issuerRate ? 'ISSUER_SUPPLIED' : 'NATIONAL_SYSTEM_PARAMETERIZED'),
        'result_country' => $document['iss_base_brl'] === null ? 'US' : null, 'total_tax_indicator' => '0',
        'evidence_id' => 'SYNTHETIC-ISS134-DOCUMENTARY-OPTIONS-'.$document['role']], $stage['documents']);
}
