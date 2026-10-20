<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialBaseEngine;
use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use Brick\Math\BigDecimal;

function tvmCurrentFixture(): array
{
    $root = dirname(__DIR__, 3);
    $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.06-28-draft.json'), true, 512, JSON_THROW_ON_ERROR);

    return [$scenario['facts']['rtc.financial_ledger'][0], $catalog['financial_operation']['base'], new DateTimeImmutable($scenario['knowledge_at'])];
}

function sealTvmCurrentLedger(array $ledger): array
{
    $unsigned = $ledger;
    foreach ($unsigned['entries'] as &$entry) {
        unset($entry['securities_realization']['qualification_evidence']);
    }
    unset($entry);
    foreach ($ledger['entries'] as &$entry) {
        if (($entry['kind'] ?? null) !== 'QUALIFIED_SECURITIES_REALIZATION') {
            continue;
        }
        $proof = &$entry['securities_realization']['qualification_evidence'];
        $proof['value'] = ['supplier_id' => $ledger['supplier_id'], 'period_start' => $ledger['period_start'], 'period_end' => $ledger['period_end'],
            'financial_entry_id' => $entry['id'], 'document_id' => $entry['document_id'], 'amount' => $entry['amount'],
            'analysis_input_hash' => CanonicalJson::hash($entry['securities_realization']['analysis_input']), 'ledger_input_hash' => CanonicalJson::hash($unsigned)];
        unset($proof['source_hash']);
        $proof['source_hash'] = CanonicalJson::hash($proof);
        unset($proof);
    }
    unset($entry);

    return $ledger;
}

function tvmCurrentBase(array $ledger, array $rule, DateTimeImmutable $known): array
{
    return (new FinancialBaseEngine)->resolve($ledger, LocalDate::fromString($ledger['period_end']), $known, $rule);
}

function tvmCurrentProof(array $value, string $id, string $period = '2026-09'): array
{
    $start = $period.'-01';
    $proof = ['qualified' => true, 'evidence_id' => 'SYNTHETIC.CURRENT.'.$id, 'supplier_id' => '11111111000191',
        'valid_from' => $start, 'valid_to' => (new DateTimeImmutable($start))->format('Y-m-t'), 'observed_on' => '2026-10-06',
        'scope' => 'SYNTHETIC_INDEPENDENT_CURRENT_COMPONENT_QUALIFICATION', 'value' => $value];
    $proof['source_hash'] = CanonicalJson::hash($proof);

    return $proof;
}

