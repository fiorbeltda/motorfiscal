<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

it('requires the signed private policy and authentic scope for negative history', function (string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3);
    $index = json_decode(file_get_contents($root.'/storage/app/phase4b-private-consolidation48-draft-20261009-28/private-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    $metadata = array_values(array_filter($index['cases'], static fn (array $row): bool => $row['case'] === 'negative-history'))[0];
    $scenario = json_decode(file_get_contents($root.'/'.$metadata['financial_input']), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.09-28-draft48.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
    $parent = array_values(array_filter($scenario['facts']['rtc.financial_ledger'][0]['entries'], static fn (array $row): bool => $row['kind'] === 'QUALIFIED_SECURITIES_REALIZATION'))[0];
    $input = $parent['securities_realization']['analysis_input'];
    $scope = ['supplier_id' => $input['position']['supplier_id'], 'period' => $input['position']['period'], 'title' => $input['title'], 'opening' => $input['position']['opening']];
    $history = $input['recognition_history'];
    $key = CanonicalJson::hash($metadata['historical_source_manifest_hashes']);
    if ($mutation === 'unsigned-policy') {
        unset($rule['source_policy_selection']['profiles'][$key]['policy']['signed_private_deferral_policy']);
        $profile = &$rule['source_policy_selection']['profiles'][$key];
        $profile['policy_hash'] = CanonicalJson::hash($profile['policy']);
        unset($profile);
        $rule['source_policy_selection']['source_hash'] = CanonicalJson::hash(array_diff_key($rule['source_policy_selection'], ['source_hash' => true]));
    } elseif ($mutation === 'supplier') {
        $scope['supplier_id'] = '22222222000191';
    } elseif ($mutation === 'account') {
        $scope['title']['cCta'] = 'OTHER000';
    } elseif ($mutation === 'not-permitted') {
        $rule['permitted_bundle_manifest_hashes'] = [];
    }
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $history, new DateTimeImmutable($rule['published_at']), $rule);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_TVM_HISTORY_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['unsigned-policy', 'SIGNED_PRIVATE_DEFERRAL_POLICY_UNPROVEN'],
    ['supplier', 'CLOSING_MEMORY_SCOPE_CONFLICT'],
    ['account', 'CLOSING_MEMORY_SCOPE_CONFLICT'],
    ['not-permitted', 'FISCAL_KNOWLEDGE_NOT_PERMITTED'],
]);
