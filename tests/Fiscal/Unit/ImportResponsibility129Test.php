<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CIndOpReference126;
use App\Modules\Fiscal\ConsumptionReform\CreditInputPolicy122;
use App\Modules\Fiscal\ConsumptionReform\DpsForeignPolicy128;
use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\ConsumptionReform\ImportResponsibility129;

it('keeps all inherited source guards mandatory by129 version without optional markers', function (): void {
    $codes = ['version' => ImportResponsibility129::VERSION];
    expect(ImportResponsibility129::required($codes))->toBeTrue()
        ->and(DpsForeignPolicy128::required($codes))->toBeTrue()
        ->and(DpsLayoutPolicy127::required($codes))->toBeTrue()
        ->and(CIndOpReference126::required($codes))->toBeTrue()
        ->and(CreditInputPolicy122::required($codes))->toBeTrue()
        ->and(ImportResponsibility129::required(['version' => DpsForeignPolicy128::VERSION]))->toBeFalse();
});

it('rejects altered policy or premature source knowledge', function (string $case): void {
    $policy = ImportResponsibility129::policy();
    $codes = ['version' => ImportResponsibility129::VERSION, 'import_responsibility_policy129' => $policy];
    $known = new DateTimeImmutable(ImportResponsibility129::OBSERVED_AT);
    switch ($case) {
        case 'missing': unset($codes['import_responsibility_policy129']);
            break;
        case 'source': $codes['import_responsibility_policy129']['source']['sha256'] = str_repeat('0', 64);
            break;
        case 'reference': $codes['import_responsibility_policy129']['references']['ACQUIRER']['article'] = 'LC214 art. 36';
            break;
        case 'extra': $codes['import_responsibility_policy129']['approved'] = true;
            break;
        case 'earlier': $known = $known->modify('-1 microsecond');
            break;
    }
    expect((new ImportResponsibility129)->qualify($codes, $known)['status'])->toBe('ACTION_REQUIRED');
})->with(['missing', 'source', 'reference', 'extra', 'earlier']);

it('binds current source metadata without implying payment or documentary completion', function (): void {
    $codes = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.22-129-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new ImportResponsibility129)->qualify($codes, new DateTimeImmutable(ImportResponsibility129::OBSERVED_AT));
    expect($result['status'])->toBe('RESOLVED')->and($result['payment_proven'])->toBeFalse()
        ->and($result['document_obligation_complete'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
});
