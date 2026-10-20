<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmSharedAssetHistoricalFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\DereSecuritiesRealizationBindingQualifier;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('qualifies each independent public historical title before combining books', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-shared-asset-current-2026.10.09-28-draft43/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [$operation, $month, $known, $rules] = tvmPublicDeferralFixture($case, $fixture, '2026.10.09-28-draft43');
    expect($operation['base']['net_base'])->toBe($fixture['expected']['base']);
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason']);
})->with(['positive-partial', 'positive-complete', 'negative-partial', 'negative-complete']);

it('combines two historical public sales without offsetting title losses', function (string $positiveMode, string $negativeMode, bool $shared): void {
    [$operation, $month, $known, $rules, $ledger, $manifest] = sharedAssetHistoricalFixture($positiveMode, $negativeMode, $shared, '2026.10.09-28-draft43');
    expect($operation['base']['realization_bindings'])->toHaveCount(2);
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])
        ->and($projected['debt_yield_projection']['title_count'])->toBe(2)
        ->and($projected['debt_yield_projection']['total_vApur'])->toBe($positiveMode === 'partial' ? '45.00' : '41.85')
        ->and($operation['base']['net_base'])->toBe($positiveMode === 'partial' ? '745.00' : '741.85')
        ->and($operation['base']['deductions_before_art188'])->toBe('300.00');
    $book = array_column($month['book']['accounts'], null, 'cCta');
    expect($book['debt000']['vSaldoInic'])->toBe('19950.00');
    expect(array_unique(array_column($month['securities_realization_book'], 'asset_account')))->toBe(['debt000']);
    expect($projected['securities_realization_binding']['asset_position_mode'])->toBe('PUBLIC_DISTINCT_ISINS_AGGREGATED_ACCOUNT')
        ->and($projected['securities_realization_binding']['aggregate_asset_positions']['debt000'])->toBe(['opening' => '19950.00', 'closing' => $book['debt000']['vSaldoFinal'], 'titles' => 2]);
    $entries = array_column($operation['base']['entries'], null, 'id');
    expect($entries['yield2']['d2101_binding']['taxable_amount'])->toBe('0.00')
        ->and($entries['yield2']['d2101_binding']['unoffset_title_loss'])->toBe('50.00');
    if (getenv('TVM_SHARED_ASSET_43_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3).'/resources/fiscal/';
        $case = $positiveMode.'-'.$negativeMode.'-'.($shared ? 'shared' : 'separate');
        $scenario = json_decode(file_get_contents($root.'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-shared-asset-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['competence_date'] = '2026-08-31';
        $scenario['facts']['rtc.operation_date'] = '2026-08-31';
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $kind => $data) {
            $path = $root.'scenarios/tvm-shared-asset-current-2026.10.09-28-draft43/'.$case.'-'.$kind.'.json';
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
})->with([['partial', 'partial', false], ['partial', 'partial', true], ['complete', 'complete', false], ['complete', 'complete', true], ['partial', 'complete', false], ['partial', 'complete', true], ['complete', 'partial', false], ['complete', 'partial', true]]);

it('rejects incomplete shared book coverage and attempted cross title totalization', function (string $mutation): void {
    [$operation, $month, $known, $rules] = sharedAssetHistoricalFixture('partial', 'partial', true, '2026.10.09-28-draft43');
    if ($mutation === 'parent') {
        array_pop($month['securities_realization_book']);
    } elseif ($mutation === 'links') {
        foreach ($month['book']['accounts'] as &$account) {
            if ($account['cCta'] === 'curve000') {
                $account['financial_entry_ids'] = ['yield1'];
            }
        }
        unset($account);
    } else {
        $operation['base']['net_base'] = '700.00';
    }
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['production_ready'])->toBeFalse();
})->with(['parent', 'links', 'offset']);

it('rejects wrong title, duplicated source and wrong source period in historical allocations', function (string $mutation): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-shared-asset-current-2026.10.09-28-draft43/positive-partial-current.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-2026.10.09-28-draft43.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($root.'bundles/consumption-phase4b-2026.10.09-28-draft43.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($mutation === 'title') {
        $other = json_decode(file_get_contents($root.'scenarios/tvm-shared-asset-current-2026.10.09-28-draft43/negative-partial-current.json'), true, 512, JSON_THROW_ON_ERROR);
        $fixture['recognition_history']['fiscal_sources'] = $other['recognition_history']['fiscal_sources'];
    } elseif ($mutation === 'duplicate') {
        $fixture['recognition_history']['fiscal_sources'][] = $fixture['recognition_history']['fiscal_sources'][0];
    } else {
        $fixture['recognition_history']['closing_memory']['position']['period'] = '2026-06';
    }
    $scope = ['supplier_id' => $fixture['position']['supplier_id'], 'period' => '2026-08', 'title' => $fixture['title'], 'opening' => $fixture['position']['opening']];
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $fixture['recognition_history'], new DateTimeImmutable($manifest['knowledge_available_at']), $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy']);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['production_ready'])->toBeFalse();
})->with(['title', 'duplicate', 'period']);

it('independently rejects incorrect aggregate positions despite resealed supplied book proofs', function (string $mutation, string $expected): void {
    [$operation, $month, $known, $rules, $ledger] = sharedAssetHistoricalFixture('partial', 'partial', true);
    $rule = $rules['closing']['securities_realization_binding'];
    foreach ($month['book']['accounts'] as &$account) {
        if ($account['cCta'] !== 'debt000') {
            continue;
        }
        if ($mutation === 'opening') {
            $account['vSaldoInic'] = '19951.00';
            $account['vSaldoFinal'] = '11971.00';
        } elseif ($mutation === 'closing') {
            $account['vSaldoFinal'] = '11971.00';
        } elseif ($mutation === 'gross') {
            $account['vMovDebt'] = '51.00';
            $account['vMovCred'] = '8031.00';
        }
    }
    unset($account);
    if ($mutation === 'policy') {
        unset($rule['asset_position_mode']);
    } elseif ($mutation === 'unknown') {
        $rule['asset_position_mode'] = 'UNAVAILABLE';
    } elseif ($mutation === 'duplicate') {
        $operation['base']['realization_bindings']['tvm2']['title']['idTitulo'] = $operation['base']['realization_bindings']['tvm1']['title']['idTitulo'];
    }
    [$month, $pgcc] = resealSharedAssetBook($operation, $month, $ledger, $known, $rules);
    $result = (new DereSecuritiesRealizationBindingQualifier)->resolve($operation, $month, $pgcc, $known, $rule);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($expected);
})->with([
    ['opening', 'RTC_DERE_TVM_BOOK_AGGREGATED_ASSET_POSITION_CONFLICT'],
    ['closing', 'RTC_DERE_TVM_BOOK_AGGREGATED_ASSET_POSITION_CONFLICT'],
    ['gross', 'RTC_DERE_TVM_BOOK_JOURNAL_GROSS_MOVEMENTS_OR_COMPONENT_LINKS_CONFLICT'],
    ['policy', 'RTC_DERE_TVM_BOOK_ASSET_POSITION_OR_PRINCIPAL_EXCLUSION_CONFLICT'],
    ['unknown', 'RTC_DERE_TVM_BOOK_ASSET_POSITION_POLICY_UNAVAILABLE'],
    ['duplicate', 'RTC_DERE_TVM_BOOK_AGGREGATED_ASSET_TITLE_DUPLICATE'],
]);
