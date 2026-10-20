<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsXmlProjector;

it('integrates professional reduction with ISS federal withholding and local XML', function (string $profession): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'),
        bundleManifestPath: 'bundles/consumption-phase4b-2026.10.01-4.json');
    $execution = $runner->execute(resource_path('fiscal/scenarios/consumption-integrated-2026.10.01-4/integrated-'.$profession.'-reduced-2026.json'));
    $result = $execution->result->toArray();
    $decision = $result['decision'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($decision['iss']['calculation']['tax_amount'])->toBe('50.00')
        ->and($decision['iss']['municipal_parameters']['source_type'])->toBe('FIXTURE')
        ->and($decision['federal_withholdings']['total_withholding'])->toBe('61.50')
        ->and($decision['consumption_reform']['decision']['ibs']['nominal_amount'])->toBe('0.70')
        ->and($decision['consumption_reform']['decision']['cbs']['nominal_amount'])->toBe('6.30');
    $identity = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/dps-local-2026.10.01-4/federal-identity.json')), true, 512, JSON_THROW_ON_ERROR);
    $xml = (new DpsXmlProjector)->project($decision, $identity);
    expect($xml['status'])->toBe('PROJECTED')->and($xml['xml'])->toContain('<CST>200</CST>', '<cClassTrib>200052</cClassTrib>', '<vRetIRRF>15.00</vRetIRRF>')
        ->and($xml['xml'])->not->toContain('pRedAliq', 'pAliqEfet');
    $schema = (new DpsXmlSchemaValidator)->validate($xml['xml'],
        resource_path('fiscal/sources/official/nfse-esquemas_xsd-v1-01-20260209.zip'),
        'e7935cbd9470527c6cc32984c1b2263e614183bf0139ce2733eaaed2de9a8072', 'Schemas/1.01/DPS_v1.01.xsd');
    expect($schema['errors'])->toHaveCount(1)->and($schema['errors'][0]['message'])->toContain('serie');
})->with(['lawyer', 'accountant']);
