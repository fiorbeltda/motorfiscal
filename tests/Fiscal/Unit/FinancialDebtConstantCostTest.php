<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtConstantCostCalculator;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string, mixed> */
function constantDebtCostFixture(string $case): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $k = (new FiscalEngineFactory)->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.06-24.json');
    $v = json_decode(file_get_contents($root.'scenarios/dere-constant-cost-2026.10.06-24/'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $p = $k['documents']['rtc_code_catalog']['dere_debt_constant_cost_calculation'];

    return ['input' => $v['position'], 'expected' => $v['expectation'], 'series' => $p['series'], 'rule' => $p,
        'known' => new DateTimeImmutable($k['documents']['bundle_manifest']['knowledge_available_at'])];
}

it('calculates bounded per-title bases from the actual observed daily series and rejects unproven positions', function (string $case): void {
    $f = constantDebtCostFixture($case);
    $r = (new FinancialDebtConstantCostCalculator)->calculate($f['input'], $f['series'], $f['known'], $f['rule']);
    expect($r['status'])->toBe($f['expected']['status']);
    if ($r['status'] !== 'RESOLVED') {
        expect($r['reason'])->toBe($f['expected']['reason']);

        return;
    }
    $expectedBase = ['capped-yield' => '98.17', 'below-cap' => '65.00', 'negative-variation' => '0.00',
        'zero-variation' => '0.00', 'pis-exceeds-yield' => '0.00', 'missing-pis' => '108.17', 'fractional-cost' => '99.50'];
    expect($r['vSelic'])->toBe($case === 'fractional-cost' ? '109.50' : '108.17')
        ->and($r['vApur'])->toBe($expectedBase[$case])->and($r['daily_rates_applied'])->toBe(21)
        ->and($r['daily_memory'])->toHaveCount(21)->and($r['negative_base_created'])->toBeFalse()->and($r['production_ready'])->toBeFalse();
})->with(function (): array {
    return array_map(static fn (string $p): string => basename($p, '.json'),
        glob(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dere-constant-cost-2026.10.06-24/*.json'));
});

it('does not let a negative title reduce the base of another title', function (): void {
    $calculate = static function (string $case): array {
        $f = constantDebtCostFixture($case);

        return (new FinancialDebtConstantCostCalculator)->calculate($f['input'], $f['series'], $f['known'], $f['rule']);
    };
    $loss = $calculate('negative-variation');
    $gain = $calculate('below-cap');
    expect($loss['vApur'])->toBe('0.00')->and($gain['vApur'])->toBe('65.00')
        ->and($loss['cross_title_loss_offset_performed'])->toBeFalse();
});

it('rejects rate row edits and substituted rate units under the frozen series hash', function (): void {
    $f = constantDebtCostFixture('capped-yield');
    $calc = new FinancialDebtConstantCostCalculator;
    $f['series']['rows'][0]['daily_percent'] = '0.01';
    expect($calc->calculate($f['input'], $f['series'], $f['known'], $f['rule'])['reason'])->toBe('RTC_DERE_D2101_OFFICIAL_DAILY_SERIES_UNPROVEN');
    $f = constantDebtCostFixture('capped-yield');
    $f['series']['unit'] = 'PERCENT_PER_YEAR';
    expect($calc->calculate($f['input'], $f['series'], $f['known'], $f['rule'])['reason'])->toBe('RTC_DERE_D2101_OFFICIAL_DAILY_SERIES_UNPROVEN');
});
