<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

dataset('sp98-cases-runners', function (): array {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/sp98-scenario-expectations.json'), true, 512, JSON_THROW_ON_ERROR);
    $data = [];
    foreach ($manifest['cases'] as $case) {
        foreach (['rtc', 'integrated'] as $runner) {
            $data[$case['name'].'-'.$runner] = [$case, $runner];
        }
    }

    return $data;
});

it('binds current SP municipal proofs after native RTC and national ISS stages', function (array $case, string $runner): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.16-98-draft1.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(base_path($case['path']));
    $evaluate = fn () => $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
    $result = $evaluate();
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain($case['reason'])
        ->and($evaluate())->toBe($result)->and($result['decision'])->toBe([]);
    $rtc = $result;
    if ($runner === 'integrated') {
        $trace = array_column($result['trace'], 'payload', 'type');
        $rtc = $trace['CONSUMPTION_REFORM'];
        $tax = $case['name'] === 'brl-control' ? '20.00' : ($case['name'] === 'usd-weekend' ? '103.98' : '103.59');
        expect($trace['ISS_CALCULATION']['tax_amount'])->toBe($tax);
    }
    $stages = array_column($rtc['trace'], null, 'stage');
    $local = $stages['PARTIAL_EXPORT_SP_RESULTS'] ?? null;
    expect($local !== null && $local['status'] === 'LOCAL_CURRENT_SP_EXPORT_RESULTS_BOUND')->toBe($case['expected_bound']);
    if ($case['expected_bound']) {
        expect($local['supplier_municipality'])->toBe('3550308')->and($local['archive_snapshot_replayed'])->toBeFalse()
            ->and(array_column($local['documents'], 'role'))->toBe(['RTC_FOREIGN', 'RTC_DOMESTIC']);
        foreach ($local['documents'] as $row) {
            $value = $row['municipal_result'];
            $candidate = $value['result_subject_scope'] === 'ABROAD' && $value['br_person_link'] === 'NO_BR_LINK';
            expect($value['export_non_incidence_candidate'])->toBe($candidate)->and($value['candidate_tribISSQN'])->toBe($candidate ? '3' : null);
        }
        foreach (['economic_operation_identity_authenticated', 'fiscal_document_identifiers_qualified', 'iss_supply_date_materially_qualified',
            'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated', 'full_operation_iss_rewritten',
            'source_effective_date_proven', 'legal_applicability_approved', 'xml_generated', 'document_obligation_complete', 'posting_authorized', 'production_ready'] as $flag) {
            expect($local[$flag])->toBeFalse();
        }
        $order = array_column($rtc['trace'], 'stage');
        expect(array_search('PARTIAL_EXPORT_ISS_RESULTS', $order, true))->toBeLessThan(array_search('PARTIAL_EXPORT_SP_RESULTS', $order, true));
    }
    if (isset($stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'])) {
        foreach ($case['independent_decimal'] as $key => $value) {
            $row = $key === 'total_gross_brl' ? $stages['PARTIAL_EXPORT_GROSS_ALLOCATION'] : $stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'];
            expect($row[$key])->toBe($value);
        }
    }
})->with('sp98-cases-runners');
