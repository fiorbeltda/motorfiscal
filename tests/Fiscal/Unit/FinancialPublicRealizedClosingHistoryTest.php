<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

function publicRealizedClosingHistoryFixture(string $case): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-public-realized-closing-preparation-20261008/'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $policy = json_decode(file_get_contents($root.'catalogs/tvm-public-realized-closing-policy-draft-20261008.json'), true, 512, JSON_THROW_ON_ERROR);

    return [$fixture, $policy, new DateTimeImmutable($policy['published_at'])];
}

it('carries actual prior realization closing pools plus only unreleased own-month public yield', function (string $case): void {
    [$fixture, $policy, $known] = publicRealizedClosingHistoryFixture($case);
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($fixture['scope'], $fixture['recognition_history'], $known, $policy);
    expect($result['status'])->toBe('RESOLVED', $result['reason'])
        ->and($result['public_realized_closing_replayed'])->toBeTrue()
        ->and($result['opening_fiscal_components'])->toBe($fixture['expected_components'])
        ->and($result['historical_public_loss_current_deduction_created'])->toBeFalse()
        ->and($result['historical_public_loss_cross_title_offset_created'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse()
        ->and($fixture['current_month_tax_calculated'])->toBeFalse();
})->with(['negative-partial', 'negative-complete', 'positive-partial', 'zero-partial', 'fund-partial']);

it('rejects ordinary gross substitution duplicate closing use and forged actual realization decisions', function (string $mutation, string $reason): void {
    [$fixture, $policy, $known] = publicRealizedClosingHistoryFixture('negative-partial');
    $history = $fixture['recognition_history'];
    if ($mutation === 'policy') {
        unset($policy['public_realized_closing_policy']);
    } elseif ($mutation === 'duplicate') {
        $history['fiscal_sources'][] = $history['fiscal_sources'][0];
    } elseif ($mutation === 'share') {
        $history['fiscal_sources'][0]['retained_share'] = '0.6';
    } elseif ($mutation === 'forged') {
        $history['fiscal_sources'][0]['snapshot']['result']['decision'] = ['forged' => true];
    } elseif ($mutation === 'closing') {
        $position = &$history['closing_memory']['position'];
        $position['vPisCofins'] = '6.00';
        $position['declared_monthly']['vPisCofins'] = '6.00';
        $position['declared_monthly']['vApur'] = '44.00';
        $position['memory_evidence']['value'] = array_diff_key($position, ['memory_evidence' => true]);
        $position['memory_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($position['memory_evidence'], ['source_hash' => true]));
        $daily = $policy['daily_memory_policies']['2026-09'];
        $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $daily['series'], new DateTimeImmutable($history['closing_memory']['known_at']), $daily);
        expect($memory['status'])->toBe('RESOLVED', $memory['reason']);
        $history['closing_memory']['result'] = $memory;
        unset($position);
    }
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($fixture['scope'], $history, $known, $policy);
    expect($result['reason'])->toBe('RTC_TVM_HISTORY_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['policy', 'CLOSING_CARRYING_COMPONENT_CONFLICT'],
    ['duplicate', 'PUBLIC_REALIZED_CLOSING_POLICY_CONFLICT'],
    ['share', 'PUBLIC_REALIZED_CLOSING_RETAINED_TWICE'],
    ['forged', 'FISCAL_SNAPSHOT_REPLAY_MISMATCH'],
    ['closing', 'PUBLIC_REALIZED_CLOSING_COMPONENTS_UNPROVEN'],
]);
