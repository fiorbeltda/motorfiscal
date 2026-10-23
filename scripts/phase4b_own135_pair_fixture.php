<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentIssIntegration134;
use App\Modules\Fiscal\ConsumptionReform\DocumentOwnTaxBridge135;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;

require_once __DIR__.'/phase4b_iss133_fixture.php';
require_once __DIR__.'/phase4b_own135_fixture.php';

/** New explicit synthetic contract quotes; no price is assigned from an RTC percentage by the engine. */
function own135PairFixture(string $domesticPrice = '641.33', string $foreignPrice = '400.00', ?string $knowledgeAt = null, ?array $identityOverrides = null): array
{
    $known = $knowledgeAt ?? (new DateTimeImmutable(DocumentOwnTaxBridge135::AVAILABLE_AT))->format('Y-m-d\TH:i:s.uP');
    $component = phase4bIss133Fixture(knowledgeAt: $known,
        declaredPrices: ['RTC_FOREIGN' => $foreignPrice, 'RTC_DOMESTIC' => $domesticPrice], identityOverrides: $identityOverrides);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-'.DocumentIssIntegration134::VERSION.'.json');
    $scenario = $component['scenario'];
    $scenario['scenario_id'] = 'OWN135-NEW-CONTRACT-'.$foreignPrice.'-'.$domesticPrice;
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['facts'][DocumentIssIntegration134::PROOFS] = $component['proofs'];
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $result = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);
    $snapshot = (new ScenarioExecution($loaded, $result, $knowledge))->snapshot();
    if ($snapshot['result']['reason_codes'] !== ['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN']) {
        throw new RuntimeException('New135 contract did not reach current134 documentary stage.');
    }
    $rtc = array_column($snapshot['result']['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $stages = array_column($rtc['trace'], null, 'stage');
    $iss = $stages['PARTIAL_EXPORT_DOCUMENT_ISS134'];
    $ownProofs = own135Proofs($iss);
    foreach ($ownProofs as &$proof) {
        $proof['observed_at'] = $known;
        $proof = own135Capture($proof);
    }
    unset($proof);

    return ['snapshot' => $snapshot, 'facts' => $loaded->input->context->facts, 'iss' => $iss,
        'allocation' => $stages['PARTIAL_EXPORT_GROSS_ALLOCATION'], 'own_proofs' => $ownProofs, 'known_at' => $known];
}
