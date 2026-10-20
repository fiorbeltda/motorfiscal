<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ImportFxTransactionQualifier;

it('enforces qualified material quote origins and exact civil offset boundaries', function (string $family, string $name): void {
    $root = dirname(__DIR__, 3);
    $record = json_decode((string) file_get_contents($root.'/storage/app/phase4b-fx70-origin-preparation-20261014/'.$family.'-permanent-inputs.json'), true, 512, JSON_THROW_ON_ERROR);
    $case = $record['cases'][$name];
    $result = (new ImportFxTransactionQualifier)->qualify($case['facts'], $case['tax_point'], new DateTimeImmutable($case['known_at']), $case['policy']);
    expect($result)->toBe($case['result'])->and($result['production_ready'])->toBeFalse();
    if ($result['status'] === 'LOCAL_IMPORT_FX_TRANSACTION_BOUND') {
        expect($result['converted_gross_brl'])->toBe('5179.50')->and($result['document_authenticated'])->toBeFalse();
    } else {
        expect($result['status'])->toBe('ACTION_REQUIRED');
    }
})->with([
    ['origin', 'old-policy'], ['origin', 'usd-qualified'], ['origin', 'origin-missing'], ['origin', 'origin-source-hash'],
    ['origin', 'origin-future'], ['origin', 'origin-hash'], ['origin', 'wrong-strategy'], ['origin', 'eur-refinitiv'], ['origin', 'aud-no-authority'],
    ['offset', 'zero'], ['offset', 'positive-boundary'], ['offset', 'negative-boundary'], ['offset', 'positive-minute-over'],
    ['offset', 'negative-minute-over'], ['offset', 'hour-over'], ['offset', 'minute-over'], ['offset', 'impossible-hour'],
]);
