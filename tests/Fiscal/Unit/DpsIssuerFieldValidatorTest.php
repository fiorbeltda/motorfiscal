<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\DpsIssuerFieldValidator;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string, mixed> */
function nt004Codes(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.09.28-10.json'),
        true, 512, JSON_THROW_ON_ERROR);
}

it('validates projected issuer paths and scalar constraints against the NT004 XSD', function (): void {
    $codes = nt004Codes();
    $layout = $codes['document_layouts'][0];
    $validator = new DpsIssuerFieldValidator;
    $fields = [
        'IBSCBS.finNFSe' => '0',
        'IBSCBS.indDest' => '1',
        'IBSCBS.cIndOp' => '100301',
        'IBSCBS.valores.trib.gIBSCBS.CST' => '000',
        'IBSCBS.valores.trib.gIBSCBS.cClassTrib' => '000001',
    ];

    expect($validator->validate($fields, $layout, $codes)['status'])->toBe('VALID')
        ->and($validator->validate(['IBSCBS.unknown' => '1'], $layout, $codes)['reason'])
        ->toBe('RTC_DPS_ISSUER_FIELD_NOT_IN_XSD')
        ->and($validator->validate(['IBSCBS.finNFSe' => '9'], $layout, $codes)['reason'])
        ->toBe('RTC_DPS_ISSUER_FIELD_ENUM_INVALID')
        ->and($validator->validate(['IBSCBS.cIndOp' => '12345'], $layout, $codes)['reason'])
        ->toBe('RTC_DPS_ISSUER_FIELD_PATTERN_INVALID');

    $wrongLayout = $layout;
    $wrongLayout['xsd_source_hash'] = str_repeat('0', 64);
    expect($validator->validate($fields, $wrongLayout, $codes)['reason'])
        ->toBe('RTC_DPS_XSD_VERSION_NOT_PROVEN');
});

/** @return array<string, mixed> */
function nt004Evaluate(string $scenarioPath): array
{
    $factory = new FiscalEngineFactory;
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $knowledge = $factory->captureKnowledge($resourceRoot, 'bundles/consumption-phase4b-2026.09.28-10.json');
    $source = json_decode((string) file_get_contents($resourceRoot.'/scenarios/'.$scenarioPath),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'nt004-xsd-fields-2026';
    $source['knowledge_at'] = '2026-09-28T14:31:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('records a source-bound XSD field check in the 2026 RTC decision', function (): void {
    $result = nt004Evaluate('consumption-phase4b/regular-saas-2026.json');
    $codes = nt004Codes();

    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['document']['issuer_field_validation']['status'])->toBe('VALID')
        ->and($result['decision']['document']['issuer_field_validation']['xsd_source_hash'])
        ->toBe($codes['dps_xsd']['source_sha256']);
});

it('checks importer supplied fields before leaving the import document obligation pending', function (): void {
    $result = nt004Evaluate('consumption-phase4b-2026.09.28-1/import-digital-2026-document-gap.json');
    $checks = array_values(array_filter($result['trace'],
        static fn (array $event): bool => ($event['stage'] ?? null) === 'DPS_ISSUER_XSD_VALIDATION'));

    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN')
        ->and($checks)->toHaveCount(1)
        ->and($checks[0]['status'])->toBe('VALID');
});
