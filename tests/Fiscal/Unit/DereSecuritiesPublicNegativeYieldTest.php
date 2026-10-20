<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicDeferralFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function publicNegativeYieldFixture(string $case): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-negative-yield-2026.10.08-28-draft18/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('retains prior public title loss without making it a current deduction or positive art195 exclusion', function (string $case): void {
    $fixture = publicNegativeYieldFixture($case);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft18');
    $parent = $operation['base']['realization_bindings']['tvm1'];
    $history = $parent['analysis']['replayed_fiscal_history'];
    expect($history['opening_fiscal_components']['unoffset_public_title_loss'])->toBe('-150.00000000')
        ->and($history['opening_fiscal_components']['assessed_produced_return'])->toBe('0.00000000')
        ->and($history['opening_fiscal_components']['excluded_art195_return'])->toBe('0.00000000')
        ->and($history['historical_public_loss_current_deduction_created'])->toBeFalse()
        ->and($history['historical_public_loss_cross_title_offset_created'])->toBeFalse()
        ->and($operation['base']['net_base'])->toBe($fixture['expected']['base'])->and($parent['deductions'])->toBe('0.00')
        ->and($parent['financial_entries'])->toBe([]);
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])->and($projected['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])
        ->and($projected['production_ready'])->toBeFalse();
    if (getenv('TVM_PUBLIC_NEGATIVE_YIELD_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-public-negative-yield-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-public-negative-yield-2026.10.08-28-draft18/'.$case.'-'.$suffix.'.json';
            if (is_file($path)) {
                expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));
            } else {
                $stream = fopen($path, 'x');
                expect($stream)->not->toBeFalse();
                fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                fclose($stream);
            }
        }
    }
})->with(['partial', 'complete', 'exclusive-fund']);

it('requires a permitted actually replayed negative public source without duplicating its loss', function (string $mutation, string $reason): void {
    $f = publicNegativeYieldFixture('partial');
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft18.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
    $history = $f['recognition_history'];
    switch ($mutation) {
        case 'policy': unset($rule['public_negative_yield_policy']);
            break;
        case 'duplicate': $history['fiscal_sources'][] = $history['fiscal_sources'][0];
            break;
        case 'result': $history['fiscal_sources'][0]['snapshot']['result']['decision'] = ['forged' => true];
            break;
    }
    $scope = ['supplier_id' => $f['position']['supplier_id'], 'period' => '2026-09', 'title' => $f['title'], 'opening' => $f['position']['opening']];
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $history, new DateTimeImmutable($catalog['financial_operation']['base']['securities_realization']['published_at']), $rule);
    expect($result['reason'])->toBe('RTC_TVM_HISTORY_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['policy', 'PUBLIC_TITLE_OR_NEGATIVE_RECOGNITION_UNPROVEN'], ['duplicate', 'DUPLICATE_FISCAL_SOURCE'], ['result', 'FISCAL_SNAPSHOT_REPLAY_MISMATCH'],
]);

it('refuses to relabel a replayed unoffset public loss as previously assessed or excluded income', function (string $field): void {
    $f = publicNegativeYieldFixture('partial');
    [, , $known, , $ledger] = tvmPublicDeferralFixture('partial', $f, '2026.10.08-28-draft18');
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $sale = &$input['realizations'][0];
    $sale['history'][$field] = '-60.00000000';
    $sale['history']['previously_unoffset_public_title_loss'] = '0.00000000';
    $sale['history_evidence']['value']['history'] = $sale['history'];
    $sale['history_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['history_evidence'], ['source_hash' => true]));
    $sale['component_evidence']['value']['history_evidence_hash'] = $sale['history_evidence']['source_hash'];
    $sale['component_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['component_evidence'], ['source_hash' => true]));
    unset($sale);
    $input['complete_evidence']['value']['realizations'] = $input['realizations'];
    $input['complete_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['complete_evidence'], ['source_hash' => true]));
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft18.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $catalog['financial_operation']['base']['securities_realization']['analysis'], $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_HISTORICAL_FISCAL_COMPONENT_REUSED_OR_UNAVAILABLE');
})->with(['previously_assessed_produced_return', 'previously_excluded_art195_return']);
