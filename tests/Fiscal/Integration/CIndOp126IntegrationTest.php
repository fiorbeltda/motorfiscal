<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Production\Phase4bReadinessAssessor;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

function cindop126Input(bool $historical = false): array
{
    $factory = new FiscalEngineFactory;
    $version = $historical ? '2026.10.21-122-draft1' : '2026.10.22-126-draft1';
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-'.$version.'.json');
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.10.02-1/input-credit-standard-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'CINDOP126-LOCAL-SYNTHETIC';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['knowledge_at'] = '2026-10-22T11:00:00+00:00';
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    return [$factory, $knowledge, $scenario];
}

it('blocks production with an explicit cIndOp environment limitation', function (): void {
    $root = resource_path('fiscal');
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess($root,
        'bundles/consumption-phase4b-2026.10.22-126-draft1.json',
        $root.'/production/phase4b-controls-2026.10.22-126-draft1.json');
    expect($report['ready'])->toBeFalse()->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['official_sources_checked'])->toBe(105)
        ->and($report['official_source_hashes_valid'])->toBe(105)
        ->and($report['blockers'])->toContain('CINDOP126_REFERENCE_LOCAL_ONLY_NOT_OFFICIAL_ENVIRONMENT_ACTIVATION');
});

it('does not turn a new reference code into an engine classification rule', function (): void {
    [$factory, $knowledge, $scenario] = cindop126Input();
    $source = $scenario->source;
    $source['facts']['rtc.cindop'] = '020102';
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray();
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toContain('RTC_SUPPLIED_CLASSIFICATION_CONFLICT');
});

it('integrates the new reference in both runners and preserves the credit decision', function (string $runner): void {
    $evaluate = function (bool $historical) use ($runner): array {
        [$factory, $knowledge, $scenario] = cindop126Input($historical);
        $result = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray()
            : $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
        expect($result['status'])->toBe($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS');

        return $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    };
    $prior = $evaluate(true);
    $current = $evaluate(false);
    expect($prior['decision']['catalog_versions']['codes'])->toBe('2026.10.21-122-draft1')
        ->and($current['decision']['catalog_versions']['codes'])->toBe('2026.10.22-126-draft1');
    $prior['decision']['catalog_versions']['codes'] = '2026.10.22-126-draft1';
    expect($current['decision'])->toBe($prior['decision']);
    $stages = array_column($current['trace'], null, 'stage');
    expect($stages['CINDOP_REFERENCE126']['status'])->toBe('REFERENCE_ROW_AVAILABLE')
        ->and($stages['CINDOP_REFERENCE126']['edition'])->toBe('RTC_1_03_00')
        ->and($stages['CREDIT_INPUT_POLICY122']['status'])->toBe('RESOLVED')
        ->and(array_column($prior['trace'], 'stage'))->not->toContain('CINDOP_POLICY126');
})->with(['rtc', 'integrated']);

it('cannot remove new or inherited policies from the new catalog', function (string $field, string $reason): void {
    [, $knowledge, $scenario] = cindop126Input();
    $documents = $knowledge['documents'];
    $codes = $documents['rtc_code_catalog'];
    unset($codes[$field]);
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']), $codes,
        new TransitionCatalog($documents['rtc_transition_catalog']));
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain($reason);
})->with([
    ['cindop_reference126', 'RTC_CINDOP126_SOURCE_POLICY_NOT_BOUND'],
    ['credit_input_policy122', 'RTC_CREDIT122_SOURCE_POLICY_NOT_BOUND'],
    ['material_input_policy119', 'RTC_INPUT119_SOURCE_POLICY_NOT_BOUND'],
]);
