<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

function publicDeferralHistoryFixture(string $case = 'gain'): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-deferral-2026.10.08-28-draft15/'.$case.'-history.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('replays public produced yield and signed deferred fair value with a separate full carrying memory', function (string $case): void {
    $f = publicDeferralHistoryFixture($case);
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($f['scope'], $f['history'], new DateTimeImmutable($f['known_at']), $f['rule']);
    expect($result['status'])->toBe('RESOLVED', $result['reason'])->and($result['opening_fiscal_components'])->toBe($f['expected'])
        ->and($result['fiscal_snapshots_replayed'])->toBe(1)->and($result['closing_memory_replayed'])->toBeTrue()
        ->and($result['production_ready'])->toBeFalse()->and($result['posting_performed'])->toBeFalse();
    $yield = array_values(array_filter($result['sources'], static fn (array $s): bool => $s['entry_id'] === 'yield1'))[0];
    expect($yield['retained']['assessed_produced_return'])->toBe('99.31000000')
        ->and($yield['retained']['excluded_art195_return'])->toBe('40.69000000')
        ->and($yield['retained']['removed_pis_cofins'])->toBe('10.00000000')
        ->and($yield['retained']['deferred_fair_value'])->toBe('0.00000000');
    if (getenv('TVM_PUBLIC_DEFERRAL_CAPTURE') === '1') {
        $path = dirname(__DIR__, 3).'/storage/app/phase4b-realization-public-deferral-draft-20261008-28/'.$case.'-history-result.json';
        if (is_file($path)) {
            expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($result));
        } else {
            $stream = fopen($path, 'x');
            expect($stream)->not->toBeFalse();
            fwrite($stream, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
            fclose($stream);
        }
    }
})->with(['gain', 'loss', 'fund-gain', 'fund-loss']);

it('refuses a public deferral without its opt-in actual yield source and independent daily split', function (string $mutation): void {
    $f = publicDeferralHistoryFixture('loss');
    $source = &$f['history']['fiscal_sources'][1];
    switch ($mutation) {
        case 'policy': unset($f['rule']['public_deferral_policy']);
            break;
        case 'missing-bridge': unset($source['public_deferral_bridge']);
            break;
        case 'wrong-yield': $source['public_deferral_bridge']['yield_entry_id'] = 'unknown';
            break;
        case 'missing-yield-source': array_shift($f['history']['fiscal_sources']);
            break;
        case 'unqualified-proof': $source['public_deferral_bridge']['independent_evidence']['qualified'] = false;
            break;
        case 'bound-memory': $source['public_deferral_bridge']['independent_evidence']['value']['closing_memory_hash'] = str_repeat('a', 64);
            $source['public_deferral_bridge']['independent_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($source['public_deferral_bridge']['independent_evidence'], ['source_hash' => true]));
            break;
        case 'retained': $source['retained_share'] = '0.5';
            break;
        case 'daily-date':
            $closing = &$f['history']['closing_memory'];
            $position = &$closing['position'];
            foreach ($position['contract_curve'] as &$day) {
                if ($day['date'] === '2026-08-30') {
                    $day['appropriation'] = '-200';
                    $day['claimed_book_balance'] = '9800.00000000';
                } elseif ($day['date'] === '2026-08-31') {
                    $day['appropriation'] = '150';
                }
            }
            unset($day);
            $position['memory_evidence']['value'] = array_diff_key($position, ['memory_evidence' => true]);
            $position['memory_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($position['memory_evidence'], ['source_hash' => true]));
            $policy = $f['rule']['daily_memory_policies']['2026-08'];
            $closing['result'] = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], new DateTimeImmutable($closing['known_at']), $policy);
            expect($closing['result']['status'])->toBe('RESOLVED');
            break;
    }
    unset($source);
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($f['scope'], $f['history'], new DateTimeImmutable($f['known_at']), $f['rule']);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_TVM_HISTORY_PUBLIC_DEFERRAL_BRIDGE_UNPROVEN')
        ->and($result['production_ready'])->toBeFalse();
})->with(['policy', 'missing-bridge', 'wrong-yield', 'missing-yield-source', 'unqualified-proof', 'bound-memory', 'retained', 'daily-date']);
