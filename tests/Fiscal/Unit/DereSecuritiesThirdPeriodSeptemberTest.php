<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicDeferralFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use Brick\Math\BigDecimal;

function thirdPeriodSeptemberSave(string $name, array $data): void
{
    if (getenv('TVM_THIRD_PERIOD_SEPTEMBER_CAPTURE') !== '1') {
        return;
    }
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-third-sale-integrated-2026.10.08-28-draft38/'.$name.'.json';
    if (is_file($path)) {
        expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));

        return;
    }
    $stream = fopen($path, 'x');
    expect($stream)->not->toBeFalse();
    fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    fclose($stream);
}

it('integrates September third sales with fiscal covered, uncovered and newly created rounding', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-third-sale-integrated-2026.10.08-28-draft38/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft38');
    $parent = $operation['base']['realization_bindings']['tvm1'];
    $sale = $parent['analysis']['realizations'][0];
    foreach ($fixture['third_period_sale_rounding_oracle'] as $field => $value) {
        expect($sale['book_release_rounding'][$field])->toBe($value);
    }
    expect($sale['historical_carrying_rounding'])->toBe($fixture['third_period_sale_rounding_oracle']['fiscal_covered_rounding_consumed'])
        ->and($parent['financial_entries'])->toBe([])->and($parent['deductions'])->toBe('0.00')
        ->and($parent['analysis']['recognition_inventory']['closing_fiscal_components'])->toBe($fixture['recognition_inventory']['closing_fiscal_components']);
    $split = $parent['analysis']['recognition_inventory']['rounding_inventory'];
    expect($split['created_at_sale'])->toBe($fixture['third_period_sale_rounding_oracle']['created_rounding_adjustment'])
        ->and(BigDecimal::of($split['closing_total'])->isEqualTo($fixture['recognition_inventory']['closing']['rounding_bridge']))->toBeTrue();
    foreach (['fiscal_covered_rounding', 'uncovered_created_rounding'] as $origin) {
        $pool = $split[$origin];
        expect(BigDecimal::of($pool['opening'])->minus($pool['consumed'])->isEqualTo($pool['closing']))->toBeTrue();
    }
    expect(BigDecimal::of($split['fiscal_covered_rounding']['closing'])->plus($split['uncovered_created_rounding']['closing'])->plus($split['closing_created'])->isEqualTo($split['closing_total']))->toBeTrue();
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
    $scenario['competence_date'] = '2026-09-30';
    $scenario['facts']['rtc.operation_date'] = '2026-09-30';
    $scenario['facts']['rtc.financial_ledger'] = [$ledger];
    thirdPeriodSeptemberSave($case.'-financial', $scenario);
    thirdPeriodSeptemberSave($case.'-month', $month);
})->with(['exact-partial-complete', 'exact-partial-partial', 'negative-aligned-partial-complete', 'negative-aligned-partial-partial', 'negative-partial-complete', 'negative-partial-partial', 'positive-aligned-partial-complete', 'positive-aligned-partial-partial', 'positive-partial-complete', 'positive-partial-partial', 'zero-partial-complete', 'zero-partial-partial']);

it('rejects absent third sale authorization and forged per origin proof', function (string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $case = 'positive-partial-partial';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-third-sale-integrated-2026.10.08-28-draft38/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [, , $known, , $ledger] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft38');
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-2026.10.08-28-draft38.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis'];
    if ($mutation === 'missing') {
        unset($input['realizations'][0]['book_release_rounding_evidence']);
    } elseif ($mutation === 'policy') {
        unset($rule['third_sale_book_rounding_policy']);
    } elseif ($mutation === 'coverage') {
        $input['realizations'][0]['history']['previously_recognized_carrying_rounding'] = '0.01000000';
    } else {
        $proof = &$input['realizations'][0]['book_release_rounding_evidence'];
        $proof['value'][$mutation] = '0.01000000';
        $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
        unset($proof);
    }
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $rule, $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason);
})->with([
    ['missing', 'THIRD_BOOK_RELEASE_ROUNDING_EVIDENCE_MISSING'],
    ['policy', 'THIRD_BOOK_RELEASE_ROUNDING_POLICY_UNPROVEN'],
    ['coverage', 'HISTORICAL_CARRYING_ROUNDING_UNPROVEN'],
    ['fiscal_covered_rounding_consumed', 'INDEPENDENT_THIRD_BOOK_RELEASE_ROUNDING_UNPROVEN'],
    ['uncovered_created_rounding_consumed', 'INDEPENDENT_THIRD_BOOK_RELEASE_ROUNDING_UNPROVEN'],
    ['created_rounding_adjustment', 'INDEPENDENT_THIRD_BOOK_RELEASE_ROUNDING_UNPROVEN'],
]);
