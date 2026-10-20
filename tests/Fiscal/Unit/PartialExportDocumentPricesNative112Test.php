<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\PartialExportContractPrices110;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssPriceComposition111;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

dataset('price112-native110', function (): array {
    $index = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/price110-native112-index.json'), true, 512, JSON_THROW_ON_ERROR);

    return array_column(array_map(fn (array $row): array => ['name' => $row['name'], 'args' => [$row]], $index['cases']), 'args', 'name');
});

dataset('price112-native111', function (): array {
    $index = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/price111-native112-index.json'), true, 512, JSON_THROW_ON_ERROR);

    return array_column(array_map(fn (array $row): array => ['name' => $row['name'], 'args' => [$row]], $index['cases']), 'args', 'name');
});

it('preserves the160 captured standalone110 results after namespace installation', function (array $row): void {
    $path = dirname(__DIR__, 3).'/'.$row['path'];
    expect(hash_file('sha256', $path))->toBe($row['sha256']);
    $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $c = $fixture['input'];
    $before = CanonicalJson::hash($c);
    $call = fn (): array => (new PartialExportContractPrices110)->qualify($c['facts'], $c['material_records'], $c['result_records'],
        $c['identity_records'], $c['price_records'], $c['proof'], $c['known_at'], $c['policy']);
    expect($call())->toBe($fixture['expected_result'])->and($call())->toBe($fixture['expected_result'])
        ->and(CanonicalJson::hash($c))->toBe($before);
})->with('price112-native110');

it('preserves the107 captured standalone111 outcomes without granting base FX payment or issuance approval', function (array $row): void {
    $path = dirname(__DIR__, 3).'/'.$row['path'];
    expect(hash_file('sha256', $path))->toBe($row['sha256']);
    $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $c = $fixture['input'];
    $before = CanonicalJson::hash($c);
    $call = fn (): array => (new PartialExportIssPriceComposition111)->qualify($c['input110'], $c['records'], $c['proof'], $c['known_at'], $c['policy']);
    $actual = $call();
    expect($actual)->toBe($fixture['expected_result'])->and($call())->toBe($actual)->and(CanonicalJson::hash($c))->toBe($before);
    foreach (['rtc_facts_rewritten', 'rtc_allocation_used_as_iss_price_rule', 'conditional_discount_subtracted_from_iss_price',
        'iss_base_calculated', 'per_document_tax_amount_calculated', 'documentary_currency_conversion_qualified',
        'payment_receipt_proven', 'legal_applicability_approved', 'government_evidence_authenticated',
        'xml_generated', 'posting_authorized', 'production_ready'] as $flag) {
        expect($actual[$flag])->toBeFalse();
    }
})->with('price112-native111');
