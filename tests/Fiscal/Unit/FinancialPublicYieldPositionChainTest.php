<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicDeferralFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

it('binds retained public yield history to the actual replayed closing position', function (string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-public-negative-yield-2026.10.08-28-draft18/partial-current.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-2026.10.08-28-draft18.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
    $rule['public_position_chain_policy'] = 'REPLAYED_CONSECUTIVE_PUBLIC_YIELD_POSITION_CHAIN';
    $known = new DateTimeImmutable($rule['published_at']);
    $history = $fixture['recognition_history'];
    $scope = ['supplier_id' => $fixture['position']['supplier_id'], 'period' => '2026-09', 'title' => $fixture['title'], 'opening' => $fixture['position']['opening']];
    if ($mutation === 'closing') {
        // This closing memory is independently valid and has the same carrying balance,
        // but its PIS calculation differs from the actually replayed source.
        $position = &$history['closing_memory']['position'];
        $position['vPisCofins'] = '1.00';
        $position['declared_monthly']['vPisCofins'] = '1.00';
        $position['memory_evidence']['value'] = array_diff_key($position, ['memory_evidence' => true]);
        $position['memory_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($position['memory_evidence'], ['source_hash' => true]));
        $policy = $rule['daily_memory_policies']['2026-08'];
        $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], new DateTimeImmutable($history['closing_memory']['known_at']), $policy);
        expect($memory['status'])->toBe('RESOLVED');
        $history['closing_memory']['result'] = $memory;
        unset($position);
    } elseif ($mutation === 'share') {
        $history['fiscal_sources'][0]['retained_share'] = '0.5';
    } elseif ($mutation === 'policy') {
        unset($rule['public_negative_yield_policy']);
    }
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $history, $known, $rule);
    expect($result['reason'])->toBe('RTC_TVM_HISTORY_'.$reason)->and($result['production_ready'])->toBeFalse();
    if ($mutation === 'none') {
        expect($result['public_position_chain_replayed'])->toBeTrue()
            ->and($result['opening_fiscal_components']['unoffset_public_title_loss'])->toBe('-150.00000000');
    }
})->with([
    ['none', 'REPLAY_QUALIFIED'],
    ['closing', 'PUBLIC_POSITION_CHAIN_CONTINUITY_UNPROVEN'],
    ['share', 'PUBLIC_POSITION_CHAIN_SOURCE_CONFLICT'],
    ['policy', 'PUBLIC_POSITION_CHAIN_POLICY_CONFLICT'],
]);
