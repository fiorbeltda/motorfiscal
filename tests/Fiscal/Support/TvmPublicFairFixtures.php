<?php

declare(strict_types=1);

require_once __DIR__.'/TvmFairFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;

function tvmPublicFairFixture(string $case, ?array $providedFixture = null, string $version = '2026.10.07-28-draft13'): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $path): array => json_decode(file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
    $catalog = $read('catalogs/rtc-codes-'.$version.'.json');
    $fixture = $providedFixture ?? $read('scenarios/tvm-public-fair-'.$version.'/'.$case.'.json');
    [$ledger, , $known] = tvmCurrentFirstFixture($case, $fixture, $catalog['financial_operation']['base']);
    $ledger['entries'][4]['securities_realization']['public_yield_entry_id'] = 'yield1';
    if ($case === 'opposing-fair') {
        $parent = &$ledger['entries'][4]['securities_realization'];
        foreach ($parent['analysis_input']['realizations'] as &$sale) {
            if (! BigDecimal::of($sale['components']['new_fair_value_change_at_sale'])->isNegative()) {
                continue;
            }
            $sale['history']['fair_value_subaccount'] = 'FVNEG000';
            $sale['history_evidence']['value']['history'] = $sale['history'];
            $sale['history_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['history_evidence'], ['source_hash' => true]));
            $sale['component_evidence']['value']['history_evidence_hash'] = $sale['history_evidence']['source_hash'];
            $sale['component_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['component_evidence'], ['source_hash' => true]));
        }
        unset($sale);
        $proof = &$parent['analysis_input']['complete_evidence'];
        $proof['value']['realizations'] = $parent['analysis_input']['realizations'];
        $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
        unset($proof);
        foreach ($parent['deduction_evidence'] as &$deduction) {
            $deduction['independent_evidence']['value']['analysis_input_hash'] = CanonicalJson::hash($parent['analysis_input']);
            $deduction['independent_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($deduction['independent_evidence'], ['source_hash' => true]));
        }
        unset($deduction, $parent);
    }
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $prototype = $read('scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate-financial.json');
    $yield = $prototype['facts']['rtc.financial_ledger'][0]['entries'][4];
    $yield['document_id'] = 'SYNTHETIC.PUBLIC.FAIR.YIELD.'.$case;
    $yield['amount'] = $fixture['position']['declared_monthly']['vVarMensal'];
    $detail = &$yield['public_offer_debt'];
    $detail['position'] = $fixture['position'];
    $detail['title'] = $fixture['title'];
    $detail['result_account_code'] = 'curve000';
    $policy = $catalog['financial_operation']['base']['public_offer_debt'];
    $calc = (new FinancialDebtDailyMemoryCalculator)->calculate($detail['position'], $policy['daily_memory_calculation']['series'], $known, $policy['daily_memory_calculation']);
    $detail['eligibility_evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'],
        'title' => $detail['title'], 'financial_entry_id' => 'yield1', 'document_id' => $yield['document_id'], 'amount' => $yield['amount'],
        'result_account_code' => 'curve000', 'position_input_hash' => CanonicalJson::hash($detail['position']), 'position_mode' => 'DAILY_MEMORY'], 'PUBLIC.FAIR.ELIGIBILITY');
    $detail['sale_recognition'] = ['companion_parent_id' => 'tvm1', 'analysis_input' => $input,
        'independent_evidence' => tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_start' => $ledger['period_start'], 'period_end' => $ledger['period_end'],
            'financial_entry_id' => 'yield1', 'document_id' => $yield['document_id'], 'analysis_input_hash' => CanonicalJson::hash($input),
            'calculation_hash' => CanonicalJson::hash($calc), 'policy_hash' => CanonicalJson::hash($policy['sale_recognition_policy']),
            'appropriation_excludes_fair_value' => true, 'previously_recognized_returns_not_reincluded' => true,
            'companion_parent_id' => 'tvm1', 'fair_value_tax_origin' => 'D1101'], 'PUBLIC.FAIR.SPLIT')];
    unset($detail);
    $ledger['entries'][] = $yield;
    $ledger = sealTvmCurrentLedger($ledger);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmFairFixture($case, $ledger, $version);
    $publicMonth = $read('scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate.json');
    $month['contributor'] = $publicMonth['contributor'];
    $month['prerequisites']['D2101_required'] = true;
    $month['debt_yield'] = ['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'], 'observed_on' => '2026-10-06',
        'financial_entry_ids' => ['yield1'], 'previous_title_balances' => []];
    $detail = &$month['debt_yield'];
    $detail['evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'],
        'detail' => array_diff_key($detail, ['evidence' => true]), 'operation_hash' => CanonicalJson::hash($operation),
        'book_input_hash' => CanonicalJson::hash($month['book']), 'pgcc_source_hash' => $month['book']['pgcc_source_hash']], 'PUBLIC.FAIR.MONTH');
    unset($detail);

    return [$operation, $month, $known, $rules, $ledger, $manifest, $fixture];
}
