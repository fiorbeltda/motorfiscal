<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Brick\Math\BigDecimal;

/** Synthetic evidence envelopes bind inputs; numerical expectations come from the independent frozen Decimal fixtures. */
function realizationProof(array $value, string $id, string $start = '2026-09-01', string $end = '2026-09-30'): array
{
    $p = ['qualified' => true, 'evidence_id' => 'SYNTHETIC.DRAFT.'.$id, 'supplier_id' => '11111111000191',
        'valid_from' => $start, 'valid_to' => $end, 'observed_on' => '2026-10-06',
        'scope' => 'SYNTHETIC_COMPONENT_RECONCILIATION_ONLY_NOT_FISCAL_ACCEPTANCE', 'value' => $value];
    $p['source_hash'] = CanonicalJson::hash($p);

    return $p;
}

function realizationSeal(array $input): array
{
    $p = $input['position'];
    $common = ['supplier_id' => $p['supplier_id'], 'period' => $p['period']];
    $input['classification_evidence'] = realizationProof([...$common, 'title' => $input['title'], 'position_input_hash' => CanonicalJson::hash($p)], 'CLASSIFICATION');
    $input['inventory_evidence'] = realizationProof([...$common, 'idTitulo' => $p['idTitulo'], 'cCta' => $p['cCta'],
        'position_input_hash' => CanonicalJson::hash($p), 'inventory' => $input['recognition_inventory']], 'INVENTORY');
    $input['complete_evidence'] = realizationProof([...$common, 'position_input_hash' => CanonicalJson::hash($p), 'title' => $input['title'],
        'realizations' => $input['realizations'], 'recognition_inventory' => $input['recognition_inventory'],
        'inventory_evidence_hash' => $input['inventory_evidence']['source_hash']], 'COMPLETE');

    return $input;
}

