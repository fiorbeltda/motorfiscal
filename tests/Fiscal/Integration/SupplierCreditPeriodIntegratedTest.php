<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;

it('integrates the supplier period assessment while preserving operation taxes and documentary fields', function (string $name, string $ibs): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'),
        bundleManifestPath: 'bundles/consumption-phase4b-2026.10.02-3.json');
    $result = $runner->run(resource_path('fiscal/scenarios/consumption-integrated-2026.10.02-3/integrated-supplier-credit-period-'.$name.'-2026.json'))->toArray();
    $decision = $result['decision'];
    $rtc = $decision['consumption_reform']['decision'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')->and($decision['iss']['calculation']['tax_amount'])->toBe('50.00')
        ->and($decision['iss']['municipal_parameters']['source_type'])->toBe('FIXTURE')
        ->and($decision['federal_withholdings']['status'])->toBe('NOT_REQUESTED')
        ->and($rtc['ibs']['nominal_amount'])->toBe('0.40')->and($rtc['cbs']['nominal_amount'])->toBe('3.60')
        ->and($rtc['supplier_credit_period']['taxes']['IBS']['annulled_credit'])->toBe($ibs)
        ->and($decision['dps_projection']['service']['cNBS'])->toBe('123012100');
})->with([['mixed', '30.00'], ['asymmetric', '40.00'], ['adjusted', '27.00']]);
