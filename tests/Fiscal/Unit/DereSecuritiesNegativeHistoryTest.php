<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialBaseEngine;
use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

require_once __DIR__.'/../Support/TvmFairFixtures.php';

function tvmNegativeFixture(): array
{
    $root = dirname(__DIR__, 3);
    $version = '2026.10.07-28-draft8';
    $directory = $root.'/resources/fiscal/scenarios/tvm-negative-history-'.$version;
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base'];
    $historyRule = $rule['securities_realization']['analysis']['history_policy'];
    $known = new DateTimeImmutable($rule['securities_realization']['published_at']);
    $snapshot = json_decode(file_get_contents($root.'/storage/app/phase4b-realization-negative-history-draft-20261007-28/prior-negative-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
    $previous = json_decode(file_get_contents($directory.'/prior-negative-position-v2.json'), true, 512, JSON_THROW_ON_ERROR);
    $fixture = json_decode(file_get_contents($directory.'/current-negative-position.json'), true, 512, JSON_THROW_ON_ERROR);
    [$ledger] = tvmCurrentFixture();
    $detail = &$ledger['entries'][4]['securities_realization'];
    $input = &$detail['analysis_input'];
    $input['position'] = $fixture['position'];
    $scope = ['supplier_id' => $ledger['supplier_id'], 'period' => '2026-09', 'title' => $input['title'], 'opening' => $fixture['position']['opening']];
    $policy = $historyRule['daily_memory_policies']['2026-08'];
    $previousMemory = (new FinancialDebtDailyMemoryCalculator)->calculate($previous['position'], $policy['series'], $known, $policy);
    expect($previousMemory['status'])->toBe('RESOLVED');
    foreach ($previous['independent_oracle'] as $key => $value) {
        if ($key !== 'daily_memory') {
            expect($previousMemory[$key])->toBe($value);
        }
    }
    $closing = ['contract' => 'TVM-RECOGNITION-CLOSING-MEMORY-1', 'position' => $previous['position'], 'result' => $previousMemory,
        'known_at' => $known->format(DATE_ATOM), 'policy_hash' => CanonicalJson::hash($policy)];
    $zero = array_fill_keys(['assessed_produced_return', 'excluded_art195_return', 'removed_pis_cofins', 'deferred_fair_value'], '0.00000000');
    $expected = [...$zero, 'assessed_produced_return' => '100.00000000', 'deferred_fair_value' => '-200.00000000'];
    $references = $sources = [];
    foreach (['prior-return' => ['assessed_produced_return', '100.00000000', 'contract1'], 'prior-fair-value' => ['deferred_fair_value', '-200.00000000', 'prior-fv-document']] as $id => [$key, $amount, $document]) {
        $hash = CanonicalJson::hash($snapshot);
        $proof = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'current_period' => '2026-09', 'title' => $input['title'],
            'snapshot_hash' => $hash, 'financial_entry_id' => $id, 'retained_share' => '1'], 'ASSIGN.'.$id, '2026-08');
        $sources[] = ['snapshot' => $snapshot, 'financial_entry_id' => $id, 'retained_share' => '1', 'assignment_evidence' => $proof];
        $references[] = ['source_key' => CanonicalJson::hash(['supplier_id' => $ledger['supplier_id'], 'period' => '2026-08', 'document_id' => $document, 'financial_entry_id' => $id]),
            'snapshot_hash' => $hash, 'decision_hash' => $snapshot['result']['decision_hash'], 'snapshot_status' => $snapshot['result']['status'],
            'period' => '2026-08', 'entry_id' => $id, 'retained_share' => '1', 'available' => [...$zero, $key => $amount], 'retained' => [...$zero, $key => $amount],
            'assignment_evidence_hash' => $proof['source_hash']];
    }
    usort($references, static fn (array $a, array $b): int => $a['source_key'] <=> $b['source_key']);
    $input['recognition_history'] = ['closing_memory' => $closing, 'fiscal_sources' => $sources,
        'complete_evidence' => tvmCurrentProof(['scope' => $scope, 'sources' => $references, 'closing_memory_hash' => CanonicalJson::hash($closing), 'opening_fiscal_components' => $expected], 'COMPLETE_HISTORY', '2026-08')];
    $history = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $input['recognition_history'], $known, $historyRule);
    expect($history['status'])->toBe('RESOLVED')->and($history['opening_fiscal_components'])->toBe($expected);
    $policy = $rule['securities_realization']['analysis']['daily_memory_calculation'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($fixture['position'], $policy['series'], $known, $policy);
    expect($memory['status'])->toBe('RESOLVED');
    foreach ($fixture['independent_oracle'] as $key => $value) {
        if ($key !== 'daily_memory') {
            expect($memory[$key])->toBe($value);
        }
    }
    $input['recognition_inventory']['opening']['deferred_fair_value_realized'] = '-200';
    $input['recognition_inventory']['closing']['deferred_fair_value_realized'] = '-120';
    $sale = &$input['realizations'][0];
    $sale['components']['deferred_fair_value_realized'] = '-80';
    $sale['components']['new_produced_return_at_sale'] = '-60';
    $sale['components']['new_fair_value_change_at_sale'] = '0';
    $sale['history']['snapshot_id'] = $history['source_set_hash'];
    $sale['history']['deferred_fair_value_released'] = '-80';
    $common = ['supplier_id' => $ledger['supplier_id'], 'period' => '2026-09'];
    $sale['history_evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'idTitulo' => $input['title']['idTitulo'], 'cCta' => $input['title']['cCta'],
        'operation_id' => 'sale1', 'quantity' => '400.00000000', 'history' => $sale['history']], 'SALE_HISTORY', '2026-08');
    $saleMemory = null;
    foreach ($memory['daily_memory'] as $day) {
        foreach ($day['operations'] as $event) {
            if ($event['kind'] === 'SELL') {
                $saleMemory = ['date' => $day['date'], ...$event];
            }
        }
    }
    $sale['component_evidence'] = tvmCurrentProof([...$common, 'idTitulo' => $input['title']['idTitulo'], 'cCta' => $input['title']['cCta'],
        'sale' => $saleMemory, 'components' => $sale['components'], 'history_evidence_hash' => $sale['history_evidence']['source_hash']], 'SALE_COMPONENTS');
    unset($sale);
    $input['classification_evidence'] = tvmCurrentProof([...$common, 'title' => $input['title'], 'position_input_hash' => CanonicalJson::hash($input['position'])], 'CLASSIFICATION');
    $input['inventory_evidence'] = tvmCurrentProof([...$common, 'idTitulo' => $input['title']['idTitulo'], 'cCta' => $input['title']['cCta'],
        'position_input_hash' => CanonicalJson::hash($input['position']), 'inventory' => $input['recognition_inventory']], 'INVENTORY');
    $input['complete_evidence'] = tvmCurrentProof([...$common, 'position_input_hash' => CanonicalJson::hash($input['position']), 'title' => $input['title'],
        'realizations' => $input['realizations'], 'recognition_inventory' => $input['recognition_inventory'], 'inventory_evidence_hash' => $input['inventory_evidence']['source_hash']], 'COMPLETE');
    $detail['produced_curve_evidence'] = tvmCurrentProof([...$common, 'title' => $input['title'], 'daily_memory_hash' => CanonicalJson::hash($memory),
        'appropriation_excludes_fair_value' => true, 'curve_rows' => array_map(static fn (array $day): array => array_intersect_key($day, array_flip(['date', 'contract_curve_id', 'appropriation'])), $memory['daily_memory'])], 'CURVE');
    $detail['deduction_evidence'] = [];
    foreach (['deferred_fair_value_realized' => ['DEFERRED_FAIR_VALUE_REALIZED', '-80.00000000', '80.00', '411011701', 'LC214 art192 paragraph6; art187'],
        'new_produced_return_at_sale' => ['DISPOSAL_NEW_PRODUCED_RETURN', '-60.00000000', '60.00', '411011102', 'LC214 art192 paragraph1 II; caput III; art187']] as $key => [$kind, $signed, $amount, $code, $article]) {
        $component = ['component_id' => 'SALE:sale1:'.$key, 'component_kind' => $kind, 'signed_unrounded_amount' => $signed, 'amount' => $amount, 'bucket' => 'DEDUCTION', 'expected_codTrib' => $code, 'source_article' => $article];
        $conditions = ['operation_authorized' => true, 'within_operational_limits' => true, 'not_administrative_expense' => true, 'authorization_evidence_id' => 'SYNTHETIC.NEGATIVE.REALIZATION.AUTHORIZATION'];
        $detail['deduction_evidence'][] = ['component_id' => $component['component_id'], 'conditions' => $conditions,
            'independent_evidence' => tvmCurrentProof([...$common, 'title' => $input['title'], 'financial_entry_id' => 'tvm1', 'component' => $component,
                'conditions' => $conditions, 'analysis_input_hash' => CanonicalJson::hash($input)], 'LOSS.'.$key)];
    }
    unset($input, $detail);
    $ledger['entries'][4]['amount'] = '290.00';

    return tvmFairFixture('negative-history', sealTvmCurrentLedger($ledger), $version);
}

