<?php

declare(strict_types=1);

require_once __DIR__.'/TvmFairFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\ConsumptionReform\FinancialThirdSaleRoundingQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;

function tvmPublicDeferralFixture(string $case, ?array $providedFixture = null, string $version = '2026.10.08-28-draft15'): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $path): array => json_decode(file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
    $catalog = $read('catalogs/rtc-codes-'.$version.'.json');
    $fixture = $providedFixture ?? $read('scenarios/tvm-public-deferral-'.$version.'/'.$case.'-current.json');
    $period = $fixture['position']['period'];
    $priorPeriod = (new DateTimeImmutable($period.'-01'))->modify('-1 month')->format('Y-m');
    [$ledger, , $known] = tvmCurrentFirstFixture($case, $fixture, $catalog['financial_operation']['base']);
    $detail = &$ledger['entries'][4]['securities_realization'];
    $input = &$detail['analysis_input'];
    $input['recognition_history'] = $fixture['recognition_history'];
    $scope = ['supplier_id' => $ledger['supplier_id'], 'period' => $period, 'title' => $input['title'], 'opening' => $input['position']['opening']];
    $history = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $input['recognition_history'], $known,
        $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy']);
    expect($history['status'])->toBe('RESOLVED', $history['reason']);
    $policy = $catalog['financial_operation']['base']['public_offer_debt'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($input['position'], $policy['daily_memory_calculation']['series'], $known, $policy['daily_memory_calculation']);
    $sale = &$input['realizations'][0];
    $parts = $sale['components'];
    $fraction = BigDecimal::of($input['position']['operations'][0]['quantity'])->dividedBy($input['position']['opening']['quantity'], 8);
    $sale['history'] = ['snapshot_id' => $history['source_set_hash'], 'period' => $priorPeriod,
        'previously_recognized_produced_return' => $parts['prior_produced_return'],
        'previously_assessed_produced_return' => (string) BigDecimal::of($history['opening_fiscal_components']['assessed_produced_return'])->multipliedBy($fraction)->toScale(8),
        'previously_excluded_art195_return' => (string) BigDecimal::of($history['opening_fiscal_components']['excluded_art195_return'])->multipliedBy($fraction)->toScale(8),
        'previously_removed_pis_cofins' => (string) BigDecimal::of($history['opening_fiscal_components']['removed_pis_cofins'])->multipliedBy($fraction)->toScale(8),
        'deferred_fair_value_released' => $parts['deferred_fair_value_realized'], 'fair_value_subaccount' => 'FV000'];
    if (array_key_exists('unoffset_public_title_loss', $history['opening_fiscal_components'])) {
        $sale['history']['previously_unoffset_public_title_loss'] = (string) BigDecimal::of($history['opening_fiscal_components']['unoffset_public_title_loss'])->multipliedBy($fraction)->toScale(8);
    }
    if (($history['public_explicit_net_closing_replayed'] ?? false) === true) {
        $sale['history']['previously_recognized_carrying_rounding'] = $fixture['historical_sale_rounding_oracle']['existing_rounding_consumed'] ?? $parts['rounding_bridge'];
    }
    if (($history['public_created_rounding_closing_replayed'] ?? false) === true) {
        $sale['history']['previously_recognized_carrying_rounding'] = $fixture['third_period_sale_rounding_oracle']['fiscal_covered_rounding_consumed'];
    }
    if (isset($fixture['historical_sale_rounding_oracle']) || isset($fixture['third_period_sale_rounding_oracle'])) {
        $native = null;
        foreach ($memory['daily_memory'] as $day) {
            foreach ($day['operations'] as $operation) {
                if ($operation['id'] === $sale['operation_id']) {
                    $native = ['date' => $day['date'], ...$operation];
                }
            }
        }
        $third = isset($fixture['third_period_sale_rounding_oracle']);
        $sale['book_release_rounding_evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period' => $period,
            'title' => $input['title'], 'operation' => $native, 'daily_memory_hash' => CanonicalJson::hash($memory),
            'history_source_set_hash' => $history['source_set_hash'], 'method' => $third
                ? FinancialThirdSaleRoundingQualifier::METHOD
                : 'PROVED_HISTORICAL_AVERAGE_RELEASE_WITH_SEPARATE_CREATED_ROUNDING_8_2',
            ...($third ? $fixture['third_period_sale_rounding_oracle'] : $fixture['historical_sale_rounding_oracle']),
            'normative_homologation_claimed' => false], $third ? 'THIRD.AVERAGE.RELEASE' : 'HISTORICAL.AVERAGE.RELEASE', $period);
    }
    $sale['history_evidence']['value']['history'] = $sale['history'];
    $sale['history_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['history_evidence'], ['source_hash' => true]));
    $sale['component_evidence']['value']['history_evidence_hash'] = $sale['history_evidence']['source_hash'];
    $sale['component_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['component_evidence'], ['source_hash' => true]));
    unset($sale);
    $input['complete_evidence']['value']['realizations'] = $input['realizations'];
    $input['complete_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['complete_evidence'], ['source_hash' => true]));
    $detail['public_yield_entry_id'] = 'yield1';
    if (BigDecimal::of($parts['deferred_fair_value_realized'])->isNegative()) {
        $component = ['component_id' => 'SALE:sale1:deferred_fair_value_realized', 'component_kind' => 'DEFERRED_FAIR_VALUE_REALIZED',
            'signed_unrounded_amount' => (string) BigDecimal::of($parts['deferred_fair_value_realized'])->toScale(8),
            'amount' => $fixture['expected']['deductions'], 'bucket' => 'DEDUCTION', 'expected_codTrib' => '411011701', 'source_article' => 'LC214 art192 paragraph6; art187'];
        $conditions = ['operation_authorized' => true, 'within_operational_limits' => true, 'not_administrative_expense' => true,
            'authorization_evidence_id' => 'SYNTHETIC.PUBLIC.DEFERRAL.LOSS.AUTHORIZATION'];
        $detail['deduction_evidence'] = [['component_id' => $component['component_id'], 'conditions' => $conditions,
            'independent_evidence' => tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period' => $period, 'title' => $input['title'],
                'financial_entry_id' => 'tvm1', 'component' => $component, 'conditions' => $conditions, 'analysis_input_hash' => CanonicalJson::hash($input)], 'PUBLIC.DEFERRAL.LOSS', $period)]];
    }
    $analysisInput = $input;
    unset($input, $detail);
    $prototype = $read('scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate-financial.json');
    $yield = $prototype['facts']['rtc.financial_ledger'][0]['entries'][4];
    $yield['document_id'] = 'SYNTHETIC.PUBLIC.DEFERRAL.YIELD.'.$case;
    if ($period !== '2026-09') {
        $yield['recognized_on'] = $ledger['period_end'];
    }
    $yield['amount'] = $fixture['yield_amount'] ?? '50.00';
    $d = &$yield['public_offer_debt'];
    $d['position'] = $fixture['position'];
    $d['title'] = $fixture['title'];
    $d['result_account_code'] = 'curve000';
    $d['eligibility_evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'], 'title' => $d['title'],
        'financial_entry_id' => 'yield1', 'document_id' => $yield['document_id'], 'amount' => $yield['amount'], 'result_account_code' => 'curve000',
        'position_input_hash' => CanonicalJson::hash($d['position']), 'position_mode' => 'DAILY_MEMORY'], 'PUBLIC.DEFERRAL.ELIGIBILITY', $period);
    $d['sale_recognition'] = ['companion_parent_id' => 'tvm1', 'analysis_input' => $analysisInput,
        'independent_evidence' => tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_start' => $ledger['period_start'], 'period_end' => $ledger['period_end'],
            'financial_entry_id' => 'yield1', 'document_id' => $yield['document_id'], 'analysis_input_hash' => CanonicalJson::hash($analysisInput),
            'calculation_hash' => CanonicalJson::hash($memory), 'policy_hash' => CanonicalJson::hash($policy['sale_recognition_policy']),
            'appropriation_excludes_fair_value' => true, 'previously_recognized_returns_not_reincluded' => true,
            'companion_parent_id' => 'tvm1', 'fair_value_tax_origin' => 'D1101'], 'PUBLIC.DEFERRAL.SPLIT', $period)];
    unset($d);
    $ledger['entries'][] = $yield;
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmFairFixture($case, sealTvmCurrentLedger($ledger), $version);
    $publicMonth = $read('scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate.json');
    $month['contributor'] = $publicMonth['contributor'];
    if ($period !== '2026-09') {
        $month['contributor']['period_end'] = $ledger['period_end'];
    }
    $month['prerequisites']['D2101_required'] = true;
    $month['debt_yield'] = ['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'], 'observed_on' => '2026-10-06',
        'financial_entry_ids' => ['yield1'], 'previous_title_balances' => ['yield1' => tvmCurrentProof(['supplier_id' => $ledger['supplier_id'],
            'period' => $priorPeriod, 'idTitulo' => $fixture['title']['idTitulo'], 'cCta' => $fixture['title']['cCta'],
            'vSaldoContFin' => $fixture['position']['opening']['book_balance']], 'PUBLIC.DEFERRAL.PRIOR.TITLE', $priorPeriod)]];
    $month['debt_yield']['evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'],
        'detail' => $month['debt_yield'], 'operation_hash' => CanonicalJson::hash($operation), 'book_input_hash' => CanonicalJson::hash($month['book']),
        'pgcc_source_hash' => $month['book']['pgcc_source_hash']], 'PUBLIC.DEFERRAL.MONTH', $period);

    return [$operation, $month, $known, $rules, $ledger, $manifest, $fixture];
}
