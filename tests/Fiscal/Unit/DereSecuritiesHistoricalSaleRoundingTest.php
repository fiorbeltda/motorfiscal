<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicDeferralFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function historicalSaleRoundingSave(string $name, array $data): void
{
    if (getenv('TVM_HISTORICAL_SALE_ROUNDING_CAPTURE') !== '1') {
        return;
    }
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-historical-sale-rounding-2026.10.08-28-draft33/'.$name.'.json';
    if (is_file($path)) {
        expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));

        return;
    }
    $stream = fopen($path, 'x');
    expect($stream)->not->toBeFalse();
    fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    fclose($stream);
}

it('separates previously covered rounding from rounding created at a subsequent sale', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-historical-sale-rounding-2026.10.08-28-draft33/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft33');
    $parent = $operation['base']['realization_bindings']['tvm1'];
    $sale = $parent['analysis']['realizations'][0];
    foreach ($fixture['historical_sale_rounding_oracle'] as $field => $value) {
        expect($sale['book_release_rounding'][$field])->toBe($value);
    }
    expect($sale['historical_carrying_rounding'])->toBe($fixture['historical_sale_rounding_oracle']['existing_rounding_consumed'])
        ->and($parent['financial_entries'])->toBe([])->and($parent['deductions'])->toBe('0.00')
        ->and($parent['analysis']['recognition_inventory']['closing_fiscal_components'])->toBe($fixture['recognition_inventory']['closing_fiscal_components']);
    $split = $parent['analysis']['recognition_inventory']['rounding_inventory'];
    expect($split['created_at_sale'])->toBe($fixture['historical_sale_rounding_oracle']['created_rounding_adjustment'])
        ->and($split['closing_total'])->toBe($fixture['recognition_inventory']['closing']['rounding_bridge']);
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])
        ->and($projected['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])
        ->and($operation['base']['net_base'])->toBe($fixture['expected']['base'])
        ->and($projected['production_ready'])->toBeFalse();
    $scenario = json_decode(file_get_contents($root.'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'SYNTHETIC-historical-sale-rounding-'.$case;
    $scenario['knowledge_at'] = $known->format(DATE_ATOM);
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $scenario['facts']['rtc.financial_ledger'] = [$ledger];
    historicalSaleRoundingSave($case.'-financial', $scenario);
    historicalSaleRoundingSave($case.'-month', $month);
})->with(['negative-partial', 'negative-complete', 'positive-partial', 'positive-complete', 'zero-partial', 'zero-complete', 'exact-partial', 'exact-complete', 'positive-aligned-partial', 'negative-aligned-partial']);

it('rejects missing or forged subsequent rounding proof and refuses new rounding as historical coverage', function (string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-historical-sale-rounding-2026.10.08-28-draft33/positive-partial-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [, , $known, , $ledger] = tvmPublicDeferralFixture('positive-partial', $fixture, '2026.10.08-28-draft33');
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-2026.10.08-28-draft33.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis'];
    if ($mutation === 'missing') {
        unset($input['realizations'][0]['book_release_rounding_evidence']);
    } elseif ($mutation === 'policy') {
        unset($rule['historical_sale_book_rounding_policy']);
    } elseif ($mutation === 'coverage') {
        $input['realizations'][0]['history']['previously_recognized_carrying_rounding'] = '-0.00250000';
    } else {
        $proof = &$input['realizations'][0]['book_release_rounding_evidence'];
        $proof['value'][$mutation === 'created' ? 'created_rounding_adjustment' : 'existing_rounding_consumed'] = '0.00500000';
        $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
        unset($proof);
    }
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $rule, $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason);
})->with([
    ['missing', 'HISTORICAL_CARRYING_ROUNDING_UNPROVEN'], ['policy', 'BOOK_RELEASE_ROUNDING_SCOPE_UNPROVEN'],
    ['created', 'INDEPENDENT_HISTORICAL_BOOK_RELEASE_ROUNDING_UNPROVEN'], ['old', 'INDEPENDENT_HISTORICAL_BOOK_RELEASE_ROUNDING_UNPROVEN'],
    ['coverage', 'HISTORICAL_CARRYING_ROUNDING_UNPROVEN'],
]);