function realizationFixture(string $case): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $k = (new FiscalEngineFactory)->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.06-26.json');
    $v = json_decode(file_get_contents($root.'scenarios/tvm-realization-draft-2026.10.06-28/'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $known = new DateTimeImmutable($k['documents']['bundle_manifest']['knowledge_available_at']);
    $daily = $k['documents']['rtc_code_catalog']['dere_debt_daily_memory_calculation'];
    $rule = ['contract' => FinancialSecuritiesRealizationAnalyzer::CONTRACT, 'status' => 'ACTIVE',
        'published_at' => $known->format(DATE_ATOM), 'source_hash' => $daily['source_hash'], 'daily_memory_calculation' => $daily];
    $p = $v['position'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($p, $daily['series'], $known, $daily);
    expect($memory['status'])->toBe('RESOLVED');
    foreach ($v['independent_daily_oracle'] as $key => $expected) {
        if ($key !== 'daily_memory') {
            expect($memory[$key])->toBe($expected);
        }
    }
    $sales = [];
    foreach ($memory['daily_memory'] as $day) {
        foreach ($day['operations'] as $operation) {
            if ($operation['kind'] === 'SELL') {
                $sales[] = ['date' => $day['date'], ...$operation];
            }
        }
    }
    $declarations = [];
    foreach ($sales as $sale) {
        $components = $v['components_by_sale'][$sale['id']] ?? $v['components'];
        $history = ['snapshot_id' => 'SYNTHETIC.PREVIOUS.SNAPSHOT', 'period' => '2026-08',
            'previously_recognized_produced_return' => $components['prior_produced_return'],
            'previously_assessed_produced_return' => $components['prior_produced_return'],
            'previously_excluded_art195_return' => '0',
            'deferred_fair_value_released' => $components['deferred_fair_value_realized'], 'fair_value_subaccount' => 'FV000'];
        $historyProof = realizationProof(['supplier_id' => $p['supplier_id'], 'idTitulo' => $p['idTitulo'], 'cCta' => $p['cCta'],
            'operation_id' => $sale['id'], 'quantity' => $sale['quantity'], 'history' => $history], 'HISTORY', '2026-08-01', '2026-08-31');
        $componentsProof = realizationProof(['supplier_id' => $p['supplier_id'], 'period' => $p['period'],
            'idTitulo' => $p['idTitulo'], 'cCta' => $p['cCta'], 'sale' => $sale, 'components' => $components,
            'history_evidence_hash' => $historyProof['source_hash']], 'COMPONENTS');
        $declarations[] = ['operation_id' => $sale['id'], 'components' => $components, 'history' => $history,
            'history_evidence' => $historyProof, 'component_evidence' => $componentsProof];
    }
    $title = ['idTitulo' => $p['idTitulo'], 'cCta' => $p['cCta'], 'CNPJDevedor' => '11222333000181', 'currency' => 'BRL',
        'creditor_in_financial_specific_regime' => true, 'instrument_type' => $v['public'] ? 'CORPORATE_DEBT_PUBLIC_OFFER' : 'CORPORATE_DEBT_PRIVATE',
        'holding_route' => 'DIRECT', 'public_offer_regulator' => 'CVM', 'issuer_regime' => 'REGULAR', 'issuer_in_financial_specific_regime' => false];
    $input = realizationSeal(['position' => $p, 'title' => $title, 'recognition_inventory' => $v['recognition_inventory'],
        'realizations' => $declarations]);

    return compact('input', 'rule', 'known', 'v');
}

it('reconciles economic accounting and previously recognized components without activating tax totalization', function (string $case): void {
    $f = realizationFixture($case);
    $r = (new FinancialSecuritiesRealizationAnalyzer)->analyze($f['input'], $f['rule'], $f['known']);
    expect($r['status'])->toBe('RECONCILED');
    foreach ($f['v']['expectation'] as $key => $expected) {
        expect(BigDecimal::of($r['realizations'][0][$key])->isEqualTo($expected))->toBeTrue();
    }
    expect($r['recognition_inventory']['closing'])->toBe($f['v']['recognition_inventory']['closing'])
        ->and($r['financial_base_integration_performed'])->toBeFalse()->and($r['xml_projection_performed'])->toBeFalse()
        ->and($r['tax_totalization_performed'])->toBeFalse()->and($r['production_ready'])->toBeFalse();
})->with(['private-accounting-gain', 'public-produced-return', 'prior-return', 'deferred-fair-value',
    'mixed-components', 'current-curve', 'complete-prior-realization', 'complete-loss']);

it('rejects contradictory inventory even when its independent envelope has been resealed', function (string $mutation, string $reason): void {
    $f = realizationFixture('prior-return');
    $i = $f['input'];
    match ($mutation) {
        'missing' => $i['recognition_inventory'] = [],
        'opening' => $i['recognition_inventory']['opening']['prior_produced_return'] = '200',
        'closing' => $i['recognition_inventory']['closing']['prior_produced_return'] = '100',
        'reused' => [$i['recognition_inventory']['opening']['prior_produced_return'], $i['recognition_inventory']['opening']['deferred_fair_value_realized']] = ['20', '80'],
        'unknown' => $i['recognition_inventory']['other_releases'][] = ['operation_id' => 'unknown', 'components' => $i['recognition_inventory']['opening']],
    };
    $r = (new FinancialSecuritiesRealizationAnalyzer)->analyze(realizationSeal($i), $f['rule'], $f['known']);
    expect($r['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason)->and($r['production_ready'])->toBeFalse();
})->with([
    ['missing', 'RECOGNITION_INVENTORY_MISSING'], ['opening', 'OPENING_RECOGNITION_INVENTORY_CONFLICT'],
    ['closing', 'CLOSING_RECOGNITION_INVENTORY_CONFLICT'], ['reused', 'RECOGNITION_COMPONENT_REUSED_OR_UNAVAILABLE'],
    ['unknown', 'OTHER_RECOGNITION_RELEASE_UNKNOWN'],
]);

it('requires independent evidence and exact sale coverage', function (string $mutation, string $reason): void {
    $f = realizationFixture('private-accounting-gain');
    $i = $f['input'];
    match ($mutation) {
        'inventory-proof' => $i['inventory_evidence']['qualified'] = false,
        'history-proof' => $i['realizations'][0]['history_evidence']['qualified'] = false,
        'component-proof' => $i['realizations'][0]['component_evidence']['qualified'] = false,
        'duplicate' => $i['realizations'][] = $i['realizations'][0],
        'coverage' => $i['realizations'] = [],
        'title' => $i['title']['CNPJDevedor'] = '11222333000180',
        'scope' => $i['complete_evidence']['value']['period'] = '2026-08',
    };
    $r = (new FinancialSecuritiesRealizationAnalyzer)->analyze($i, $f['rule'], $f['known']);
    expect($r['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason);
})->with([
    ['inventory-proof', 'INDEPENDENT_RECOGNITION_INVENTORY_UNPROVEN'], ['history-proof', 'INDEPENDENT_HISTORY_UNPROVEN'],
    ['component-proof', 'INDEPENDENT_COMPONENTS_UNPROVEN'], ['duplicate', 'SALE_UNKNOWN_OR_DUPLICATE'],
    ['coverage', 'SALE_COVERAGE_INCOMPLETE'], ['title', 'TITLE_SCOPE_CONFLICT'], ['scope', 'COMPLETE_SCOPE_UNPROVEN'],
]);

it('conserves recognition inventory across multiple sales coupons and amortizations without netting separate sale components', function (string $case): void {
    $f = realizationFixture($case);
    $r = (new FinancialSecuritiesRealizationAnalyzer)->analyze($f['input'], $f['rule'], $f['known']);
    expect($r['status'])->toBe('RECONCILED')->and($r['production_ready'])->toBeFalse();
    foreach ($r['realizations'] as $row) {
        foreach ($f['v']['expectations_by_sale'][$row['operation_id']] as $key => $expected) {
            expect(BigDecimal::of($row[$key])->isEqualTo($expected))->toBeTrue();
        }
    }
    expect($r['recognition_inventory']['closing'])->toBe($f['v']['recognition_inventory']['closing']);
    if ($case === 'offsetting-sales') {
        expect($r['realizations'][0]['ordinary_current_component'])->toBe('200')
            ->and($r['realizations'][1]['ordinary_current_component'])->toBe('-200');
    }
})->with(['offsetting-sales', 'two-sales-history', 'coupon-and-sale', 'amortization-and-sale']);

it('distinguishes prior taxable and art195 excluded portions instead of treating the gross historical return as previously taxed', function (bool $public, string $assessed, string $excluded, string $status): void {
    $f = realizationFixture('prior-return');
    $i = $f['input'];
    $i['title']['instrument_type'] = $public ? 'CORPORATE_DEBT_PUBLIC_OFFER' : 'CORPORATE_DEBT_PRIVATE';
    $d = &$i['realizations'][0];
    $d['history']['previously_assessed_produced_return'] = $assessed;
    $d['history']['previously_excluded_art195_return'] = $excluded;
    $hp = $d['history_evidence'];
    $hp['value']['history'] = $d['history'];
    unset($hp['source_hash']);
    $hp['source_hash'] = CanonicalJson::hash($hp);
    $d['history_evidence'] = $hp;
    $cp = $d['component_evidence'];
    $cp['value']['history_evidence_hash'] = $hp['source_hash'];
    unset($cp['source_hash']);
    $cp['source_hash'] = CanonicalJson::hash($cp);
    $d['component_evidence'] = $cp;
    unset($d);
    $r = (new FinancialSecuritiesRealizationAnalyzer)->analyze(realizationSeal($i), $f['rule'], $f['known']);
    expect($r['status'])->toBe($status);
    if ($status === 'RECONCILED') {
        expect($r['realizations'][0]['previously_assessed_return'])->toBe($assessed)
            ->and($r['realizations'][0]['previously_excluded_art195_return'])->toBe($excluded)
            ->and($r['historical_fiscal_snapshot_replay_performed'])->toBeFalse();
    } else {
        expect($r['reason'])->toBe('RTC_TVM_REALIZATION_PRIOR_ASSESSED_AND_EXCLUDED_RETURN_CONFLICT');
    }
})->with([
    [true, '30', '10', 'RECONCILED'], [true, '0', '40', 'RECONCILED'],
    [false, '30', '10', 'ACTION_REQUIRED'], [true, '30', '20', 'ACTION_REQUIRED'],
    [true, '50', '-10', 'ACTION_REQUIRED'],
]);
