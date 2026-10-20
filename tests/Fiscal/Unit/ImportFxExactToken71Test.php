<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ImportFxQuoteConverter;

it('preserves the exact source monetary token and refuses unsupported quote encodings', function (string $token, ?string $expected): void {
    $root = dirname(__DIR__, 3);
    $record = json_decode((string) file_get_contents($root.'/storage/app/phase4b-import-fx68-integration-20261013/fx-permanent-inputs.json'), true, 512, JSON_THROW_ON_ERROR);
    $case = $record['cases']['usd-direct'];
    $sources = $case['trusted_source_inputs'];
    $raw = preg_replace_callback('/("cotacaoVenda"\s*:\s*)[0-9]+(?:[.][0-9]+)?/', static fn (array $match): string => $match[1].$token, $sources['USD']['raw_json']);
    expect($raw)->toBeString();
    $sources['USD']['raw_json'] = $raw;
    $sources['USD']['sha256'] = hash('sha256', $raw);
    $result = (new ImportFxQuoteConverter)->convert('1000000.00', 'USD', $case['tax_point'], new DateTimeImmutable($case['known_at']), $case['context'], $sources);
    if ($expected !== null) {
        expect($result['converted_gross_brl'])->toBe($expected)
            ->and($result['selected_quote']['rate'])->toBe($token)
            ->and($result['production_ready'])->toBeFalse();
    } else {
        expect($result['status'])->toBe('ACTION_REQUIRED')
            ->and($result['reason'])->toBe('RTC_IMPORT_FX_POSITIVE_SALE_BRL_PER_UNIT_QUOTE_REQUIRED');
    }
})->with([
    ['9999999999.12345678', '9999999999123456.78'],
    ['"5.1795"', null],
    ['5.1795e0', null],
]);
