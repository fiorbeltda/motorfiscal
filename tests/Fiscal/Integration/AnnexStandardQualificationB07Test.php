<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AnnexStandardQualificationPolicyB07;

require_once dirname(__DIR__).'/Support/b07/AnnexStandardSpecB07.php';

/** First bundle whose rtc_codes catalog carries the exact B07 gate, or null while A has not delivered it. */
function b07EnabledBundle(): ?string
{
    foreach (glob(B07_ROOT.'/resources/fiscal/bundles/*.json') as $path) {
        $bundle = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $version = $bundle['catalog_versions']['rtc_codes'] ?? null;
        $catalog = $version === null ? null : B07_ROOT."/resources/fiscal/catalogs/rtc-codes-{$version}.json";
        if ($catalog !== null && is_file($catalog)
            && AnnexStandardQualificationPolicyB07::required(json_decode((string) file_get_contents($catalog), true, 512, JSON_THROW_ON_ERROR))) {
            return 'bundles/'.basename($path);
        }
    }

    return null;
}

it('targets exactly the 40 rules of question Q-B06-1, each with a reduced twin rule', function (): void {
    $rules = b07Rules();
    $catalog = b07Catalog()['engine_decision_rules'];
    expect($rules)->toHaveCount(40);
    foreach ($rules as $id) {
        expect($catalog[$id]['cclasstrib'])->toBe('000001')
            ->and($catalog)->toHaveKey(str_replace('STANDARD-1', 'REDUCED_60-1', $id));
    }
});

it('keeps the B07 gate disabled in every published catalog', function (): void {
    expect(b07EnabledBundle())->toBeNull();
});

it('characterizes the historical gap on bundle 141: each rule is reachable and accepts a contradictory standard declaration', function (string $id): void {
    $catalog = b07Catalog()['engine_decision_rules'];
    $reduced = b07Run(b07Scenario($id, 'annex-correct-reduced'))['decision']['consumption_reform'];
    $standard = b07Run(b07Scenario($id, 'standard-contradiction'))['decision']['consumption_reform'];
    expect($reduced['status'])->toBe('VALID')
        ->and($reduced['decision']['classification']['rule_id'])->toBe(str_replace('STANDARD-1', 'REDUCED_60-1', $id))
        ->and($reduced['decision']['classification']['cclasstrib'])->toBe($catalog[str_replace('STANDARD-1', 'REDUCED_60-1', $id)]['cclasstrib'])
        // Historical behavior (to be preserved for bundles without the gate): full taxation even with the
        // annex description confirmed. This is the gap that B07 closes in a new versioned bundle.
        ->and($standard['status'])->toBe('VALID')
        ->and($standard['decision']['classification']['rule_id'])->toBe($id)
        ->and($standard['decision']['classification']['cclasstrib'])->toBe('000001');
})->with(fn (): array => b07Rules());

it('applies the approved decision table once the Agent A implementation and a gated bundle exist', function (string $id, string $case): void {
    $bundle = b07EnabledBundle();
    if ($bundle === null) {
        $this->markTestSkipped('B07 aguardando implementação do Agente A e bundle com o gate annex_standard_qualification_b07.');
    }
    $expected = b07Expected($id, $case);
    $scenario = b07Scenario($id, $case);
    $actual = b07Run($scenario, $bundle)['decision']['consumption_reform'] ?? [];
    if ($expected['status'] === 'HISTORICAL') {
        $historical = b07Run($scenario)['decision']['consumption_reform'] ?? [];
        expect($actual['status'] ?? null)->toBe($historical['status'] ?? null)
            ->and($actual['reasons'] ?? null)->toBe($historical['reasons'] ?? null);

        return;
    }
    expect($actual['status'] ?? null)->toBe($expected['status']);
    if ($expected['reason'] !== null) {
        expect($actual['reasons'] ?? [])->toContain($expected['reason']);
    }
    if ($expected['cclasstrib'] !== null) {
        expect($actual['decision']['classification']['cclasstrib'])->toBe($expected['cclasstrib']);
    }
})->with(function (): array {
    $cases = [];
    foreach (b07Rules() as $id) {
        foreach (B07_CASES as $case) {
            $cases["{$case} {$id}"] = [$id, $case];
        }
    }

    return $cases;
});
