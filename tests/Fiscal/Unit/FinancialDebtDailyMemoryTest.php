<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string,mixed> */
function dailyDebtMemoryFixture(string $case): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $k = (new FiscalEngineFactory)->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.06-26.json');
    $v = json_decode(file_get_contents($root.'scenarios/dere-daily-memory-2026.10.06-26/'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);

    return ['input' => $v['position'], 'expected' => $v['expectation'],
        'rule' => $k['documents']['rtc_code_catalog']['dere_debt_daily_memory_calculation'],
        'known' => new DateTimeImmutable($k['documents']['bundle_manifest']['knowledge_available_at'])];
}

it('reconstructs daily cost and movements against an independent decimal oracle and rejects incomplete or contradictory memory', function (string $case): void {
    $f = dailyDebtMemoryFixture($case);
    $r = (new FinancialDebtDailyMemoryCalculator)->calculate($f['input'], $f['rule']['series'], $f['known'], $f['rule']);
    expect($r['status'])->toBe($f['expected']['status'])->and($r['production_ready'])->toBeFalse();
    if ($r['status'] !== 'RESOLVED') {
        expect($r['reason'])->toBe($f['expected']['reason']);

        return;
    }
    foreach ($f['expected']['oracle'] as $key => $value) {
        if ($key === 'daily_memory') {
            foreach ($value as $i => $day) {
                foreach ($day as $field => $amount) {
                    expect($r['daily_memory'][$i][$field])->toBe($amount);
                }
            }
        } else {
            expect($r[$key])->toBe($value);
        }
    }
    expect($r['memory_days'])->toBe(30)->and($r['daily_rates_applied'])->toBe(21)
        ->and($r['normative_method_homologated'])->toBeFalse()->and($r['sale_gain_tax_treatment_performed'])->toBeFalse()
        ->and($r['financial_base_integration_performed'])->toBeFalse()->and($r['xml_projection_performed'])->toBeFalse();
})->with(function (): array {
    return array_map(static fn (string $p): string => basename($p, '.json'),
        glob(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dere-daily-memory-2026.10.06-26/*.json'));
});

it('binds the actual daily series, unit and knowledge time before performing any memory calculation', function (): void {
    $f = dailyDebtMemoryFixture('constant');
    $calc = new FinancialDebtDailyMemoryCalculator;
    $series = $f['rule']['series'];
    $series['rows'][0]['daily_percent'] = '0.01';
    expect($calc->calculate($f['input'], $series, $f['known'], $f['rule'])['reason'])->toBe('RTC_DERE_D2101_DAILY_OFFICIAL_SERIES_UNPROVEN');
    $series = $f['rule']['series'];
    $series['unit'] = 'PERCENT_PER_YEAR';
    expect($calc->calculate($f['input'], $series, $f['known'], $f['rule'])['reason'])->toBe('RTC_DERE_D2101_DAILY_OFFICIAL_SERIES_UNPROVEN');
    expect($calc->calculate($f['input'], $f['rule']['series'], new DateTimeImmutable('2026-09-30'), $f['rule'])['reason'])->toBe('RTC_DERE_D2101_DAILY_RULE_UNAVAILABLE');
});

it('retains already earned Selic through a total exit and distinguishes temporal conventions and accounting from acquisition cost', function (): void {
    $calc = new FinancialDebtDailyMemoryCalculator;
    $run = static function (string $case) use ($calc): array {
        $f = dailyDebtMemoryFixture($case);

        return $calc->calculate($f['input'], $f['rule']['series'], $f['known'], $f['rule']);
    };
    $exit = $run('complete-sale');
    expect($exit['closing_quantity'])->toBe('0.00000000')->and($exit['monthly']['vSaldoContFin'])->toBe('0.00')
        ->and((float) $exit['monthly']['vSelic'])->toBeGreaterThan(0)->and($exit['realized_sale_gain'])->toBe('-100.00');
    $before = $run('first-purchase-before-rate');
    $after = $run('first-purchase-after-rate');
    expect((float) $before['monthly']['vSelic'])->toBeGreaterThan((float) $after['monthly']['vSelic']);
    $different = $run('different-book-and-acquisition-cost');
    expect($different['closing_acquisition_cost'])->toBe('10000.00000000')->and($different['monthly']['vSaldoContFin'])->toBe('10250.00');
});
