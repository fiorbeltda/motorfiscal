<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

dataset('dated108-upstream-runners', function (): array {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/dated108-upstream-expectations.json'), true, 512, JSON_THROW_ON_ERROR);
    $rows = [];
    foreach ($manifest['cases'] as $case) {
        foreach (['rtc', 'integrated'] as $runner) {
            $rows[$case['name'].'-'.$runner] = [$case, $runner];
        }
    }

    return $rows;
});

it('preserves upstream currency allocation nominal and refusal gates through the dated108 route', function (array $case, string $runner): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.19-108-draft3.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(base_path($case['path']));
    $original = json_decode((string) file_get_contents(base_path($case['original_path'])), true, 512, JSON_THROW_ON_ERROR);
    $restored = $scenario->source['facts'];
    foreach (['rtc.assess_export_dated_documents', 'rtc.export_iss_material_records', 'rtc.export_iss_dated_result_records',
        'rtc.export_dated_dps_identity_records', 'provider.iss_regime', 'provider.iss_regime_proof_records'] as $key) {
        unset($restored[$key]);
    }
    foreach (['rtc.assess_export_iss_results', 'rtc.assess_export_sp_results', 'rtc.assess_export_dps_identity'] as $key) {
        if (array_key_exists($key, $original['facts'])) {
            $restored[$key] = $original['facts'][$key];
        } else {
            unset($restored[$key]);
        }
    }
    expect($restored)->toBe($original['facts']);
    $result = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
    expect($result['status'])->toBe($case['status'])->and($result['reason_codes'])->toContain($case['reason']);
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $stages = array_column($rtc['trace'], null, 'stage');
    expect($stages)->not->toHaveKey('PARTIAL_EXPORT_ISS_RESULTS')->not->toHaveKey('PARTIAL_EXPORT_SP_RESULTS')
        ->not->toHaveKey('PARTIAL_EXPORT_DPS_IDENTITY')->and($rtc['decision'])->toBe([]);
    if ($case['dated_status'] === null) {
        expect($stages)->not->toHaveKey('PARTIAL_EXPORT_DATED_DOCUMENTS');
    } else {
        $dated = $stages['PARTIAL_EXPORT_DATED_DOCUMENTS'];
        expect($dated['status'])->toBe($case['dated_status'])->and($dated['reason'])->toBe($case['dated_reason']);
        if ($case['dated_subreason'] !== null) {
            expect($dated['dated_reason'])->toBe($case['dated_subreason']);
        }
        if ($case['dated_status'] === 'LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND') {
            foreach ($dated['documents'] as $doc) {
                expect($doc['identity']['competence_date'])->toBe($doc['iss_supply_on']);
            }
        }
        foreach (['xml_generated', 'production_ready', 'posting_authorized', 'document_obligation_complete',
            'dps_competence_legally_approved', 'iss_supply_date_materially_qualified',
            'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated'] as $flag) {
            expect($dated[$flag])->toBeFalse();
        }
    }
    $controlKnowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.19-104-draft1.json');
    $control = $original;
    $control['rule_bundle_id'] = $controlKnowledge['rule_bundle_id'];
    $control['catalog_versions'] = $controlKnowledge['catalog_versions'];
    $control['knowledge_at'] = $scenario->source['knowledge_at'];
    foreach (['rtc.assess_export_iss_results', 'rtc.assess_export_sp_results', 'rtc.assess_export_dps_identity'] as $key) {
        $control['facts'][$key] = false;
    }
    $beforeInput = $factory->scenarioLoaderFromKnowledge($controlKnowledge)->fromArray($control);
    $before = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($controlKnowledge, $beforeInput)->toArray()
        : $factory->makeFromKnowledge($controlKnowledge)->evaluate($beforeInput->input)->toArray();
    $beforeRtc = $runner === 'rtc' ? $before : array_column($before['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $beforeStages = array_column($beforeRtc['trace'], null, 'stage');
    if (isset($stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'])) {
        expect($stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'])->toBe($beforeStages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']);
    }
    if ($case['fx_refusal_before_nominal']) {
        expect($stages)->not->toHaveKey('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION')
            ->and($result['reason_codes'])->toBe($before['reason_codes']);
    }
})->with('dated108-upstream-runners');
