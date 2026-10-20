<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

it('rejects unproven historical policy selection while retaining native replay validation', function (string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $path): array => json_decode(file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
    $catalog = $read('catalogs/rtc-codes-2026.10.09-28-draft46.json');
    $fixture = $read('scenarios/tvm-shared-asset-current-2026.10.09-28-draft43/positive-partial-current.json');
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
    $history = $fixture['recognition_history'];
    $known = new DateTimeImmutable($rule['published_at']);
    $scope = ['supplier_id' => $fixture['position']['supplier_id'], 'period' => $fixture['position']['period'], 'title' => $fixture['title'], 'opening' => $fixture['position']['opening']];
    $set = [$history['fiscal_sources'][0]['snapshot']['knowledge']['content_hashes']['bundle_manifest']];
    $key = CanonicalJson::hash($set);
    $selection = &$rule['source_policy_selection'];
    $profile = &$selection['profiles'][$key];
    switch ($mutation) {
        case 'future': $selection['published_at'] = $known->modify('+1 second')->format(DATE_ATOM);
            break;
        case 'strategy': $selection['strategy'] = 'CALLER_SELECTED';
            break;
        case 'profile-hash': $profile['policy_hash'] = str_repeat('0', 64);
            break;
        case 'recursive': $profile['policy']['source_policy_selection'] = ['strategy' => 'BY_EXACT_REPLAYED_MANIFEST_SET'];
            $profile['policy_hash'] = CanonicalJson::hash($profile['policy']);
            break;
        case 'permission': $profile['policy']['permitted_bundle_manifest_hashes'] = [];
            $profile['policy_hash'] = CanonicalJson::hash($profile['policy']);
            break;
        case 'unknown-set': unset($selection['profiles'][$key]);
            break;
        case 'closing': $history['closing_memory']['policy_hash'] = str_repeat('0', 64);
            break;
        case 'snapshot': $history['fiscal_sources'][0]['snapshot']['result']['status'] = 'FABRICATED';
            break;
        case 'integrity': $selection['source_hash'] = str_repeat('0', 64);
            break;
    }
    unset($profile);
    if ($mutation !== 'integrity') {
        $selection['source_hash'] = CanonicalJson::hash(array_diff_key($selection, ['source_hash' => true]));
    }
    unset($selection);
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $history, $known, $rule);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_TVM_HISTORY_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['future', 'SOURCE_POLICY_SELECTION_UNAVAILABLE'],
    ['strategy', 'SOURCE_POLICY_SELECTION_UNAVAILABLE'],
    ['profile-hash', 'SOURCE_POLICY_PROFILE_UNPROVEN'],
    ['recursive', 'SOURCE_POLICY_PROFILE_UNPROVEN'],
    ['permission', 'SOURCE_POLICY_PROFILE_PERMISSION_CONFLICT'],
    ['unknown-set', 'SOURCE_POLICY_PROFILE_UNPROVEN'],
    ['closing', 'CLOSING_MEMORY_SCOPE_CONFLICT'],
    ['snapshot', 'FISCAL_SNAPSHOT_REPLAY_MISMATCH'],
    ['integrity', 'SOURCE_POLICY_SELECTION_UNAVAILABLE'],
]);
