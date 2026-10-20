<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;

it('integrates the qualified acquisition credit without changing ISS or treating it as output debit', function (string $name, string $cbs): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'),
        bundleManifestPath: 'bundles/consumption-phase4b-2026.10.02-1.json');
    $result = $runner->run(resource_path('fiscal/scenarios/consumption-integrated-2026.10.02-1/integrated-input-credit-'.$name.'-2026.json'))->toArray();
    $decision = $result['decision'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')->and($decision['iss']['calculation']['tax_amount'])->toBe('50.00')
        ->and($decision['iss']['municipal_parameters']['source_type'])->toBe('FIXTURE')
        ->and($decision['federal_withholdings']['status'])->toBe('NOT_REQUESTED')
        ->and($decision['consumption_reform']['decision']['cbs']['nominal_amount'])->toBe('3.60')
        ->and($decision['consumption_reform']['decision']['cbs']['credit']['amount'])->toBe($cbs)
        ->and($decision['dps_projection']['service']['cNBS'])->toBe('123012100');
})->with([['asymmetric', '0.00'], ['suspension-principal-only', '18.00']]);