function tvmCurrentFirstFixture(string $name, ?array $suppliedFixture = null, ?array $suppliedRule = null): array
{
    [$ledger, $rule, $known] = tvmCurrentFixture();
    if ($suppliedRule !== null) {
        $rule = $suppliedRule;
        $known = new DateTimeImmutable($rule['securities_realization']['published_at']);
    }
    $fixture = $suppliedFixture ?? json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $position = $fixture['position'];
    $period = $position['period'];
    $priorPeriod = (new DateTimeImmutable($period.'-01'))->modify('-1 month')->format('Y-m');
    $ledger['period_start'] = $period.'-01';
    $ledger['period_end'] = (new DateTimeImmutable($period.'-01'))->format('Y-m-t');
    foreach ($ledger['entries'] as &$periodEntry) {
        if ($period !== '2026-09') {
            $periodEntry['recognized_on'] = $ledger['period_end'];
        }
    }
    unset($periodEntry);
    $policy = $rule['securities_realization']['analysis']['daily_memory_calculation'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], $known, $policy);
    expect($memory['status'])->toBe('RESOLVED', $memory['reason']);
    foreach ($fixture['independent_oracle'] as $key => $value) {
        if ($key !== 'daily_memory') {
            expect($memory[$key])->toBe($value);
        }
    }
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    if (isset($fixture['title'])) {
        $input['title'] = $fixture['title'];
    }
    unset($input['recognition_history']);
    $input['position'] = $position;
    $empty = array_fill_keys(['prior_produced_return', 'current_produced_return_already_in_curve', 'deferred_fair_value_realized', 'rounding_bridge'], '0');
    $input['recognition_inventory'] = ['opening' => $empty, 'closing' => $empty, 'other_releases' => []];
    if (isset($fixture['recognition_inventory'])) {
        $input['recognition_inventory'] = $fixture['recognition_inventory'];
    }
    $input['realizations'] = [];
    foreach ($memory['daily_memory'] as $day) {
        foreach ($day['operations'] as $operation) {
            if ($operation['kind'] !== 'SELL') {
                continue;
            }
            $sale = ['date' => $day['date'], ...$operation];
            $history = ['snapshot_id' => 'FIRST_ACQUISITION_NO_PRIOR_SNAPSHOT', 'period' => $priorPeriod,
                'previously_recognized_produced_return' => '0', 'previously_assessed_produced_return' => '0',
                'previously_excluded_art195_return' => '0', 'previously_removed_pis_cofins' => '0', 'deferred_fair_value_released' => '0'];
            if (! BigDecimal::of($fixture['components'][$operation['id']]['new_fair_value_change_at_sale'])->isZero()) {
                $history['fair_value_subaccount'] = 'FV000';
            }
            $hp = tvmCurrentProof(['supplier_id' => $position['supplier_id'], 'idTitulo' => $position['idTitulo'], 'cCta' => $position['cCta'],
                'operation_id' => $operation['id'], 'quantity' => $operation['quantity'], 'history' => $history], 'NO_PRIOR', $priorPeriod);
            $components = $fixture['components'][$operation['id']];
            $cp = tvmCurrentProof(['supplier_id' => $position['supplier_id'], 'period' => $position['period'], 'idTitulo' => $position['idTitulo'],
                'cCta' => $position['cCta'], 'sale' => $sale, 'components' => $components, 'history_evidence_hash' => $hp['source_hash']], 'SALE', $period);
            $input['realizations'][] = ['operation_id' => $operation['id'], 'components' => $components, 'history' => $history,
                'history_evidence' => $hp, 'component_evidence' => $cp];
            if (isset($fixture['sale_rounding_oracle'])) {
                $input['realizations'][array_key_last($input['realizations'])]['book_release_rounding_evidence'] = tvmCurrentProof([
                    'supplier_id' => $position['supplier_id'], 'period' => $position['period'], 'title' => $input['title'], 'operation' => $sale,
                    'daily_memory_hash' => CanonicalJson::hash($memory), 'method' => 'PROVED_AVERAGE_CARRYING_RELEASE_HALF_EVEN_8_2',
                    ...$fixture['sale_rounding_oracle'], 'normative_homologation_claimed' => false,
                ], 'AVERAGE_BOOK_RELEASE', $period);
            }
        }
    }
    $common = ['supplier_id' => $position['supplier_id'], 'period' => $position['period']];
    $input['classification_evidence'] = tvmCurrentProof([...$common, 'title' => $input['title'], 'position_input_hash' => CanonicalJson::hash($position)], 'CLASSIFICATION', $period);
    $input['inventory_evidence'] = tvmCurrentProof([...$common, 'idTitulo' => $position['idTitulo'], 'cCta' => $position['cCta'],
        'position_input_hash' => CanonicalJson::hash($position), 'inventory' => $input['recognition_inventory']], 'INVENTORY', $period);
    $input['complete_evidence'] = tvmCurrentProof([...$common, 'position_input_hash' => CanonicalJson::hash($position), 'title' => $input['title'],
        'realizations' => $input['realizations'], 'recognition_inventory' => $input['recognition_inventory'],
        'inventory_evidence_hash' => $input['inventory_evidence']['source_hash']], 'COMPLETE', $period);
    $detail = &$ledger['entries'][4]['securities_realization'];
    if ($period !== '2026-09') {
        $detail['qualification_evidence']['valid_from'] = $ledger['period_start'];
        $detail['qualification_evidence']['valid_to'] = $ledger['period_end'];
    }
    $detail['analysis_input'] = $input;
    $detail['produced_curve_evidence'] = tvmCurrentProof([...$common, 'title' => $input['title'], 'daily_memory_hash' => CanonicalJson::hash($memory),
        'appropriation_excludes_fair_value' => true, 'curve_rows' => array_map(static fn (array $row): array => array_intersect_key($row,
            array_flip(['date', 'contract_curve_id', 'appropriation'])), $memory['daily_memory'])], 'CURVE', $period);
    $detail['deduction_evidence'] = [];
    foreach ($fixture['curve_deduction_components'] ?? [] as $component) {
        $conditions = ['operation_authorized' => true, 'within_operational_limits' => true, 'not_administrative_expense' => true,
            'authorization_evidence_id' => 'SYNTHETIC.CURVE.LOSS.AUTHORIZATION'];
        $detail['deduction_evidence'][] = ['component_id' => $component['component_id'], 'conditions' => $conditions,
            'independent_evidence' => tvmCurrentProof([...$common, 'title' => $input['title'], 'financial_entry_id' => 'tvm1',
                'component' => $component, 'conditions' => $conditions, 'analysis_input_hash' => CanonicalJson::hash($input)], 'CURVE_LOSS')];
    }
    foreach ($fixture['components'] as $operationId => $parts) {
        foreach (['new_produced_return_at_sale' => 'DISPOSAL_NEW_PRODUCED_RETURN', 'new_fair_value_change_at_sale' => 'NEW_FAIR_VALUE_REALIZED'] as $key => $kind) {
            if (! BigDecimal::of($parts[$key])->isNegative()) {
                continue;
            }
            $amount = BigDecimal::of($parts[$key]);
            $component = ['component_id' => 'SALE:'.$operationId.':'.$key, 'component_kind' => $kind,
                'signed_unrounded_amount' => (string) $amount->toScale(8), 'amount' => (string) $amount->abs()->toScale(2), 'bucket' => 'DEDUCTION', 'expected_codTrib' => '411011102',
                'source_article' => $key === 'new_produced_return_at_sale' ? 'LC214 art192 paragraph1 II; caput III; art187' : 'LC214 art192 paragraph6; art187'];
            $conditions = ['operation_authorized' => true, 'within_operational_limits' => true, 'not_administrative_expense' => true,
                'authorization_evidence_id' => 'SYNTHETIC.OPERATION.AUTHORIZATION'];
            $detail['deduction_evidence'][] = ['component_id' => $component['component_id'], 'conditions' => $conditions,
                'independent_evidence' => tvmCurrentProof([...$common, 'title' => $input['title'], 'financial_entry_id' => 'tvm1',
                    'component' => $component, 'conditions' => $conditions, 'analysis_input_hash' => CanonicalJson::hash($input)], 'LOSS')];
        }
    }
    unset($detail);
    $ledger['entries'][4]['amount'] = (string) BigDecimal::of($fixture['expected']['revenue'])->plus($fixture['expected']['deductions'])->toScale(2);

    return [sealTvmCurrentLedger($ledger), $rule, $known, $fixture];
}
