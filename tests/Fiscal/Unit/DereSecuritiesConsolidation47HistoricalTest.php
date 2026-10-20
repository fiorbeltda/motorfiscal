<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmRealizationConsolidation47Fixtures.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use Brick\Math\BigDecimal;

$expansionRoot = dirname(__DIR__, 3);
$expansionIndex = json_decode(file_get_contents($expansionRoot.'/storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28/expansion-cases.json'), true, 512, JSON_THROW_ON_ERROR);

it('consolidates every previously captured historical realization with its independent expected amounts', function (string $case, array $metadata): void {
    $root = dirname(__DIR__, 3);
    $fixture = json_decode(file_get_contents($root.'/'.$metadata['fixture']), true, 512, JSON_THROW_ON_ERROR);
    expect(hash_file('sha256', $root.'/'.$metadata['fixture']))->toBe($metadata['fixture_sha256']);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.09-28-draft47');
    expect($manifest['version'])->toBe('2026.10.09-28-draft47')->and($operation['base']['net_base'])->toBe($fixture['expected']['base']);
    $yield = array_values(array_filter($operation['base']['entries'], static fn (array $entry): bool => ($entry['id'] ?? null) === 'yield1'))[0]['d2101_binding'];
    expect($yield['taxable_amount'])->toBe($fixture['expected']['auxiliary_base']);
    foreach ($fixture['independent_oracle'] as $field => $expected) {
        if ($field === 'daily_memory') {
            expect($yield['calculation'][$field])->toHaveCount(count($expected));
            foreach ($expected as $index => $row) {
                foreach ($row as $key => $value) {
                    expect($yield['calculation'][$field][$index][$key])->toBe($value);
                }
            }
        } else {
            expect($yield['calculation'][$field])->toBe($expected);
        }
    }
    $inventory = $operation['base']['realization_bindings']['tvm1']['analysis']['recognition_inventory'];
    foreach (['opening', 'closing', 'closing_fiscal_components'] as $edge) {
        foreach ($fixture['recognition_inventory'][$edge] as $field => $amount) {
            expect(BigDecimal::of($inventory[$edge][$field])->isEqualTo($amount))->toBeTrue();
        }
    }
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])->and($projected['production_ready'])->toBeFalse();
    if (getenv('TVM_REALIZATION_CONSOLIDATED_CAPTURE') === '1') {
        $scenario = json_decode(file_get_contents($root.'/'.$metadata['original_financial_input']), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-consolidated47-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $kind => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-realization-consolidated-2026.10.09-28-draft47/'.$case.'-'.$kind.'.json';
            if (is_file($path)) {
                expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));
            } else {
                $stream = fopen($path, 'x');
                expect($stream)->not->toBeFalse();
                fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                fclose($stream);
            }
        }
    }
})->with(array_map(static fn (string $case, array $metadata): array => [$case, $metadata], array_keys($expansionIndex['cases']), array_values($expansionIndex['cases'])));
