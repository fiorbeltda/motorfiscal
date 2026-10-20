<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

dataset('catalog101-cases-runners', function (): array {
    $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/catalog101-scenario-expectations-v2.json'), true, 512, JSON_THROW_ON_ERROR);
    $data = [];
    foreach ($fixture['cases'] as $case) {
        foreach (['rtc', 'integrated'] as $runner) {
            $data[$case['name'].'-'.$runner] = [$case, $runner];
        }
    }

    return $data;
});

it('uses current reference101 only at a classified code and preserves business facts and earlier gates', function (array $case, string $runner): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.16-101-draft1.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(base_path($case['path']));
    $result = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
    $legacy = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.16-98-draft1.json');
    $controlSource = $scenario->source;
    $controlSource['rule_bundle_id'] = $legacy['rule_bundle_id'];
    $controlSource['catalog_versions'] = $legacy['catalog_versions'];
    $control = $factory->scenarioLoaderFromKnowledge($legacy)->fromArray($controlSource);
    $before = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($legacy, $control)->toArray()
        : $factory->makeFromKnowledge($legacy)->evaluate($control->input)->toArray();
    expect($result['status'])->toBe($before['status'])->and($result['reason_codes'])->toBe($before['reason_codes']);
    expect($result['status'])->toBe($case['name'] === 'regular-saas-positive' && $runner === 'integrated' ? 'VALID_WITH_WARNINGS' : $case['status']);
    if ($case['reason'] !== null) {
        expect($result['reason_codes'])->toContain($case['reason']);
    }
    $original = json_decode((string) file_get_contents(base_path($case['original_path'])), true, 512, JSON_THROW_ON_ERROR);
    expect($scenario->source['facts'])->toBe($original['facts']);
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $references = array_values(array_filter($rtc['trace'], fn (array $row): bool => ($row['stage'] ?? null) === 'CCLASSTRIB_REFERENCE'));
    foreach ($references as $reference) {
        expect($reference['status'])->toBe('REFERENCE_ROW_AVAILABLE')->and($reference['document_model'])->toBe('NFSE')
            ->and($reference['decision_authority'])->toBeFalse()->and($reference['tax_treatment_selected'])->toBeFalse()
            ->and($reference['document_indicator_emitted'])->toBeFalse()->and($reference['production_ready'])->toBeFalse();
    }
    if ($case['name'] === 'regular-saas-positive') {
        expect($references)->toHaveCount(1)->and($rtc['decision']['ibs']['nominal_amount'])->toBe('1.00')
            ->and($rtc['decision']['cbs']['nominal_amount'])->toBe('9.00')
            ->and($rtc['decision']['classification']['source_kind'])->toBe('ENGINE_DECISION_RULE');
    }
    if (str_starts_with($case['name'], 'export-')) {
        $stages = array_column($rtc['trace'], null, 'stage');
        if (isset($stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'])) {
            expect($references)->toHaveCount(1)->and($references[0]['context'])->toBe('PARTIAL_EXPORT_DOMESTIC');
        }
    }
})->with('catalog101-cases-runners');
