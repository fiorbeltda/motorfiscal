<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

it('converts the partial export before allocation and preserves the document obligation gate', function (string $name): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.14-73-draft1.json');
    $manifest = json_decode((string) file_get_contents(storage_path('app/phase4b-partial-export-fx73-preparation-20261014/export-integrated-case-manifest.json')), true, 512, JSON_THROW_ON_ERROR);
    $case = current(array_filter($manifest['cases'], static fn (array $row): bool => $row['name'] === $name));
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(base_path($case['path']));
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray();
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain($case['expected_reason']);
    $trace = array_column($result['trace'], null, 'stage');
    if ($case['nominal_path']) {
        foreach ($case['independent_decimal'] as $key => $value) {
            $row = $key === 'total_gross_brl' ? $trace['PARTIAL_EXPORT_GROSS_ALLOCATION'] : $trace['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'];
            expect($row[$key])->toBe($value);
        }
        if ($case['currency'] !== 'BRL') {
            expect($trace['PARTIAL_EXPORT_FX_TRANSACTION']['operation_document_binding_locally_verified'])->toBeTrue()
                ->and($trace['PARTIAL_EXPORT_FX_TRANSACTION']['production_ready'])->toBeFalse();
            $stages = array_column($result['trace'], 'stage');
            expect(array_search('PARTIAL_EXPORT_FX_TRANSACTION', $stages, true))->toBeLessThan(array_search('PARTIAL_EXPORT_GROSS_ALLOCATION', $stages, true));
        }
    } else {
        expect(isset($trace['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']))->toBeFalse();
    }
})->with(['usd-direct', 'usd-weekend', 'usd-quarter', 'usd-mostly-foreign', 'usd-mostly-domestic', 'brl-control',
    'proof-hash', 'proof-missing', 'wrong-document-proof', 'eur-unqualified', 'wrong-operation', 'unknown-source']);
