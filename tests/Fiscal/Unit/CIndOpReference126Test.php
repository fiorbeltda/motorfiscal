<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CIndOpReference126;
use App\Modules\Fiscal\ConsumptionReform\CreditInputPolicy122;
use App\Modules\Fiscal\Core\Shared\LocalDate;

function cindop126Catalog(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.22-126-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('enforces versioned reference and inherited credit guards even if policy fields were removed', function (): void {
    expect(CIndOpReference126::required(['version' => CIndOpReference126::VERSION]))->toBeTrue()
        ->and(CreditInputPolicy122::required(['version' => CIndOpReference126::VERSION]))->toBeTrue()
        ->and(CIndOpReference126::required(['version' => CreditInputPolicy122::VERSION]))->toBeFalse();
});

it('retains every official code and literal permission without inventing treatment or locality', function (string $code, bool $allowed): void {
    $result = (new CIndOpReference126)->resolve(cindop126Catalog(), $code, 'LOCAL_SANDBOX',
        LocalDate::fromString('2026-10-22'), new DateTimeImmutable('2026-10-22T11:00:00+00:00'));
    expect($result['status'])->toBe($allowed ? 'REFERENCE_ROW_AVAILABLE' : 'INVALID')
        ->and($result['decision_authority'])->toBeFalse()->and($result['remote_deployment_proven'])->toBeFalse()
        ->and($result['fiscal_destination_determined'])->toBeFalse();
    if ($allowed) {
        expect($result['source_hash'])->toBe('95f30a44ee94adeea57fb92f3a0337b1e62fdc106f393e73894087889be9e5aa')
            ->and($result['code'])->toBe($code)->and($result['source_row'])->toBeGreaterThan(1);
    }
})->with(function (): array {
    // Explicit expectations from indNFSe in the preserved official source.
    $denied = ['010104', '010105', '010201', '010202', '080101', '090101', '090102', '110101', '110201', '120101', '130101', '130201'];
    $cases = [];
    foreach (array_keys(cindop126Catalog()['cindop']) as $code) {
        $code = (string) $code;
        $cases[$code] = [$code, ! in_array($code, $denied, true)];
    }

    return $cases;
});

it('refuses unknown codes including codes confined to the different restricted annex', function (string $code): void {
    $result = (new CIndOpReference126)->resolve(cindop126Catalog(), $code, 'LOCAL_SANDBOX',
        LocalDate::fromString('2026-10-22'), new DateTimeImmutable('2026-10-22T11:00:00+00:00'));
    expect($result['reason'])->toBe('RTC_CINDOP126_UNKNOWN_REFERENCE_CODE');
})->with(['030103', '030104', '050103', '050104', '999999', '20102', ' 100301']);

it('keeps observation time distinct from operation date and local coverage', function (string $on, string $known, string $reason): void {
    $result = (new CIndOpReference126)->resolve(cindop126Catalog(), '100301', 'LOCAL_SANDBOX',
        LocalDate::fromString($on), new DateTimeImmutable($known));
    expect($result['reason'])->toBe('RTC_CINDOP126_'.$reason);
})->with([
    ['2026-10-22', '2026-10-22T10:51:26.468336+00:00', 'SOURCE_NOT_YET_OBSERVED'],
    ['2026-10-22', '2026-10-22T10:51:26.468337+00:00', 'LOCAL_REFERENCE_AVAILABLE'],
    ['2026-01-04', '2026-10-22T11:00:00+00:00', 'OUTSIDE_LOCAL_COVERAGE'],
    ['2026-01-05', '2026-10-22T11:00:00+00:00', 'LOCAL_REFERENCE_AVAILABLE'],
    ['2026-12-31', '2026-10-22T11:00:00+00:00', 'LOCAL_REFERENCE_AVAILABLE'],
    ['2027-01-01', '2026-10-22T11:00:00+00:00', 'OUTSIDE_LOCAL_COVERAGE'],
]);

it('does not infer activation across official environments', function (string $environment, string $reason): void {
    $result = (new CIndOpReference126)->resolve(cindop126Catalog(), '100301', $environment,
        LocalDate::fromString('2026-10-22'), new DateTimeImmutable('2026-10-22T11:00:00+00:00'));
    expect($result['reason'])->toBe('RTC_CINDOP126_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['PRODUCTION_RESTRICTED', 'OFFICIAL_ENVIRONMENT_EFFECTIVE_DATE_UNPROVEN'],
    ['PRODUCTION', 'OFFICIAL_ENVIRONMENT_EFFECTIVE_DATE_UNPROVEN'],
    ['', 'EXPLICIT_ENVIRONMENT_REQUIRED'], ['production', 'EXPLICIT_ENVIRONMENT_REQUIRED'],
]);

it('rejects source policy row and permission mutations', function (string $mutation): void {
    $catalog = cindop126Catalog();
    switch ($mutation) {
        case 'missing': unset($catalog['cindop_reference126']);
            break;
        case 'environment': $catalog['cindop_reference126']['profiles']['PRODUCTION']['deployment_effective_from'] = '2026-01-01';
            break;
        case 'source': $catalog['sources']['cindop']['sha256'] = str_repeat('0', 64);
            break;
        case 'row': $catalog['cindop_reference126']['sources']['RTC_1_03_00']['rows']['100301']['values'][7] = 'N';
            break;
        case 'materialized': $catalog['cindop']['100301']['nfse_supported'] = false;
            break;
        case 'remove code': unset($catalog['cindop']['100301']);
            break;
        case 'version': $catalog['sources']['cindop']['version'] = '1.02.00';
            break;
    }
    $result = (new CIndOpReference126)->qualify($catalog, new DateTimeImmutable('2026-10-22T11:00:00+00:00'));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['decision_authority'])->toBeFalse();
})->with(['missing', 'environment', 'source', 'row', 'materialized', 'remove code', 'version']);
