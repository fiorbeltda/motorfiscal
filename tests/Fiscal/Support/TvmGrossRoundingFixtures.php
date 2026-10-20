<?php

declare(strict_types=1);

require_once __DIR__.'/TvmFairFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

function tvmGrossRoundingFixture(string $case, ?array $providedFixture = null, string $version = '2026.10.08-28-draft30'): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = $providedFixture ?? json_decode(file_get_contents($root.'scenarios/tvm-public-gross-rounding-'.$version.'/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
    $period = $fixture['position']['period'];
    $catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    [$ledger, $rule, $known] = tvmCurrentFirstFixture($case, $fixture, $catalog['financial_operation']['base']);
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $ledger['entries'][4]['securities_realization']['public_yield_entry_id'] = 'yield1';
    $policy = $rule['public_offer_debt'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($input['position'], $policy['daily_memory_calculation']['series'], $known, $policy['daily_memory_calculation']);
    $prototype = json_decode(file_get_contents($root.'scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $yield = $prototype['facts']['rtc.financial_ledger'][0]['entries'][4];
    $yield['document_id'] = 'SYNTHETIC.PUBLIC.GROSS.ROUNDING.'.$case;
    if ($period !== '2026-09') {
        $yield['recognized_on'] = $ledger['period_end'];
    }
    $yield['amount'] = (string) BigDecimal::of($memory['monthly']['vVarMensal'])->abs();
    $d = &$yield['public_offer_debt'];
    $d['position'] = $fixture['position'];
    $d['title'] = $fixture['title'];
    $d['result_account_code'] = 'curve000';
    $d['eligibility_evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'], 'title' => $d['title'],
        'financial_entry_id' => 'yield1', 'document_id' => $yield['document_id'], 'amount' => $yield['amount'], 'result_account_code' => 'curve000',
        'position_input_hash' => CanonicalJson::hash($d['position']), 'position_mode' => 'DAILY_MEMORY'], 'GROSS.ELIGIBILITY', $period);
    $d['sale_recognition'] = ['companion_parent_id' => 'tvm1', 'analysis_input' => $input,
        'independent_evidence' => tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_start' => $ledger['period_start'], 'period_end' => $ledger['period_end'],
            'financial_entry_id' => 'yield1', 'document_id' => $yield['document_id'], 'analysis_input_hash' => CanonicalJson::hash($input),
            'calculation_hash' => CanonicalJson::hash($memory), 'policy_hash' => CanonicalJson::hash($policy['sale_recognition_policy']),
            'appropriation_excludes_fair_value' => true, 'previously_recognized_returns_not_reincluded' => true,
            'companion_parent_id' => 'tvm1', 'fair_value_tax_origin' => 'D1101'], 'GROSS.SPLIT', $period)];
    $o = $fixture['gross_rounding_oracle'];
    $delta = BigDecimal::of($o['signed_adjustment']);
    if (! $delta->isZero()) {
        $lines = [
            ['cCta' => $input['title']['cCta'], 'nature' => $delta->isNegative() ? 'C' : 'D', 'amount' => (string) $delta->abs(), 'financial_entry_id' => null],
            ['cCta' => 'curve000', 'nature' => $delta->isNegative() ? 'D' : 'C', 'amount' => (string) $delta->abs(), 'financial_entry_id' => 'yield1'],
        ];
        usort($lines, static fn (array $a, array $b): int => [$a['cCta'], $a['nature'], $a['financial_entry_id']] <=> [$b['cCta'], $b['nature'], $b['financial_entry_id']]);
        $journal = ['event_id' => 'GROSS_ROUNDING:'.$period.'-11', 'parent_id' => 'tvm1', 'date' => $period.'-11', 'lines' => $lines];
        $method = 'PROVIDED_PUBLIC_GROSS_ROUNDING_JOURNAL_8_2';
        $d['gross_rounding_reconciliation'] = ['method' => $method, 'normative_homologation_claimed' => false, 'journal' => $journal,
            'independent_evidence' => tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period' => $period, 'title' => $input['title'],
                'daily_memory_hash' => CanonicalJson::hash($memory), 'method' => $method, ...$o, 'monthly_variation' => $memory['monthly']['vVarMensal'],
                'journal' => $journal, 'result_account_code' => 'curve000', 'financial_entry_id' => 'yield1', 'parent_id' => 'tvm1',
                'policy_hash' => CanonicalJson::hash($policy), 'tax_origin' => 'D2101', 'book_apur_amount' => '0.00', 'normative_homologation_claimed' => false], 'GROSS.JOURNAL', $period)];
    }
    unset($d);
    $ledger['entries'][] = $yield;
    $ledger = sealTvmCurrentLedger($ledger);
    $gross = ['C' => BigDecimal::of('0.00000000'), 'D' => BigDecimal::of('0.00000000')];
    $booked = ['C' => BigDecimal::of('0.00'), 'D' => BigDecimal::of('0.00')];
    $allocations = [];
    foreach ($memory['daily_memory'] as $day) {
        $raw = BigDecimal::of($day['appropriation']);
        if ($raw->isZero()) {
            continue;
        }
        $nature = $raw->isNegative() ? 'D' : 'C';
        $gross[$nature] = $gross[$nature]->plus($raw->abs());
        $total = $gross[$nature]->toScale(2, RoundingMode::HalfEven);
        $amount = $total->minus($booked[$nature]);
        $booked[$nature] = $total;
        $amount = $nature === 'D' ? $amount->negated() : $amount;
        $allocations[] = ['date' => $day['date'], 'nature' => $nature, 'raw_amount' => (string) $raw->toScale(8), 'booked_amount' => (string) $amount->toScale(2),
            'rounding_residual' => (string) $raw->minus($amount)->toScale(8), 'accumulated_raw_gross' => (string) $gross[$nature]->toScale(8), 'accumulated_booked_gross' => (string) $total];
    }
    $base = tvmCurrentBase($ledger, $rule, $known);
    expect($base['status'])->toBe('RESOLVED', $base['reason']);
    $value = ['supplier_id' => $ledger['supplier_id'], 'period' => $period, 'title' => $input['title'], 'daily_memory_hash' => CanonicalJson::hash($memory),
        'method' => 'CUMULATIVE_GROSS_HALF_EVEN_8_2', 'allocations' => $allocations, 'gross_credit' => (string) $booked['C'], 'gross_debit' => (string) $booked['D'], 'normative_homologation_claimed' => false];
    if (! $delta->isZero()) {
        $value['gross_rounding_reconciliation'] = $base['realization_bindings']['tvm1']['public_yield_binding']['gross_rounding_reconciliation'];
    }
    $rounding = ['method' => $value['method'], 'normative_homologation_claimed' => false, 'allocations' => $allocations, 'independent_evidence' => tvmCurrentProof($value, 'GROSS.DAILY', $period)];
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmFairFixture($case, $ledger, $version, $rounding);
    $publicMonth = json_decode(file_get_contents($root.'scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate.json'), true, 512, JSON_THROW_ON_ERROR);
    $month['contributor'] = $publicMonth['contributor'];
    $month['contributor']['period_end'] = $ledger['period_end'];
    $month['prerequisites']['D2101_required'] = true;
    $month['debt_yield'] = ['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'], 'observed_on' => '2026-10-06',
        'financial_entry_ids' => ['yield1'], 'previous_title_balances' => []];
    $month['debt_yield']['evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'], 'detail' => $month['debt_yield'],
        'operation_hash' => CanonicalJson::hash($operation), 'book_input_hash' => CanonicalJson::hash($month['book']), 'pgcc_source_hash' => $month['book']['pgcc_source_hash']], 'GROSS.MONTH', $period);

    return [$operation, $month, $known, $rules, $ledger, $manifest, $fixture];
}
