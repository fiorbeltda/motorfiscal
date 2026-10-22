<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CIndOpReference126;
use App\Modules\Fiscal\ConsumptionReform\CreditInputPolicy122;
use App\Modules\Fiscal\ConsumptionReform\DpsIssuerFieldValidator;
use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection127;

function dps127Catalog(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.22-127-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('keeps new and inherited policies mandatory in127 even when markers are removed', function (): void {
    $codes = ['version' => DpsLayoutPolicy127::VERSION];
    expect(DpsLayoutPolicy127::required($codes))->toBeTrue()
        ->and(CIndOpReference126::required($codes))->toBeTrue()->and(CreditInputPolicy122::required($codes))->toBeTrue()
        ->and(DpsLayoutPolicy127::required(['version' => CIndOpReference126::VERSION]))->toBeFalse();
});

it('binds the exact observed schema and source field inventory', function (): void {
    $catalog = dps127Catalog();
    $result = (new DpsLayoutPolicy127)->qualify($catalog, new DateTimeImmutable(DpsLayoutPolicy127::OBSERVED_AT));
    expect($result['status'])->toBe('RESOLVED')->and($result['remote_deployment_verified'])->toBeFalse()
        ->and($catalog['dps_xsd_fields'])->toHaveCount(236)
        ->and($catalog['dps_xsd_fields']['DPS.infDPS.finNFSe']['enum'])->toBe(['0'])
        ->and($catalog['dps_xsd_fields']['DPS.infDPS.IBSCBS.indFinal']['enum'])->toBe(['0', '1']);
});

it('maps only explicit current field paths including prefixed import paths', function (string $prefix): void {
    $fields = [$prefix.'IBSCBS.finNFSe' => '0', $prefix.'IBSCBS.valores.trib.gIBSCBS.CST' => '000',
        $prefix.'IBSCBS.valores.trib.gIBSCBS.cClassTrib' => '000001', $prefix.'IBSCBS.cIndOp' => '100301'];
    $mapped = (new DpsLayoutPolicy127)->mapIssuerFields($fields);
    expect($mapped)->toBe([$prefix.'finNFSe' => '0', $prefix.'IBSCBS.valores.trib.CST' => '000',
        $prefix.'IBSCBS.valores.trib.cClassTrib' => '000001', $prefix.'IBSCBS.cIndOp' => '100301']);
    $result = (new DpsIssuerFieldValidator)->validate($mapped, ['xsd_source_hash' => DpsLayoutPolicy127::SOURCE_SHA256], dps127Catalog());
    expect($result['status'])->toBe('VALID');
})->with(['', 'DPS.infDPS.']);

it('does not accept old field paths under the new bound schema', function (string $path): void {
    $result = (new DpsIssuerFieldValidator)->validate([$path => '0'], ['xsd_source_hash' => DpsLayoutPolicy127::SOURCE_SHA256], dps127Catalog());
    expect($result['reason'])->toBe('RTC_DPS_ISSUER_FIELD_NOT_IN_XSD');
})->with(['IBSCBS.finNFSe', 'IBSCBS.valores.trib.gIBSCBS.CST', 'IBSCBS.valores.trib.gIBSCBS.cClassTrib']);

it('refuses policy and facet mutations instead of falling back to an earlier schema', function (string $case): void {
    $codes = dps127Catalog();
    switch ($case) {
        case 'absent': unset($codes['dps_layout_policy127']);
            break;
        case 'hash': $codes['dps_layout_policy127']['source_sha256'] = str_repeat('0', 64);
            break;
        case 'activation': $codes['dps_layout_policy127']['remote_deployment_verified'] = true;
            break;
        case 'enum': $codes['dps_xsd_fields']['DPS.infDPS.finNFSe']['enum'][] = '1';
            break;
        case 'old root': $codes['dps_xsd']['root_schema'] = 'old.xsd';
            break;
        case 'old source': $codes['dps_xsd']['source_sha256'] = '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc';
            break;
        case 'contract': $codes['local_xml_projection']['contract_version'] = 'OLD';
            break;
        case 'scope': $codes['local_xml_projection']['scope'] = 'PRODUCTION';
            break;
        case 'replay': $codes['local_xml_projection']['requires_deterministic_snapshot_replay'] = false;
            break;
        case 'selection': $codes['local_xml_projection']['schema_selection']['environment'] = '1';
            break;
    }
    $result = (new DpsLayoutPolicy127)->qualify($codes, new DateTimeImmutable('2026-10-22T11:20:00+00:00'));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['production_ready'])->toBeFalse();
})->with(['absent', 'hash', 'activation', 'enum', 'old root', 'old source', 'contract', 'scope', 'replay', 'selection']);

