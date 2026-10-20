<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\PartialExportFxTransactionQualifier;

it('binds export currency to the parties documents and allocation or refuses inconsistent proof', function (string $name): void {
    $root = dirname(__DIR__, 3);
    $native = json_decode((string) file_get_contents($root.'/storage/app/phase4b-partial-export-fx73-preparation-20261014/partial-export-transaction-native-result.json'), true, 512, JSON_THROW_ON_ERROR);
    $case = $native['cases'][$name];
    $result = (new PartialExportFxTransactionQualifier)->qualify($case['facts'], $case['tax_point'], new DateTimeImmutable($case['known_at']), $case['policy']);
    expect($result)->toBe($case['result'])->and($result['production_ready'])->toBeFalse();
    if ($name === 'valid') {
        expect($result['converted_gross_brl'])->toBe('5179.50')->and($result['document_authenticated'])->toBeFalse();
    } else {
        expect($result['status'])->toBe('ACTION_REQUIRED');
    }
})->with([
    'valid', 'origin-optional', 'origin-missing', 'foreign-supplier', 'domestic-acquirer', 'foreign-domestic-recipient',
    'zero-share', 'full-share', 'missing-domestic-document', 'wrong-document-proof', 'wrong-allocation-proof',
    'wrong-source-proof', 'wrong-operation', 'wrong-adjustment-unit', 'proof-hash', 'proof-future', 'proof-invalid-date',
    'proof-invalid-offset', 'unknown-source', 'eur-unqualified', 'proof-missing',
]);
