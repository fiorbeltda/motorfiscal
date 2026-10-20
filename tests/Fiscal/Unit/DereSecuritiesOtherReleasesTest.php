<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

require_once __DIR__.'/../Support/TvmFairFixtures.php';

function tvmOtherFixture(string $case): array
{
    $root = dirname(__DIR__, 3);
    $version = '2026.10.06-28-draft5';
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $fixture = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-other-releases-'.$version.'/'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);
    [$ledger] = tvmCurrentFixture();
    $detail = &$ledger['entries'][4]['securities_realization'];
    $input = &$detail['analysis_input'];
    $position = $fixture['position'];
    $policy = $catalog['financial_operation']['base']['securities_realization']['analysis']['daily_memory_calculation'];
    $known = new DateTimeImmutable($catalog['financial_operation']['base']['securities_realization']['published_at']);
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], $known, $policy);
    expect($memory['status'])->toBe('RESOLVED');
    foreach ($fixture['independent_oracle'] as $key => $value) {
        if ($key === 'daily_memory') {
            foreach ($value as $index => $day) {
                expect(CanonicalJson::encode(array_intersect_key($memory[$key][$index], $day)))->toBe(CanonicalJson::encode($day));
            }
        } else {
            expect($memory[$key])->toBe($value);
        }
    }
    $input['position'] = $position;
    $input['recognition_inventory'] = $fixture['recognition_inventory'];
    $common = ['supplier_id' => $ledger['supplier_id'], 'period' => $position['period']];
    $sale = &$input['realizations'][0];
    $sale['components'] = $fixture['sale_components'];
    $sale['history']['previously_recognized_produced_return'] = $sale['history']['previously_assessed_produced_return'] = $sale['components']['prior_produced_return'];
    $sale['history']['deferred_fair_value_released'] = $sale['components']['deferred_fair_value_realized'];
    $events = [];
    foreach ($memory['daily_memory'] as $day) {
        foreach ($day['operations'] as $event) {
            $events[$event['id']] = ['date' => $day['date'], ...$event];
        }
    }
    $sale['history_evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'idTitulo' => $position['idTitulo'], 'cCta' => $position['cCta'],
        'operation_id' => 'sale1', 'quantity' => $events['sale1']['quantity'], 'history' => $sale['history']], 'HISTORY', '2026-08');
    $sale['component_evidence'] = tvmCurrentProof([...$common, 'idTitulo' => $position['idTitulo'], 'cCta' => $position['cCta'],
        'sale' => $events['sale1'], 'components' => $sale['components'], 'history_evidence_hash' => $sale['history_evidence']['source_hash']], 'SALE');
    if ($case === 'amortization-then-sale') {
        $release = &$input['recognition_inventory']['other_releases'][0];
        $release['fair_value_subaccount'] = 'FV000';
        $release['realization_evidence'] = tvmCurrentProof([...$common, 'title' => $input['title'], 'operation' => $events['amort1'],
            'components' => $release['components'], 'fair_value_subaccount' => 'FV000', 'fiscal_components' => $release['fiscal_components'],
            'prior_source_set_hash' => $sale['history']['snapshot_id'], 'realization_confirmed' => true, 'daily_memory_hash' => CanonicalJson::hash($memory)], 'AMORT_REALIZATION');
        unset($release);
    }
    unset($sale);
    $input['classification_evidence'] = tvmCurrentProof([...$common, 'title' => $input['title'], 'position_input_hash' => CanonicalJson::hash($position)], 'CLASSIFICATION');
    $input['inventory_evidence'] = tvmCurrentProof([...$common, 'idTitulo' => $position['idTitulo'], 'cCta' => $position['cCta'],
        'position_input_hash' => CanonicalJson::hash($position), 'inventory' => $input['recognition_inventory']], 'INVENTORY');
    $input['complete_evidence'] = tvmCurrentProof([...$common, 'position_input_hash' => CanonicalJson::hash($position), 'title' => $input['title'],
        'realizations' => $input['realizations'], 'recognition_inventory' => $input['recognition_inventory'], 'inventory_evidence_hash' => $input['inventory_evidence']['source_hash']], 'COMPLETE');
    $detail['produced_curve_evidence'] = tvmCurrentProof([...$common, 'title' => $input['title'], 'daily_memory_hash' => CanonicalJson::hash($memory),
        'appropriation_excludes_fair_value' => true, 'curve_rows' => array_map(static fn (array $day): array => array_intersect_key($day,
            array_flip(['date', 'contract_curve_id', 'appropriation'])), $memory['daily_memory'])], 'CURVE');
    unset($input, $detail);
    $ledger['entries'][4]['amount'] = $fixture['expected']['revenue'];

    return tvmFairFixture($case, sealTvmCurrentLedger($ledger), $version);
}

