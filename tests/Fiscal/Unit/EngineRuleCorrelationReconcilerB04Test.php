<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\EngineRuleCorrelationReconcilerB04;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

function b04Inputs(): array
{
    $root = dirname(__DIR__, 3);
    $active = json_decode((string) file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $b01 = json_decode((string) file_get_contents($root.'/resources/fiscal/catalogs/rtc-correlation-anexoviii-v1.02.00-b01.json'), true, 512, JSON_THROW_ON_ERROR);

    return [$active['engine_decision_rules'], ['1.01.00' => $active['correlations'], '1.02.00' => $b01['correlations']]];
}

it('reports every engine rule against both official editions without granting or removing authority', function (): void {
    [$rules, $editions] = b04Inputs();
    $before = CanonicalJson::hash(['rules' => $rules, 'editions' => $editions]);
    $result = (new EngineRuleCorrelationReconcilerB04)->reconcile($rules, $editions);
    $expected = ['CONVERGENT_LITERAL_ROW_EXISTS' => 4, 'DIVERGENT_NO_LITERAL_MATCH' => 26,
        'NOT_COMPARABLE_NO_NBS_PREFIX' => 12, 'NO_OFFICIAL_ROW_IN_PREFIX' => 57];
    expect($result['status'])->toBe('REPORTED')
        ->and($result['report']['rules'])->toBe(99)
        ->and($result['report']['totals'])->toBe(['1.01.00' => $expected, '1.02.00' => $expected])
        ->and($result['report_hash'])->toBe('293055a25b637effffb3a74ef06127537a1d3b2b6ef8e36c4f02860ea67fbe45')
        ->and($result['decision_authority'])->toBeFalse()->and($result['rules_changed'])->toBeFalse()
        ->and($result['correlations_changed'])->toBeFalse()->and($result['rule_confirmed_by_convergence'])->toBeFalse()
        ->and($result['rule_invalidated_by_divergence'])->toBeFalse()
        ->and(CanonicalJson::hash(['rules' => $rules, 'editions' => $editions]))->toBe($before);
});

it('is deterministic under rule and row permutation', function (): void {
    [$rules, $editions] = b04Inputs();
    $reference = (new EngineRuleCorrelationReconcilerB04)->reconcile($rules, $editions)['report_hash'];
    $shuffled = array_reverse($rules, true);
    $permuted = array_map(fn (array $rows): array => array_reverse($rows), $editions);
    expect((new EngineRuleCorrelationReconcilerB04)->reconcile($shuffled, $permuted)['report_hash'])->toBe($reference);
});

it('separates literal convergence from same-code variants that the correlation does not carry', function (): void {
    [$rules, $editions] = b04Inputs();
    $items = array_column((new EngineRuleCorrelationReconcilerB04)->reconcile($rules, $editions)['report']['items'], null, 'rule');
    $reduced = $items['RTC.CLASSIFICATION.ANNEX.080101.ACQUIRER_DOMICILE.REDUCED_60-1']['editions']['1.02.00'];
    $presence = $items['RTC.CLASSIFICATION.ANNEX.080101.PERSON_PRESENCE.STANDARD-1']['editions']['1.02.00'];
    expect($reduced)->toBe(['status' => 'DIVERGENT_NO_LITERAL_MATCH', 'rows_in_prefix' => 1, 'matching_rows' => 0, 'cclasstrib_only_rows' => 1, 'cindop_only_rows' => 0])
        ->and($presence)->toBe(['status' => 'DIVERGENT_NO_LITERAL_MATCH', 'rows_in_prefix' => 1, 'matching_rows' => 0, 'cclasstrib_only_rows' => 0, 'cindop_only_rows' => 1])
        ->and($items['RTC.CLASSIFICATION.SAAS.RESIDUAL.SANDBOX-1']['editions']['1.02.00']['status'])->toBe('NOT_COMPARABLE_NO_NBS_PREFIX');
});

it('refuses mixed rule kinds and correlation rows that claim decision authority', function (string $case, string $reason): void {
    [$rules, $editions] = b04Inputs();
    if ($case === 'official-as-rule') {
        $first = array_key_first($rules);
        $rules[$first]['kind'] = 'OFFICIAL_CORRELATION';
    } elseif ($case === 'authority') {
        $editions['1.02.00'][0]['decision_authority'] = true;
    } else {
        $editions['1.01.00'][3]['kind'] = 'ENGINE_DECISION_RULE';
    }
    $result = (new EngineRuleCorrelationReconcilerB04)->reconcile($rules, $editions);
    expect($result['status'])->toBe('INVALID')->and($result['reason'])->toBe($reason)
        ->and($result)->not->toHaveKey('report')->and($result['decision_authority'])->toBeFalse();
})->with([
    ['official-as-rule', 'B04_NON_ENGINE_RULE_IN_RULE_SET'],
    ['authority', 'B04_CORRELATION_AUTHORITY_NOT_PROVISIONAL'],
    ['rule-as-correlation', 'B04_CORRELATION_AUTHORITY_NOT_PROVISIONAL'],
]);

it('matches the generated review report artifact', function (): void {
    $root = dirname(__DIR__, 3);
    $path = $root.'/storage/app/phase4b-b04-development-20261103/b04-reconciliation-report.json';
    if (! is_file($path)) {
        $this->markTestSkipped('Report artifact not generated in this checkout.');
    }
    $artifact = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    [$rules, $editions] = b04Inputs();
    expect($artifact['report_hash'])->toBe((new EngineRuleCorrelationReconcilerB04)->reconcile($rules, $editions)['report_hash']);
});
