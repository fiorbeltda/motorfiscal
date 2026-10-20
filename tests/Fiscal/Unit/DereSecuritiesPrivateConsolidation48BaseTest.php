<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmCurrentFixtures.php';

it('preserves captured private economics under the separate consolidated source policies', function (array $metadata): void {
    $root = dirname(__DIR__, 3);
    $scenario = json_decode(file_get_contents($root.'/'.$metadata['financial_input']), true, 512, JSON_THROW_ON_ERROR);
    expect(hash_file('sha256', $root.'/'.$metadata['financial_input']))->toBe($metadata['financial_input_sha256']);
    expect(hash_file('sha256', $root.'/'.$metadata['monthly_input']))->toBe($metadata['monthly_input_sha256']);
    $ledger = $scenario['facts']['rtc.financial_ledger'][0];
    $oldCatalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-'.$metadata['origin_catalog_version'].'.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.09-28-draft48.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($root.'/resources/fiscal/bundles/consumption-phase4b-2026.10.09-28-draft48.json'), true, 512, JSON_THROW_ON_ERROR);
    $previous = tvmCurrentBase($ledger, $oldCatalog['financial_operation']['base'], new DateTimeImmutable($scenario['knowledge_at']));
    $current = tvmCurrentBase($ledger, $catalog['financial_operation']['base'], new DateTimeImmutable($manifest['knowledge_available_at']));
    expect($previous['status'])->toBe('RESOLVED', $previous['reason']);
    expect($current['status'])->toBe('RESOLVED', $current['reason']);
    expect($current['net_base'])->toBe($previous['net_base']);
    expect(array_keys($current['realization_bindings']))->toBe(array_keys($previous['realization_bindings']));
    foreach ($previous['realization_bindings'] as $id => $binding) {
        $actual = $current['realization_bindings'][$id];
        expect($actual['analysis']['recognition_inventory'])->toBe($binding['analysis']['recognition_inventory']);
        expect(array_column($actual['financial_entries'], 'computed_amount', 'id'))->toBe(array_column($binding['financial_entries'], 'computed_amount', 'id'));
    }
})->with(function (): array {
    $root = dirname(__DIR__, 3);
    $index = json_decode(file_get_contents($root.'/storage/app/phase4b-private-consolidation48-draft-20261009-28/private-cases.json'), true, 512, JSON_THROW_ON_ERROR);

    return array_map(static fn (array $row): array => [$row], $index['cases']);
});