it('selects only a locally observed restricted schema for explicit identity and knowledge', function (string $case, string $reason): void {
    $identity = ['environment' => '2', 'issued_at' => '2026-10-22T11:19:00+00:00', 'competence_date' => '2026-10-01', 'series' => '1'];
    $known = '2026-10-22T11:20:00+00:00';
    switch ($case) {
        case 'production': $identity['environment'] = '1';
            break;
        case 'unknown environment': unset($identity['environment']);
            break;
        case 'early knowledge': $known = '2026-10-22T11:13:42.341712+00:00';
            break;
        case 'early issuance': $identity['issued_at'] = '2026-10-22T11:13:42+00:00';
            break;
        case 'future issuance': $identity['issued_at'] = '2026-10-22T11:20:01+00:00';
            break;
        case 'invalid competence': $identity['competence_date'] = '2026-02-30';
            break;
        case 'future competence': $identity['competence_date'] = '2026-10-23';
            break;
        case 'series90000': $identity['series'] = '90000';
            break;
        case 'series99999': $identity['series'] = '99999';
            break;
        case 'series leading': $identity['series'] = '00001';
            break;
        case 'series89999': $identity['series'] = '89999';
            break;
        case 'boolean series': $identity['series'] = true;
            break;
        case 'invalid known': $known = '2026-02-30T11:20:00+00:00';
            break;
        case 'invalid offset': $known = '2026-10-22T11:20:00+14:01';
            break;
        case 'no zone': $identity['issued_at'] = '2026-10-22T11:19:00';
            break;
    }
    $result = (new DpsRestrictedSchemaSelection127)->qualify($identity, dps127Catalog(), $known);
    expect($result['reason'])->toBe('RTC_DPS127_'.$reason);
    if ($result['status'] === 'VALID') {
        expect($result['remote_deployment_verified'])->toBeFalse()->and($result['document_obligation_qualified'])->toBeFalse();
    }
})->with([
    ['valid', 'LOCAL_SCHEMA_SELECTION_BOUND'], ['series leading', 'LOCAL_SCHEMA_SELECTION_BOUND'], ['series89999', 'LOCAL_SCHEMA_SELECTION_BOUND'],
    ['production', 'PRODUCTION_ENVIRONMENT_FORBIDDEN'], ['unknown environment', 'PRODUCTION_ENVIRONMENT_FORBIDDEN'],
    ['early knowledge', 'SOURCE_NOT_YET_OBSERVED'], ['early issuance', 'ARTIFACT_NOT_YET_OBSERVED'], ['future issuance', 'ISSUANCE_AFTER_KNOWLEDGE'],
    ['invalid competence', 'COMPETENCE_DATE_CONFLICT'], ['future competence', 'COMPETENCE_DATE_CONFLICT'],
    ['series90000', 'SERIES_OUTSIDE_RESTRICTED_SCHEMA'], ['series99999', 'SERIES_OUTSIDE_RESTRICTED_SCHEMA'], ['boolean series', 'SERIES_OUTSIDE_RESTRICTED_SCHEMA'],
    ['invalid known', 'EXACT_TIMESTAMP_REQUIRED'], ['invalid offset', 'EXACT_TIMESTAMP_REQUIRED'], ['no zone', 'EXACT_TIMESTAMP_REQUIRED'],
]);
