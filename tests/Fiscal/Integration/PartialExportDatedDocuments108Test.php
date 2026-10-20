<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

dataset('dated108-engine-runners', function (): array {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/dated108-scenario-expectations.json'), true, 512, JSON_THROW_ON_ERROR);
    $rows = [];
    foreach ($manifest['cases'] as $case) {
        foreach (['rtc', 'integrated'] as $runner) {
            $rows[$case['name'].'-'.$runner] = [$case, $runner];
        }
    }

    return $rows;
});

it('composes dated108 after original RTC nominal gates without changing operation date or authorizing documents', function (array $case, string $runner): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.19-108-draft3.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(base_path($case['path']));
    $result = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
    expect($result['status'])->toBe($case['status'])->and($result['reason_codes'])->toContain($case['reason']);
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $stages = array_column($rtc['trace'], null, 'stage');
    expect($stages)->not->toHaveKey('PARTIAL_EXPORT_ISS_RESULTS')->not->toHaveKey('PARTIAL_EXPORT_SP_RESULTS')
        ->not->toHaveKey('PARTIAL_EXPORT_DPS_IDENTITY')->and($rtc['decision'])->toBe([]);
    if ($case['dated_status'] === null) {
        expect($stages)->not->toHaveKey('PARTIAL_EXPORT_DATED_DOCUMENTS');

        return;
    }
    $dated = $stages['PARTIAL_EXPORT_DATED_DOCUMENTS'];
    expect($dated['status'])->toBe($case['dated_status'])->and($dated['reason'])->toBe($case['dated_reason'])
        ->and($dated['identity_carrier_excluded_from_qualification_facts'])->toBeTrue()
        ->and($dated['regime_proof_normalized_from_singleton_transport'])->toBeTrue()
        ->and($dated['provided_context_facts_hash'])->toBe(CanonicalJson::hash($scenario->input->context->facts->canonicalValues()));
    $order = array_column($rtc['trace'], 'stage');
    expect(array_search('PARTIAL_EXPORT_DATED_DOCUMENTS', $order, true))
        ->toBeGreaterThan(array_search('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION', $order, true));
    foreach (['dps_competence_legally_approved', 'legal_applicability_approved', 'xml_generated', 'production_ready',
        'document_obligation_complete', 'posting_authorized', 'fiscal_document_identifiers_qualified',
        'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated'] as $flag) {
        expect($dated[$flag])->toBeFalse();
    }
    if ($case['dated_status'] === 'LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND') {
        expect(array_column($dated['documents'], 'iss_supply_on'))->toBe($case['expected_iss_dates'])
            ->and($dated['rtc_operation_date'])->toBe($case['rtc_operation_date']);
        foreach ($dated['documents'] as $doc) {
            expect($doc['identity']['competence_date'])->toBe($doc['iss_supply_on']);
        }
    }
    $legacy = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.19-104-draft1.json');
    $control = $scenario->source;
    $control['rule_bundle_id'] = $legacy['rule_bundle_id'];
    $control['catalog_versions'] = $legacy['catalog_versions'];
    foreach (['rtc.assess_export_dated_documents', 'rtc.export_iss_material_records', 'rtc.export_iss_dated_result_records',
        'rtc.export_dated_dps_identity_records', 'provider.iss_regime', 'provider.iss_regime_proof_records'] as $key) {
        unset($control['facts'][$key]);
    }
    $input = $factory->scenarioLoaderFromKnowledge($legacy)->fromArray($control);
    $before = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($legacy, $input)->toArray()
        : $factory->makeFromKnowledge($legacy)->evaluate($input->input)->toArray();
    $beforeRtc = $runner === 'rtc' ? $before : array_column($before['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $beforeStages = array_column($beforeRtc['trace'], null, 'stage');
    expect($stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'])->toBe($beforeStages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']);
})->with('dated108-engine-runners');
