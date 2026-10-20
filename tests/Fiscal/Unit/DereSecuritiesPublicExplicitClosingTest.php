<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmGrossRoundingFixtures.php';
require_once __DIR__.'/../Support/TvmPublicDeferralFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function explicitClosingSave(string $name, array $data): void
{
    if (getenv('TVM_EXPLICIT_CLOSING_CAPTURE') !== '1') {
        return;
    }
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-explicit-closing-2026.10.08-28-draft32/'.$name.'.json';
    if (is_file($path)) {
        expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));

        return;
    }
    $stream = fopen($path, 'x');
    expect($stream)->not->toBeFalse();
    fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    fclose($stream);
}

it('captures and projects actual August first-acquisition mixed-sign closing sources', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-public-explicit-closing-2026.10.08-28-draft32/prior-'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmGrossRoundingFixture('prior-'.$case, $fixture, '2026.10.08-28-draft31');
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])
        ->and($projected['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base']);
    $scenario = json_decode(file_get_contents($root.'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'SYNTHETIC-public-explicit-closing-prior-'.$case;
    $scenario['knowledge_at'] = $known->format(DATE_ATOM);
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $scenario['competence_date'] = '2026-08-31';
    $scenario['facts']['rtc.operation_date'] = '2026-08-31';
    $scenario['facts']['rtc.financial_ledger'] = [$ledger];
    explicitClosingSave('prior-'.$case.'-financial', $scenario);
    explicitClosingSave('prior-'.$case.'-month', $month);
})->with(['negative', 'positive', 'zero', 'exact']);

it('transports explicitly proved prior net fiscal closing while retaining economic curve and rounding separately', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-public-explicit-closing-2026.10.08-28-draft32/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft32');
    $parent = $operation['base']['realization_bindings']['tvm1'];
    $history = $parent['analysis']['replayed_fiscal_history'];
    expect($history['public_explicit_net_closing_replayed'])->toBeTrue()
        ->and($history['opening_fiscal_components'])->toBe($fixture['expected']['opening_fiscal_components'])
        ->and($history['opening_carrying_components'])->toBe($fixture['expected']['opening_carrying_components'])
        ->and($parent['financial_entries'])->toBe([])->and($parent['deductions'])->toBe('0.00');
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])
        ->and($projected['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])
        ->and($operation['base']['net_base'])->toBe($fixture['expected']['base'])
        ->and($projected['production_ready'])->toBeFalse();
    $scenario = json_decode(file_get_contents($root.'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'SYNTHETIC-public-explicit-closing-'.$case;
    $scenario['knowledge_at'] = $known->format(DATE_ATOM);
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $scenario['facts']['rtc.financial_ledger'] = [$ledger];
    explicitClosingSave($case.'-financial', $scenario);
    explicitClosingSave($case.'-month', $month);
})->with(['negative-partial', 'negative-complete', 'positive-partial', 'positive-complete', 'zero-partial', 'zero-complete', 'exact-partial', 'exact-complete']);

it('rejects unproved or altered explicit closing allocations and double retention', function (string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-public-explicit-closing-2026.10.08-28-draft32/positive-partial-current.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-2026.10.08-28-draft32.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
    $history = $fixture['recognition_history'];
    switch ($mutation) {
        case 'missing': unset($history['fiscal_sources'][0]['net_closing_evidence']);
            break;
        case 'hash': $history['fiscal_sources'][0]['net_closing_evidence']['source_hash'] = str_repeat('0', 64);
            break;
        case 'twice': $history['fiscal_sources'][0]['retained_share'] = '0.5';
            break;
        case 'snapshot': $history['fiscal_sources'][0]['snapshot']['result']['decision_hash'] = str_repeat('0', 64);
            break;
        case 'policy': unset($rule['public_explicit_net_closing_policy']);
            break;
        case 'rounding':
        case 'origin':
        case 'normative':
            $proof = &$history['fiscal_sources'][0]['net_closing_evidence'];
            if ($mutation === 'rounding') {
                $proof['value']['closing_carrying_components']['rounding_bridge'] = '0.00000000';
            } elseif ($mutation === 'origin') {
                $proof['value']['fiscal_origin'] = 'D1101';
            } else {
                $proof['value']['normative_homologation_claimed'] = true;
            }
            $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
            unset($proof);
            break;
    }
    $scope = ['supplier_id' => $fixture['position']['supplier_id'], 'period' => '2026-09', 'title' => $fixture['title'], 'opening' => $fixture['position']['opening']];
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $history, new DateTimeImmutable($rule['published_at']), $rule);
    expect($result['reason'])->toBe('RTC_TVM_HISTORY_'.$reason);
})->with([
    ['missing', 'PUBLIC_EXPLICIT_NET_CLOSING_UNPROVEN'], ['hash', 'PUBLIC_EXPLICIT_NET_CLOSING_UNPROVEN'],
    ['rounding', 'PUBLIC_EXPLICIT_NET_CLOSING_UNPROVEN'], ['origin', 'PUBLIC_EXPLICIT_NET_CLOSING_UNPROVEN'], ['normative', 'PUBLIC_EXPLICIT_NET_CLOSING_UNPROVEN'],
    ['twice', 'PUBLIC_REALIZED_CLOSING_RETAINED_TWICE'], ['snapshot', 'FISCAL_SNAPSHOT_REPLAY_MISMATCH'], ['policy', 'PUBLIC_EXPLICIT_NET_CLOSING_POLICY_UNPROVEN'],
]);

it('rejects fiscal overconsumption and economically equal but altered opening decomposition', function (string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-public-explicit-closing-2026.10.08-28-draft32/negative-partial-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [, , $known, , $ledger] = tvmPublicDeferralFixture('negative-partial', $fixture, '2026.10.08-28-draft32');
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    if ($mutation === 'opening') {
        $input['recognition_inventory']['opening']['prior_produced_return'] = '-0.00400000';
        $input['recognition_inventory']['opening']['rounding_bridge'] = '-0.00600000';
        $proof = &$input['inventory_evidence'];
        $proof['value']['inventory'] = $input['recognition_inventory'];
        $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
        unset($proof);
    } else {
        $sale = &$input['realizations'][0];
        $sale['history']['previously_assessed_produced_return'] = '0.00100000';
        $sale['history']['previously_unoffset_public_title_loss'] = '-0.00600000';
        $sale['history_evidence']['value']['history'] = $sale['history'];
        $sale['history_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['history_evidence'], ['source_hash' => true]));
        $sale['component_evidence']['value']['history_evidence_hash'] = $sale['history_evidence']['source_hash'];
        $sale['component_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['component_evidence'], ['source_hash' => true]));
        unset($sale);
    }
    $proof = &$input['complete_evidence'];
    $proof['value']['realizations'] = $input['realizations'];
    $proof['value']['recognition_inventory'] = $input['recognition_inventory'];
    $proof['value']['inventory_evidence_hash'] = $input['inventory_evidence']['source_hash'];
    $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
    unset($proof);
    $catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-2026.10.08-28-draft32.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $catalog['financial_operation']['base']['securities_realization']['analysis'], $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason);
})->with([['opening', 'OPENING_EXPLICIT_CARRYING_COMPONENT_CONFLICT'], ['fiscal', 'HISTORICAL_FISCAL_COMPONENT_REUSED_OR_UNAVAILABLE']]);
