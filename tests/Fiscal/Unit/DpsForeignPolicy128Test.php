<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CIndOpReference126;
use App\Modules\Fiscal\ConsumptionReform\CreditInputPolicy122;
use App\Modules\Fiscal\ConsumptionReform\DpsForeignPolicy128;
use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\NFSe\DpsForeignPartyProjector;

function foreign128Codes(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.22-128-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('requires128 and inherited guards without relying on optional markers', function (): void {
    $codes = ['version' => DpsForeignPolicy128::VERSION];
    expect(DpsForeignPolicy128::required($codes))->toBeTrue()->and(DpsLayoutPolicy127::required($codes))->toBeTrue()
        ->and(CIndOpReference126::required($codes))->toBeTrue()->and(CreditInputPolicy122::required($codes))->toBeTrue()
        ->and(DpsForeignPolicy128::required(['local_xml_projection' => ['contract_version' => DpsForeignPolicy128::CONTRACT]]))->toBeTrue();
});

it('binds foreign source policy without conferring documentary or fiscal approval', function (): void {
    $result = (new DpsForeignPolicy128)->qualify(foreign128Codes(), new DateTimeImmutable(DpsLayoutPolicy127::OBSERVED_AT));
    expect($result['status'])->toBe('RESOLVED')->and($result['remote_deployment_verified'])->toBeFalse()
        ->and($result['document_obligation_complete'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
});

it('rejects changed policy currency and source metadata', function (string $case): void {
    $codes = foreign128Codes();
    $known = new DateTimeImmutable('2026-10-22T11:38:00+00:00');
    switch ($case) {
        case 'removed': unset($codes['dps_foreign_policy128']);
            break;
        case 'schema': $codes['dps_foreign_policy128']['schema_source_sha256'] = str_repeat('0', 64);
            break;
        case 'approval': $codes['dps_foreign_policy128']['document_obligation_complete'] = true;
            break;
        case 'currency': $codes['foreign_acquirer_candidate']['observed_currency_codes']['BRL'] = '999';
            break;
        case 'proof': $codes['foreign_acquirer_candidate']['currency_competence_validity_proven'] = true;
            break;
        case 'source': $codes['foreign_acquirer_candidate']['currency_code_source_sha256'] = str_repeat('0', 64);
            break;
        case 'early': $known = new DateTimeImmutable('2026-10-22T11:13:42.341712+00:00');
            break;
    }
    expect((new DpsForeignPolicy128)->qualify($codes, $known)['status'])->toBe('ACTION_REQUIRED');
})->with(['removed', 'schema', 'approval', 'currency', 'proof', 'source', 'early']);

it('requires explicit knowledge for a foreign fragment in the new source context', function (): void {
    $identity = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dps-foreign-acquirer-2026.10.15-76-draft1/nif-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    $projector = new DpsForeignPartyProjector;
    $missing = $projector->project($identity['foreign_acquirer'], 'toma', foreign128Codes());
    expect($missing['reason'])->toBe('RTC_DPS_FOREIGN_PARTY_EXPLICIT_KNOWLEDGE_REQUIRED');
    $current = $projector->project($identity['foreign_acquirer'], 'toma', foreign128Codes(), new DateTimeImmutable('2026-10-22T11:38:00+00:00'));
    expect($current['status'])->toBe('PROJECTED')->and($current['schema_sha256'])->toBe(DpsLayoutPolicy127::SOURCE_SHA256)
        ->and($current['identity_authenticity_verified'])->toBeFalse()->and($current['residency_verified'])->toBeFalse();
});
