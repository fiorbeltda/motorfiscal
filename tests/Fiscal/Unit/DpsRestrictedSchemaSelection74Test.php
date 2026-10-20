<?php

declare(strict_types=1);

use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection;

/** @return array<string, mixed> */
function restrictedSelection74Projection(): array
{
    return ['contract_version' => DpsRestrictedSchemaSelection::CONTRACT,
        'scope' => 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION',
        'source_sha256' => DpsRestrictedSchemaSelection::ARCHIVE_SHA256,
        'schema_selection' => ['scope' => 'LOCAL_STRUCTURAL_VALIDATION_ONLY', 'environment' => '2',
            'source_observed_at' => '2026-10-14T11:34:50.069465+00:00',
            'root_sha256' => DpsRestrictedSchemaSelection::ROOT_SHA256, 'remote_deployment_verified' => false]];
}

/** @return array<string, mixed> */
function restrictedSelection74Identity(): array
{
    return ['environment' => '2', 'issued_at' => '2026-10-14T09:30:00-03:00',
        'competence_date' => '2026-10-01', 'series' => '1'];
}

/** @return array<string, mixed> */
function restrictedSelection74Schema(): array
{
    return ['source_sha256' => DpsRestrictedSchemaSelection::ARCHIVE_SHA256, 'root_schema' => 'DPS_v1.01.xsd'];
}

it('binds the observed restricted artifact to explicit local identities without establishing deployment', function (string $series): void {
    $identity = [...restrictedSelection74Identity(), 'series' => $series];
    $result = (new DpsRestrictedSchemaSelection)->qualify($identity, restrictedSelection74Projection(),
        restrictedSelection74Schema(), '2026-10-14T12:30:00+00:00');
    expect($result['status'])->toBe('VALID')
        ->and($result['schema_sha256'])->toBe(DpsRestrictedSchemaSelection::ARCHIVE_SHA256)
        ->and($result['remote_deployment_verified'])->toBeFalse()
        ->and($result['document_obligation_qualified'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse();
})->with(['1', '00001', '89999']);

it('refuses production, unavailable knowledge, civil date errors and invalid series', function (string $key, mixed $value, string $known, string $reason): void {
    $identity = [...restrictedSelection74Identity(), $key => $value];
    $result = (new DpsRestrictedSchemaSelection)->qualify($identity, restrictedSelection74Projection(),
        restrictedSelection74Schema(), $known);
    expect($result['reason'])->toBe('RTC_DPS_RESTRICTED_SCHEMA_'.$reason)
        ->and($result['production_ready'])->toBeFalse();
})->with([
    ['environment', '1', '2026-10-14T12:30:00Z', 'PRODUCTION_ENVIRONMENT_FORBIDDEN'],
    ['issued_at', '2026-10-01T13:30:00-03:00', '2026-10-14T12:30:00Z', 'ARTIFACT_NOT_YET_OBSERVED'],
    ['series', '1', '2026-10-14T11:34:49Z', 'ARTIFACT_NOT_YET_OBSERVED'],
    ['issued_at', '2026-10-14T09:30:00-03:00', '2026-10-14T12:29:59Z', 'ISSUANCE_AFTER_KNOWLEDGE'],
    ['issued_at', '2026-02-30T13:00:00-03:00', '2026-10-14T12:30:00Z', 'EXACT_TIMESTAMP_REQUIRED'],
    ['issued_at', '2026-10-14T09:30:00-14:01', '2026-10-14T12:30:00Z', 'EXACT_TIMESTAMP_REQUIRED'],
    ['competence_date', '2026-02-30', '2026-10-14T12:30:00Z', 'COMPETENCE_DATE_CONFLICT'],
    ['competence_date', '2026-10-15', '2026-10-14T12:30:00Z', 'COMPETENCE_DATE_CONFLICT'],
    ['series', '90000', '2026-10-14T12:30:00Z', 'SERIES_OUTSIDE_RESTRICTED_SCHEMA'],
    ['series', "1\n", '2026-10-14T12:30:00Z', 'SERIES_OUTSIDE_RESTRICTED_SCHEMA'],
    ['series', '^1$', '2026-10-14T12:30:00Z', 'SERIES_OUTSIDE_RESTRICTED_SCHEMA'],
]);

it('refuses source, root and scope substitution even for a structurally valid fixture', function (string $case): void {
    $projection = restrictedSelection74Projection();
    $schema = restrictedSelection74Schema();
    if ($case === 'source') {
        $schema['source_sha256'] = str_repeat('0', 64);
    } elseif ($case === 'root') {
        $schema['root_schema'] = 'Schemas/1.01/DPS_v1.01.xsd';
    } elseif ($case === 'scope') {
        $projection['schema_selection']['scope'] = 'PRODUCTION';
    } else {
        $projection['schema_selection']['remote_deployment_verified'] = true;
    }
    $result = (new DpsRestrictedSchemaSelection)->qualify(restrictedSelection74Identity(), $projection,
        $schema, '2026-10-14T12:30:00Z');
    expect($result['reason'])->toBe('RTC_DPS_RESTRICTED_SCHEMA_ARTIFACT_CONTRACT_NOT_PROVEN');
})->with(['source', 'root', 'scope', 'deployment']);
