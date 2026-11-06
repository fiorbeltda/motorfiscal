<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CreditZeroSemanticsPolicyB02;

it('stays disabled in every historical catalog and is enabled only from the B23 version', function (): void {
    $enabled = [];
    foreach (glob(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-*.json') as $file) {
        $codes = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (CreditZeroSemanticsPolicyB02::required($codes)) {
            $enabled[] = basename($file);
        }
    }
    // Accepted catalogs up to 141 keep their replays/hashes; B23 is the first explicitly gated version and every
    // later versioned catalog (B24+) inherits the gate.
    $later = array_values(array_filter(array_map('basename', glob(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-*.json')),
        fn (string $name): bool => strcmp($name, 'rtc-codes-2026.11.04-b23-draft1.json') >= 0));
    expect($enabled)->toBe($later)->and($enabled[0])->toBe('rtc-codes-2026.11.04-b23-draft1.json');
});

it('enables only with the exact policy block', function (mixed $policy, bool $expected): void {
    expect(CreditZeroSemanticsPolicyB02::required(['version' => 'x', CreditZeroSemanticsPolicyB02::KEY => $policy]))->toBe($expected);
})->with([
    'exact' => [CreditZeroSemanticsPolicyB02::POLICY, true],
    'reordered' => [array_reverse(CreditZeroSemanticsPolicyB02::POLICY, true), true],
    'mode changed' => [[...CreditZeroSemanticsPolicyB02::POLICY, 'mode' => 'REPLACE_OUTPUT'], false],
    'claims status change' => [[...CreditZeroSemanticsPolicyB02::POLICY, 'status_or_amount_changed' => true], false],
    'boolean flag' => [true, false],
    'empty' => [[], false],
]);
