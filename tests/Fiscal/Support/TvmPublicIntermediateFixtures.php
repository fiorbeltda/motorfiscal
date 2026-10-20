<?php

declare(strict_types=1);

require_once __DIR__.'/TvmPublicDeferralFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialBaseEngine;
use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use Brick\Math\BigDecimal;

function tvmPublicIntermediatePriorFixture(array $fixture, string $version): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($root.'bundles/consumption-phase4b-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base'];
    $known = new DateTimeImmutable($manifest['knowledge_available_at']);
    [$ledger] = tvmCurrentFixture();
    $ledger['period_start'] = '2026-08-01';
    $ledger['period_end'] = '2026-08-31';
    foreach ($ledger['entries'] as &$entry) {
        $entry['recognized_on'] = '2026-08-31';
    }
    unset($entry);
    $position = $fixture['position'];
    $policy = $rule['public_offer_debt']['daily_memory_calculation'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], $known, $policy);
    expect($memory['status'])->toBe('RESOLVED', $memory['reason']);
    foreach ($fixture['independent_oracle'] as $key => $value) {
        if ($key !== 'daily_memory') {
            expect($memory[$key])->toBe($value);
        }
    }
    $scope = ['supplier_id' => $ledger['supplier_id'], 'period' => '2026-08', 'title' => $fixture['title'], 'opening' => $position['opening']];
    $history = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $fixture['recognition_history'], $known, $rule['securities_realization']['analysis']['history_policy']);
    expect($history['status'])->toBe('RESOLVED', $history['reason']);
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $input['position'] = $position;
    $input['title'] = $fixture['title'];
    $input['recognition_history'] = $fixture['recognition_history'];
    $input['recognition_inventory'] = $fixture['recognition_inventory'];
    $input['realizations'] = [];
    foreach ($memory['daily_memory'] as $day) {
        foreach ($day['operations'] as $operation) {
            if ($operation['kind'] !== 'SELL') {
                continue;
            }
            $components = $fixture['components'][$operation['id']];
            $fraction = BigDecimal::of($operation['quantity'])->dividedBy($position['opening']['quantity'], 8);
            $prior = ['snapshot_id' => $history['source_set_hash'], 'period' => '2026-07',
                'previously_recognized_produced_return' => $components['prior_produced_return'],
                'previously_assessed_produced_return' => (string) BigDecimal::of('100')->multipliedBy($fraction)->toScale(8),
                'previously_excluded_art195_return' => '0', 'previously_removed_pis_cofins' => '0',
                'previously_unoffset_public_title_loss' => '0', 'deferred_fair_value_released' => '0'];
            foreach ($fixture['historical_allocations'][$operation['id']] ?? [] as $key => $value) {
                $prior[$key] = $value;
            }
            $hp = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'idTitulo' => $position['idTitulo'], 'cCta' => $position['cCta'],
                'operation_id' => $operation['id'], 'quantity' => $operation['quantity'], 'history' => $prior], 'INTERMEDIATE.PRIOR', '2026-07');
            $cp = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period' => '2026-08', 'idTitulo' => $position['idTitulo'], 'cCta' => $position['cCta'],
                'sale' => ['date' => $day['date'], ...$operation], 'components' => $components, 'history_evidence_hash' => $hp['source_hash']], 'INTERMEDIATE.SALE', '2026-08');
            $input['realizations'][] = ['operation_id' => $operation['id'], 'components' => $components, 'history' => $prior, 'history_evidence' => $hp, 'component_evidence' => $cp];
        }
    }
    $common = ['supplier_id' => $ledger['supplier_id'], 'period' => '2026-08'];
    $input['classification_evidence'] = tvmCurrentProof([...$common, 'title' => $input['title'], 'position_input_hash' => CanonicalJson::hash($position)], 'INTERMEDIATE.CLASSIFICATION', '2026-08');
    $input['inventory_evidence'] = tvmCurrentProof([...$common, 'idTitulo' => $position['idTitulo'], 'cCta' => $position['cCta'],
        'position_input_hash' => CanonicalJson::hash($position), 'inventory' => $input['recognition_inventory']], 'INTERMEDIATE.INVENTORY', '2026-08');
    $input['complete_evidence'] = tvmCurrentProof([...$common, 'position_input_hash' => CanonicalJson::hash($position), 'title' => $input['title'],
        'realizations' => $input['realizations'], 'recognition_inventory' => $input['recognition_inventory'], 'inventory_evidence_hash' => $input['inventory_evidence']['source_hash']], 'INTERMEDIATE.COMPLETE', '2026-08');
    $detail = &$ledger['entries'][4]['securities_realization'];
    $detail['analysis_input'] = $input;
    $detail['public_yield_entry_id'] = 'yield1';
    $detail['deduction_evidence'] = [];
    $detail['qualification_evidence'] = tvmCurrentProof([], 'INTERMEDIATE.QUALIFICATION', '2026-08');
    $detail['produced_curve_evidence'] = tvmCurrentProof([...$common, 'title' => $input['title'], 'daily_memory_hash' => CanonicalJson::hash($memory),
        'appropriation_excludes_fair_value' => true, 'curve_rows' => array_map(static fn (array $row): array => array_intersect_key($row,
            array_flip(['date', 'contract_curve_id', 'appropriation'])), $memory['daily_memory'])], 'INTERMEDIATE.CURVE', '2026-08');
    unset($detail);
    $ledger['entries'][4]['amount'] = '0.00';
    $prototype = json_decode(file_get_contents($root.'scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $yield = $prototype['facts']['rtc.financial_ledger'][0]['entries'][4];
    $yield['recognized_on'] = '2026-08-31';
    $yield['document_id'] = $fixture['document_id'] ?? 'SYNTHETIC.PUBLIC.INTERMEDIATE.AUGUST.YIELD';
    $yield['amount'] = $fixture['yield_amount'] ?? '50.00';
    $d = &$yield['public_offer_debt'];
    $d['position'] = $position;
    $d['title'] = $fixture['title'];
    $d['result_account_code'] = 'curve000';
    $d['eligibility_evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'], 'title' => $d['title'],
        'financial_entry_id' => 'yield1', 'document_id' => $yield['document_id'], 'amount' => $yield['amount'], 'result_account_code' => 'curve000',
        'position_input_hash' => CanonicalJson::hash($position), 'position_mode' => 'DAILY_MEMORY'], 'INTERMEDIATE.ELIGIBILITY', '2026-08');
    $d['sale_recognition'] = ['companion_parent_id' => 'tvm1', 'analysis_input' => $input,
        'independent_evidence' => tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_start' => $ledger['period_start'], 'period_end' => $ledger['period_end'],
            'financial_entry_id' => 'yield1', 'document_id' => $yield['document_id'], 'analysis_input_hash' => CanonicalJson::hash($input),
            'calculation_hash' => CanonicalJson::hash($memory), 'policy_hash' => CanonicalJson::hash($rule['public_offer_debt']['sale_recognition_policy']),
            'appropriation_excludes_fair_value' => true, 'previously_recognized_returns_not_reincluded' => true,
            'companion_parent_id' => 'tvm1', 'fair_value_tax_origin' => 'D1101'], 'INTERMEDIATE.SPLIT', '2026-08')];
    unset($d);
    $ledger['entries'][] = $yield;
    $ledger = sealTvmCurrentLedger($ledger);
    $base = (new FinancialBaseEngine)->resolve($ledger, LocalDate::fromString('2026-08-31'), $known, $rule);
    expect($base['status'])->toBe('RESOLVED', $base['reason'])->and($base['net_base'])->toBe($fixture['expected']['base']);
    $scenario = json_decode(file_get_contents($root.'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = $fixture['scenario_id'] ?? 'SYNTHETIC-public-intermediate-august';
    $scenario['competence_date'] = '2026-08-31';
    $scenario['knowledge_at'] = $known->format(DATE_ATOM);
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $scenario['facts']['rtc.operation_date'] = '2026-08-31';
    $scenario['facts']['rtc.financial_ledger'] = [$ledger];

    return [$base, $ledger, $known, $manifest, $scenario, $input];
}