it('replays prior negative fair value and realizes its loss once with the proper D1101 code and separate authorization', function (): void {
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmNegativeFixture();
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($operation['base']['net_base'])->toBe('710.00')->and($projected['status'])->toBe('PROJECTED');
    $parent = $operation['base']['realization_bindings']['tvm1'];
    expect($parent['revenue'])->toBe('150.00')->and($parent['deductions'])->toBe('140.00')
        ->and($parent['analysis']['recognition_inventory']['closing']['deferred_fair_value_realized'])->toBe('-120');
    $book = array_column($month['book']['accounts'], null, 'cCta');
    expect($book['FV000']['vMovCred'])->toBe('80.00')->and($book['FV000']['vAjusteCred'])->toBe('80.00')->and($book['FV000']['vApur'])->toBe('80.00')
        ->and($book['fvloss000']['vMovDebt'])->toBe('80.00')->and($book['fvloss000']['vAjusteDebt'])->toBe('80.00')->and($book['fvloss000']['vApur'])->toBe('0.00');
    if (getenv('TVM_NEGATIVE_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-private-negative-history-realization';
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-negative-history-2026.10.07-28-draft8/negative-history-'.$suffix.'.json';
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
});

it('requires current authorization for the loss even after prior deferral is proven', function (): void {
    [, , $known, , $ledger] = tvmNegativeFixture();
    $rule = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.07-28-draft8.json'), true, 512, JSON_THROW_ON_ERROR)['financial_operation']['base'];
    $ledger['entries'][4]['securities_realization']['deduction_evidence'] = [];
    $result = tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known);
    expect($result['reason'])->toBe('RTC_TVM_CURRENT_LOSS_AUTHORIZATION_UNPROVEN');
});

it('rejects unsigned or forged historical valuation direction before producing a signed deferred trace', function (string $tamper, string $reason): void {
    $root = dirname(__DIR__, 3);
    $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-negative-history-2026.10.07-28-draft8/prior-negative-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.07-28-draft7.json'), true, 512, JSON_THROW_ON_ERROR)['financial_operation']['base'];
    $ledger = $scenario['facts']['rtc.financial_ledger'][0];
    match ($tamper) {
        'policy' => $rule['deferred_fair_value_sign_policy'] = 'UNKNOWN',
        'proof' => $ledger['entries'][1]['fair_value_sign_evidence']['qualified'] = false,
        'direction' => $ledger['entries'][1]['fair_value_nature'] = 'C',
        'metadata' => $ledger['entries'][1]['deferred_signed_amount'] = '-999.00',
        'identity' => $ledger['entries'][1]['security_identity'] = null,
    };
    $result = (new FinancialBaseEngine)->resolve($ledger, LocalDate::fromString('2026-08-31'), new DateTimeImmutable($scenario['knowledge_at']), $rule);
    expect($result['reason'])->toBe('RTC_FINANCIAL_BASE_'.$reason);
})->with([['policy', 'FAIR_VALUE_SIGN_POLICY_OR_IDENTITY_UNPROVEN'], ['proof', 'INDEPENDENT_FAIR_VALUE_SIGN_UNPROVEN'],
    ['direction', 'INDEPENDENT_FAIR_VALUE_SIGN_UNPROVEN'], ['metadata', 'RESERVED_DERIVED_ENTRY_METADATA'], ['identity', 'FAIR_VALUE_SIGN_POLICY_OR_IDENTITY_UNPROVEN']]);

it('preserves explicitly proved positive valuation direction without changing the current prior-period base', function (): void {
    $root = dirname(__DIR__, 3);
    $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-negative-history-2026.10.07-28-draft8/prior-negative-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.07-28-draft7.json'), true, 512, JSON_THROW_ON_ERROR)['financial_operation']['base'];
    $ledger = $scenario['facts']['rtc.financial_ledger'][0];
    $entry = &$ledger['entries'][1];
    $entry['fair_value_nature'] = $entry['fair_value_sign_evidence']['value']['fair_value_nature'] = 'C';
    unset($entry['fair_value_sign_evidence']['source_hash']);
    $entry['fair_value_sign_evidence']['source_hash'] = CanonicalJson::hash($entry['fair_value_sign_evidence']);
    unset($entry);
    $result = (new FinancialBaseEngine)->resolve($ledger, LocalDate::fromString('2026-08-31'), new DateTimeImmutable($scenario['knowledge_at']), $rule);
    expect($result['status'])->toBe('RESOLVED')->and($result['deferred'])->toBe('200.00')->and($result['net_base'])->toBe('100.00');
});

it('requires a historical policy that explicitly permits the signed source replay', function (): void {
    [, , $known, , $ledger] = tvmNegativeFixture();
    $rule = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.07-28-draft8.json'), true, 512, JSON_THROW_ON_ERROR)['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
    unset($rule['signed_private_deferral_policy']);
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $scope = ['supplier_id' => $ledger['supplier_id'], 'period' => '2026-09', 'title' => $input['title'], 'opening' => $input['position']['opening']];
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $input['recognition_history'], $known, $rule);
    expect($result['reason'])->toBe('RTC_TVM_HISTORY_SIGNED_PRIVATE_DEFERRAL_POLICY_UNPROVEN');
});
