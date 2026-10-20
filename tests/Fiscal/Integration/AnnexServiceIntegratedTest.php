<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsXmlProjector;

it('preserves the evidenced NBS in the complete local DPS for reduced education and health', function (string $name, string $nbs): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'),
        bundleManifestPath: 'bundles/consumption-phase4b-2026.10.01-6.json');
    $result = $runner->run(resource_path('fiscal/scenarios/consumption-integrated-2026.10.01-6/integrated-'.$name.'.json'))->toArray();
    $decision = $result['decision'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')->and($decision['iss']['calculation']['tax_amount'])->toBe('50.00')
        ->and($decision['iss']['municipal_parameters']['source_type'])->toBe('FIXTURE')
        ->and($decision['federal_withholdings']['status'])->toBe('NOT_REQUESTED')
        ->and($decision['dps_projection']['service']['cNBS'])->toBe($nbs);
    $identity = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json')), true, 512, JSON_THROW_ON_ERROR);
    $projected = (new DpsXmlProjector)->project($decision, $identity);
    expect($projected['status'])->toBe('PROJECTED')->and($projected['xml'])->toContain('<cNBS>'.$nbs.'</cNBS>', '<cIndOp>030101</cIndOp>', '<CPF>12345678901</CPF>');
    $validation = (new DpsXmlSchemaValidator)->validate($projected['xml'],
        resource_path('fiscal/sources/official/nfse-esquemas_xsd-v1-01-20260209.zip'),
        'e7935cbd9470527c6cc32984c1b2263e614183bf0139ce2733eaaed2de9a8072', 'Schemas/1.01/DPS_v1.01.xsd');
    expect($validation['errors'])->toHaveCount(1)->and($validation['errors'][0]['message'])->toContain('serie');
    $identity['service_municipality'] = '3509502';
    $conflict = (new DpsXmlProjector)->project($decision, $identity);
    expect($conflict['reason'])->toBe('RTC_DPS_PERFORMANCE_MUNICIPALITY_MISMATCH');
})->with([['education-presential-2026', '122012000'], ['health-presential-2026', '123012100']]);

it('retains medical glosa in the integrated calculation and blocks XML that would discard it', function (): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'),
        bundleManifestPath: 'bundles/consumption-phase4b-2026.10.01-6.json');
    $decision = $runner->run(resource_path('fiscal/scenarios/consumption-integrated-2026.10.01-6/integrated-health-unpaid-glosa-2026.json'))->toArray()['decision'];
    expect($decision['iss']['calculation']['taxable_base'])->toBe('1000.00')
        ->and($decision['consumption_reform']['decision']['base']['taxable'])->toBe('900.00');
    $identity = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json')), true, 512, JSON_THROW_ON_ERROR);
    $report = (new DpsXmlProjector)->project($decision, $identity);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['xml'])->toBeNull()
        ->and($report['reason'])->toBe('RTC_DPS_ADJUSTMENT_OR_SPECIAL_GROUP_REQUIRED');
});
