<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AnnexStandardQualificationPolicyB07;

require_once dirname(__DIR__).'/Support/b07/AnnexStandardSpecB07.php';

it('stays disabled in every historical catalog and is enabled only from the B23 version', function (): void {
    $enabled = [];
    foreach (glob(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-*.json') as $file) {
        if (AnnexStandardQualificationPolicyB07::required(json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR))) {
            $enabled[] = basename($file);
        }
    }
    // Accepted catalogs up to 141 keep their replays/hashes; B23 is the first explicitly gated version (A155/H2).
    expect($enabled)->toBe(['rtc-codes-2026.11.04-b23-draft1.json']);
});

it('enables only with the exact contract block', function (mixed $policy, bool $expected): void {
    expect(AnnexStandardQualificationPolicyB07::required([AnnexStandardQualificationPolicyB07::KEY => $policy]))->toBe($expected);
})->with([
    'exact' => [AnnexStandardQualificationPolicyB07::POLICY, true],
    'default to standard' => [[...AnnexStandardQualificationPolicyB07::POLICY, 'never' => []], false],
    'flag only' => [true, false],
]);

it('keeps the oracle aligned with the contract outcomes and never defaults a treatment', function (): void {
    $outcomes = AnnexStandardQualificationPolicyB07::POLICY['outcomes'];
    $rule = b07Rules()[0];
    $seen = [];
    foreach (B07_CASES as $case) {
        $expected = b07Expected($rule, $case);
        if ($expected['reason'] !== null) {
            expect($outcomes)->toHaveKey($expected['reason'])
                ->and($outcomes[$expected['reason']])->toBe($expected['status']);
            $seen[] = $expected['reason'];
        }
    }
    expect(array_values(array_unique($seen)))->toHaveCount(count($seen))
        ->and(b07Expected($rule, 'standard-without-justification')['cclasstrib'])->toBeNull()
        ->and(b07Expected($rule, 'standard-missing-nbs')['cclasstrib'])->toBeNull()
        ->and(b07Expected($rule, 'standard-justified')['cclasstrib'])->toBe('000001')
        ->and(b07Expected($rule, 'annex-correct-reduced')['cclasstrib'])->toBeIn(['200028', '200029']);
});
