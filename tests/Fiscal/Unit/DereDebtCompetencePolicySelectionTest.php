<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

function competenceSelectionFixture(string $period): array
{
    $root = dirname(__DIR__, 3);
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.09-28-draft44.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($root.'/resources/fiscal/bundles/consumption-phase4b-2026.10.09-28-draft44.json'), true, 512, JSON_THROW_ON_ERROR);
    $path = match ($period) {
        '2026-07' => 'storage/app/phase4b-realization-shared-asset-sources-draft-20261009-28/positive-source-snapshot.json',
        '2026-08' => 'resources/fiscal/scenarios/tvm-shared-asset-current-2026.10.09-28-draft43/positive-partial-current.json',
        default => 'resources/fiscal/scenarios/tvm-third-sale-integrated-2026.10.08-28-draft38/positive-partial-complete-current.json',
    };
    $data = json_decode(file_get_contents($root.'/'.$path), true, 512, JSON_THROW_ON_ERROR);
    $position = $period === '2026-07' ? $data['input']['facts']['rtc.financial_ledger'][0]['entries'][0]['public_offer_debt']['position'] : $data['position'];

    return [$position, $catalog['dere_debt_daily_memory_calculation'], new DateTimeImmutable($manifest['knowledge_available_at'])];
}

it('reproduces the exact native period memory using the frozen official competence policy', function (string $period): void {
    [$position, $rule, $known] = competenceSelectionFixture($period);
    $selected = $rule['competence_policy_selection']['policies'][$period];
    $calculator = new FinancialDebtDailyMemoryCalculator;
    $expected = $calculator->calculate($position, $selected['series'], $known, $selected);
    $actual = $calculator->calculate($position, $rule['series'], $known, $rule);
    expect($expected['status'])->toBe('RESOLVED', $expected['reason'])->and($actual)->toBe($expected)
        ->and($actual['official_data_sha256'])->toBe($selected['official_data_sha256'])
        ->and($actual['period'])->toBe($period);
    expect($calculator->calculate($position, $selected['series'], $known, $rule))->toBe($expected);
})->with(['2026-07', '2026-08', '2026-09']);

it('rejects missing future or conflicting competence knowledge without substituting rate data', function (string $mutation, string $reason): void {
    [$position, $rule, $known] = competenceSelectionFixture('2026-08');
    $series = $rule['series'];
    $selection = &$rule['competence_policy_selection'];
    if ($mutation === 'missing') {
        unset($selection['policies']['2026-08']);
    } elseif ($mutation === 'future') {
        $selection['published_at'] = $known->modify('+1 day')->format(DATE_ATOM);
    } elseif ($mutation === 'invalid-date') {
        $selection['published_at'] = 'INVALID';
    } elseif ($mutation === 'strategy') {
        $selection['strategy'] = 'UNAVAILABLE';
    } elseif ($mutation === 'hash') {
        $selection['policy_hashes']['2026-08'] = str_repeat('a', 64);
    } elseif ($mutation === 'foreign-series') {
        $series['rows'][0]['daily_percent'] = '0.000001';
    } else {
        $policy = &$selection['policies']['2026-08'];
        if ($mutation === 'period') {
            $policy['series']['period'] = '2026-07';
        } elseif ($mutation === 'recursive') {
            $policy['competence_policy_selection'] = ['strategy' => 'RECURSIVE'];
        } elseif ($mutation === 'native-future') {
            $policy['published_at'] = $known->modify('+1 day')->format(DATE_ATOM);
        } else {
            $policy['series']['rows'][0]['daily_percent'] = '0.000001';
        }
        $selection['policy_hashes']['2026-08'] = CanonicalJson::hash($policy);
        unset($policy);
    }
    unset($selection);
    $result = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $series, $known, $rule);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($reason)
        ->and($result['production_ready'])->toBeFalse();
})->with([
    ['missing', 'RTC_DERE_D2101_DAILY_COMPETENCE_POLICY_NOT_CONFIGURED'],
    ['future', 'RTC_DERE_D2101_DAILY_COMPETENCE_POLICY_SELECTION_UNAVAILABLE'],
    ['invalid-date', 'RTC_DERE_D2101_DAILY_COMPETENCE_POLICY_SELECTION_UNAVAILABLE'],
    ['strategy', 'RTC_DERE_D2101_DAILY_COMPETENCE_POLICY_SELECTION_UNAVAILABLE'],
    ['hash', 'RTC_DERE_D2101_DAILY_COMPETENCE_POLICY_OR_HASH_CONFLICT'],
    ['foreign-series', 'RTC_DERE_D2101_DAILY_COMPETENCE_CALLER_SERIES_CONFLICT'],
    ['period', 'RTC_DERE_D2101_DAILY_COMPETENCE_POLICY_OR_HASH_CONFLICT'],
    ['recursive', 'RTC_DERE_D2101_DAILY_COMPETENCE_POLICY_OR_HASH_CONFLICT'],
    ['native-future', 'RTC_DERE_D2101_DAILY_RULE_UNAVAILABLE'],
    ['native-rates', 'RTC_DERE_D2101_DAILY_OFFICIAL_SERIES_UNPROVEN'],
]);
