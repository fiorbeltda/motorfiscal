<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

dataset('identity104-runners', function (): array {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/identity104-scenario-expectations-v2.json'), true, 512, JSON_THROW_ON_ERROR);
    $data = [];
    foreach ($manifest['cases'] as $case) {
        foreach (['rtc', 'integrated'] as $runner) {
            $data[$case['name'].'-'.$runner] = [$case, $runner];
        }
    }

    return $data;
});

it('binds identity104 after current RTC and both ISS gates without granting the document obligation', function (array $case, string $runner): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.19-104-draft1.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(base_path($case['path']));
    $result = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
    expect($result['status'])->toBe($case['status'])->and($result['reason_codes'])->toContain($case['reason']);
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $stages = array_column($rtc['trace'], null, 'stage');
    if ($case['identity_status'] === null) {
        expect($stages)->not->toHaveKey('PARTIAL_EXPORT_DPS_IDENTITY')->and($rtc['decision'])->toBe([]);

        return;
    }
    expect($stages['PARTIAL_EXPORT_ISS_RESULTS']['status'])->toBe('LOCAL_CURRENT_EXPORT_ISS_RESULTS_BOUND')
        ->and($stages['PARTIAL_EXPORT_SP_RESULTS']['status'])->toBe('LOCAL_CURRENT_SP_EXPORT_RESULTS_BOUND')
        ->and($stages['PARTIAL_EXPORT_DPS_IDENTITY']['status'])->toBe($case['identity_status'])
        ->and($stages['PARTIAL_EXPORT_DPS_IDENTITY']['reason'])->toBe($case['identity_reason'])
        ->and($rtc['decision'])->toBe([]);
    $order = array_column($rtc['trace'], 'stage');
    expect(array_search('PARTIAL_EXPORT_DPS_IDENTITY', $order, true))->toBeGreaterThan(array_search('PARTIAL_EXPORT_SP_RESULTS', $order, true));
    foreach (['xml_generated', 'posting_authorized', 'production_ready', 'document_obligation_complete',
        'government_evidence_authenticated', 'fiscal_document_identifiers_qualified', 'iss_supply_date_materially_qualified',
        'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated'] as $flag) {
        expect($stages['PARTIAL_EXPORT_DPS_IDENTITY'][$flag])->toBeFalse();
    }
    if ($case['identity_status'] === 'LOCAL_DPS_IDENTITY_PAIR_BOUND') {
        expect(array_column($stages['PARTIAL_EXPORT_DPS_IDENTITY']['documents'], 'dps_id'))->toBe($case['expected_ids']);
        $docs = $stages['PARTIAL_EXPORT_DPS_IDENTITY']['documents'];
        expect($docs[0]['document_reference'])->toBe($scenario->source['facts']['rtc.export_foreign_document_id'])
            ->and($docs[1]['document_reference'])->toBe($scenario->source['facts']['rtc.export_domestic_document_id']);
    }
    if (isset($case['original_path'])) {
        $legacy = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.16-101-draft1.json');
        $controlSource = $scenario->source;
        unset($controlSource['facts']['rtc.assess_export_dps_identity'], $controlSource['facts']['rtc.export_dps_identity_records']);
        $controlSource['rule_bundle_id'] = $legacy['rule_bundle_id'];
        $controlSource['catalog_versions'] = $legacy['catalog_versions'];
        $control = $factory->scenarioLoaderFromKnowledge($legacy)->fromArray($controlSource);
        $before = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($legacy, $control)->toArray()
            : $factory->makeFromKnowledge($legacy)->evaluate($control->input)->toArray();
        $beforeRtc = $runner === 'rtc' ? $before : array_column($before['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
        $beforeStages = array_column($beforeRtc['trace'], null, 'stage');
        expect($stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'])->toBe($beforeStages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']);
        $original = json_decode((string) file_get_contents(base_path($case['original_path'])), true, 512, JSON_THROW_ON_ERROR);
        expect($controlSource['facts'])->toBe($original['facts']);
    }
})->with('identity104-runners');
