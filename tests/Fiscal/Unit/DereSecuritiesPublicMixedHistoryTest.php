<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicDeferralFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function publicMixedHistoryFixture(string $case): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-mixed-history-2026.10.08-28-draft21/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('retains positive and negative public history independently even when their net sign differs or is zero', function (string $case): void {
    $fixture = publicMixedHistoryFixture($case);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft21');
    $parent = $operation['base']['realization_bindings']['tvm1'];
    $history = $parent['analysis']['replayed_fiscal_history'];
    expect($history['opening_fiscal_components'])->toBe($fixture['expected']['opening_fiscal_components'])
        ->and($history['public_position_chain_replayed'])->toBeTrue()
        ->and($history['fiscal_snapshots_replayed'])->toBe(2)
        ->and($history['historical_public_loss_current_deduction_created'])->toBeFalse()
        ->and($history['historical_public_loss_cross_title_offset_created'])->toBeFalse()
        ->and($parent['financial_entries'])->toBe([])
        ->and($parent['revenue'])->toBe('0.00')->and($parent['deductions'])->toBe('0.00')
        ->and($operation['base']['net_base'])->toBe($fixture['expected']['base']);
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])
        ->and($projected['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])
        ->and($projected['production_ready'])->toBeFalse();
    if (getenv('TVM_PUBLIC_MIXED_HISTORY_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-public-mixed-history-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-public-mixed-history-2026.10.08-28-draft21/'.$case.'-'.$suffix.'.json';
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
})->with(['negative-partial', 'negative-complete', 'positive-partial', 'zero-partial', 'fund-partial']);

it('requires a complete consecutive replayed public source chain and canonical independent assignments', function (string $mutation, string $reason): void {
    $fixture = publicMixedHistoryFixture('negative-partial');
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft21.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
    $history = $fixture['recognition_history'];
    if ($mutation === 'missing-first') {
        array_shift($history['fiscal_sources']);
    } elseif ($mutation === 'duplicate') {
        $history['fiscal_sources'][] = $history['fiscal_sources'][0];
    } elseif ($mutation === 'share') {
        $history['fiscal_sources'][0]['retained_share'] = '0.5';
    } elseif ($mutation === 'reverse') {
        $history['fiscal_sources'] = array_reverse($history['fiscal_sources']);
    }
    $scope = ['supplier_id' => $fixture['position']['supplier_id'], 'period' => '2026-09', 'title' => $fixture['title'], 'opening' => $fixture['position']['opening']];
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $history, new DateTimeImmutable($rule['published_at']), $rule);
    expect($result['reason'])->toBe('RTC_TVM_HISTORY_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['missing-first', 'PUBLIC_POSITION_CHAIN_CONTINUITY_UNPROVEN'],
    ['duplicate', 'DUPLICATE_FISCAL_SOURCE'],
    ['share', 'PUBLIC_POSITION_CHAIN_SOURCE_CONFLICT'],
    ['reverse', 'REPLAY_QUALIFIED'],
]);

it('refuses unavailable or wrongly signed historical allocations and requires actual chain qualification', function (string $mutation, string $reason): void {
    $fixture = publicMixedHistoryFixture('negative-partial');
    [, , $known, , $ledger] = tvmPublicDeferralFixture('negative-partial', $fixture, '2026.10.08-28-draft21');
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft21.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis'];
    if ($mutation === 'policy') {
        unset($rule['history_policy']['public_position_chain_policy']);
    } else {
        $sale = &$input['realizations'][0];
        if ($mutation === 'overdraw') {
            $sale['history']['previously_assessed_produced_return'] = '200.00000000';
            $sale['history']['previously_unoffset_public_title_loss'] = '-260.00000000';
        } else {
            $sale['history']['previously_assessed_produced_return'] = '-20.00000000';
            $sale['history']['previously_unoffset_public_title_loss'] = '-40.00000000';
        }
        $sale['history_evidence']['value']['history'] = $sale['history'];
        $sale['history_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['history_evidence'], ['source_hash' => true]));
        $sale['component_evidence']['value']['history_evidence_hash'] = $sale['history_evidence']['source_hash'];
        $sale['component_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['component_evidence'], ['source_hash' => true]));
        unset($sale);
        $input['complete_evidence']['value']['realizations'] = $input['realizations'];
        $input['complete_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['complete_evidence'], ['source_hash' => true]));
    }
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $rule, $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason);
})->with([
    ['policy', 'PRIOR_ASSESSED_AND_EXCLUDED_RETURN_CONFLICT'],
    ['negative-assessed', 'PRIOR_ASSESSED_AND_EXCLUDED_RETURN_CONFLICT'],
    ['overdraw', 'HISTORICAL_FISCAL_COMPONENT_REUSED_OR_UNAVAILABLE'],
]);
