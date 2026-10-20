<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;

it('reproduces positive and negative July positions with the independently calculated official daily benchmark', function (string $case, string $base): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-public-chain-preparation-20261008/july-'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $policy = json_decode(file_get_contents($root.'catalogs/tvm-public-chain-july-policy-draft-20261008.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new FinancialDebtDailyMemoryCalculator)->calculate($fixture['position'], $policy['series'], new DateTimeImmutable($policy['published_at']), $policy);
    expect($result['status'])->toBe('RESOLVED', $result['reason'])
        ->and($result['daily_rates_applied'])->toBe(23)
        ->and($result['monthly']['vSelic'])->toBe('121.52')
        ->and($result['monthly']['vApur'])->toBe($base);
    foreach ($fixture['independent_oracle'] as $key => $value) {
        if ($key !== 'daily_memory') {
            expect($result[$key])->toBe($value);
        } else {
            foreach ($value as $index => $day) {
                foreach ($day as $field => $amount) {
                    expect($result['daily_memory'][$index][$field])->toBe($amount);
                }
            }
        }
    }
})->with([['positive', '100.00'], ['negative', '0.00']]);
