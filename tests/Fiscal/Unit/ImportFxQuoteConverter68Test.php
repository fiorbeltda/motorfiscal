<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ImportFxQuoteConverter;

it('qualifies observed FX sale quotations and preserves all explicit refusals', function (string $name): void {
    $root = dirname(__DIR__, 3);
    $native = json_decode((string) file_get_contents($root.'/storage/app/phase4b-import-fx68-integration-20261013/fx-permanent-inputs.json'), true, 512, JSON_THROW_ON_ERROR);
    $case = $native['cases'][$name];
    $result = (new ImportFxQuoteConverter)->convert($case['amount'], $case['currency'], $case['tax_point'],
        new DateTimeImmutable($case['known_at']), $case['context'], $case['trusted_source_inputs']);
    expect($result)->toBe($case['result'])->and($result['production_ready'])->toBeFalse();
    if ($case['expected_reason'] === null) {
        expect($result['converted_gross_brl'])->toBe($case['expected_brl'])
            ->and($result['tax_or_credit_authorized'])->toBeFalse()
            ->and($result['operation_document_binding_verified'])->toBeFalse();
    } else {
        expect($result['reason'])->toBe('RTC_IMPORT_FX_'.$case['expected_reason']);
    }
})->with(['usd-direct', 'usd-weekend', 'eur-direct', 'aud-direct', 'zero', 'rounding-boundary', 'non-usd-weekend', 'missing-source',
    'hash', 'currency', 'purchase', 'intermediate', 'future-observation', 'endpoint', 'query-currency', 'range',
    'pagination', 'negative', 'tax-date', 'duplicate-closing', 'nonpositive-rate', 'expected-rate', 'expected-date']);