it('projects amortization and coupon continuity without including prior or current produced returns twice', function (string $case, string $base, string $release): void {
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmOtherFixture($case);
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($operation['base']['net_base'])->toBe($base)->and($projected['status'])->toBe('PROJECTED');
    $parent = $operation['base']['realization_bindings']['tvm1'];
    $accounts = array_column($month['book']['accounts'], null, 'cCta');
    expect($accounts['FV000']['vApur'])->toBe($release)->and($accounts['fvnew000']['vApur'])->toBe('0.00');
    if ($case === 'amortization-then-sale') {
        expect($parent['other_release_realizations'][0]['prior_return_not_reincluded'])->toBe('20')
            ->and($parent['other_release_realizations'][0]['components']['deferred_fair_value_realized'])->toBe('40')
            ->and($parent['analysis']['recognition_inventory']['closing']['deferred_fair_value_realized'])->toBe('100');
    } else {
        expect($parent)->not->toHaveKey('other_release_realizations');
    }
    if (getenv('TVM_OTHER_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-other-release-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-other-releases-2026.10.06-28-draft5/'.$case.'-'.$suffix.'.json';
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
})->with([['amortization-then-sale', '990.00', '100.00'], ['prior-coupon-then-sale', '1030.00', '80.00']]);

it('requires explicit current policy and independent evidence for amortization realization', function (string $tamper, string $reason): void {
    [, , $known, , $ledger] = tvmOtherFixture('amortization-then-sale');
    $root = dirname(__DIR__, 3);
    $rule = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.06-28-draft5.json'), true, 512, JSON_THROW_ON_ERROR)['financial_operation']['base'];
    if ($tamper === 'policy') {
        unset($rule['securities_realization']['other_release_realization_mode']);
    } else {
        // Preserve the inventory proof so the specific realization evidence is what fails.
        $release = &$ledger['entries'][4]['securities_realization']['analysis_input']['recognition_inventory']['other_releases'][0];
        unset($release['realization_evidence']);
        unset($release);
        $input = &$ledger['entries'][4]['securities_realization']['analysis_input'];
        $input['inventory_evidence']['value']['inventory'] = $input['recognition_inventory'];
        unset($input['inventory_evidence']['source_hash']);
        $input['inventory_evidence']['source_hash'] = CanonicalJson::hash($input['inventory_evidence']);
        $input['complete_evidence']['value']['recognition_inventory'] = $input['recognition_inventory'];
        $input['complete_evidence']['value']['inventory_evidence_hash'] = $input['inventory_evidence']['source_hash'];
        unset($input['complete_evidence']['source_hash']);
        $input['complete_evidence']['source_hash'] = CanonicalJson::hash($input['complete_evidence']);
        unset($input);
        $ledger = sealTvmCurrentLedger($ledger);
    }
    $result = (new FinancialSecuritiesRealizationQualifier)->resolve($ledger['entries'][4], $ledger, $known, $rule['securities_realization']);
    expect($result['reason'])->toBe('RTC_TVM_CURRENT_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([['policy', 'OTHER_FAIR_VALUE_REALIZATION_PENDING'], ['proof', 'INDEPENDENT_OTHER_FAIR_VALUE_REALIZATION_UNPROVEN']]);
