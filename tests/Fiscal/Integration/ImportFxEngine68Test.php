<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

it('integrates source-bound FX before Brazilian allocation and retains document guard', function (string $name): void {
    $factory = new FiscalEngineFactory;
    $root = resource_path('fiscal');
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.14-68-draft1.json');
    $manifest = json_decode((string) file_get_contents(storage_path('app/phase4b-import-fx68-integration-20261013/scenario-manifest-v3.json')), true, 512, JSON_THROW_ON_ERROR);
    $case = current(array_filter($manifest['cases'], static fn (array $row): bool => $row['name'] === $name));
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(base_path($case['path']));
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray();
    expect($result['reason_codes'])->toContain($case['expected_reason']);
    $trace = array_column($result['trace'], null, 'stage');
    if ($case['positive_fx_path']) {
        expect($trace['IMPORT_FX_TRANSACTION']['converted_gross_brl'])->toBe($case['converted_gross_brl'])
            ->and($trace['IMPORT_FX_TRANSACTION']['production_ready'])->toBeFalse()
            ->and($trace['IMPORT_FX_TRANSACTION']['operation_document_binding_locally_verified'])->toBeTrue()
            ->and(isset($trace['IMPORT_NOMINAL_CALCULATION']))->toBeTrue()
            ->and(isset($trace['IMPORT_LOCAL_DPS_PROJECTION']))->toBeTrue();
    } else {
        expect(isset($trace['IMPORT_NOMINAL_CALCULATION']))->toBeFalse();
    }
})->with(['usd-direct', 'eur-direct', 'aud-direct', 'usd-weekend', 'usd-partial', 'brl-adjustment', 'missing-proof', 'proof-hash',
    'wrong-document', 'wrong-operation', 'foreign-adjustment', 'future-proof', 'unknown-source', 'non-usd-weekend']);
