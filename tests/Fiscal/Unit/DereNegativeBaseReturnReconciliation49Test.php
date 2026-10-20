<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseReturnReconciler;

/** Synthetic normalized return; no official reception or signature is claimed. @return array<string, mixed> */
function bcnReturn49(): array
{
    return ['supplier_id' => '11111111000191', 'period' => '2026-09', 'groups' => [
        ['codBC' => '1010', 'codBCNRaiz' => '1010I', 'positive_base' => '30.00', 'generated_negative' => '0.00',
            'vSaldoAnt' => '70.00', 'vUtilPer' => '30.00', 'vSaldoFinal' => '40.00',
            'detBCN' => [['codBCN' => '1010I20260600', 'perOrigem' => '2026-06', 'vOrigemIni' => '100.00',
                'vSaldoAnt' => '70.00', 'vUtilPer' => '30.00', 'vSaldoFinal' => '40.00']]],
        ['codBC' => '1010', 'codBCNRaiz' => '1010C', 'positive_base' => '0.00', 'generated_negative' => '20.00',
            'vSaldoAnt' => '80.00', 'vUtilPer' => '0.00', 'vSaldoFinal' => '100.00',
            'detBCN' => [
                ['codBCN' => '1010C20260600', 'perOrigem' => '2026-06', 'vOrigemIni' => '200.00',
                    'vSaldoAnt' => '80.00', 'vUtilPer' => '0.00', 'vSaldoFinal' => '80.00'],
                ['codBCN' => '1010C20260901', 'perOrigem' => '2026-09', 'vOrigemIni' => '20.00',
                    'vSaldoAnt' => '0.00', 'vUtilPer' => '0.00', 'vSaldoFinal' => '20.00'],
            ]],
    ]];
}

it('reconciles different IBS consumption and CBS generation without combining their stock', function (): void {
    $result = (new DereNegativeBaseReturnReconciler)->reconcile(bcnReturn49(), '11111111000191', '2026-09');
    expect($result['status'])->toBe('LOCAL_RETURN_RECONCILED')
        ->and(array_column($result['stock_after_return'], 'available_after_return', 'codBCN'))->toBe([
            '1010I20260600' => '40.00', '1010C20260600' => '80.00', '1010C20260901' => '20.00',
        ])->and($result['received_return_verified'])->toBeFalse()->and($result['expiry_qualified'])->toBeFalse()
        ->and($result['eligible_for_consumption'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
});

it('does not require origin detail for a proven zero aggregate', function (): void {
    $input = bcnReturn49();
    $input['groups'] = [['codBC' => '1005', 'codBCNRaiz' => '1005I', 'positive_base' => '50.00',
        'generated_negative' => '0.00', 'vSaldoAnt' => '0.00', 'vUtilPer' => '0.00', 'vSaldoFinal' => '0.00', 'detBCN' => []]];
    expect((new DereNegativeBaseReturnReconciler)->reconcile($input, '11111111000191', '2026-09')['status'])->toBe('LOCAL_RETURN_RECONCILED');
});

it('rejects incorrect identities conservation and reuse of current generated stock', function (string $mutation, string $reason): void {
    $input = bcnReturn49();
    switch ($mutation) {
        case 'supplier': $input['supplier_id'] = '22222222000191';
            break;
        case 'period': $input['period'] = '2026-08';
            break;
        case 'base': $input['groups'][0]['codBC'] = '1005';
            break;
        case 'duplicate-root': $input['groups'][] = $input['groups'][0];
            break;
        case 'missing-origin': $input['groups'][0]['detBCN'] = [];
            break;
        case 'cbs-in-ibs': $input['groups'][0]['detBCN'][0]['codBCN'] = '1010C20260600';
            break;
        case 'origin-key-period': $input['groups'][0]['detBCN'][0]['perOrigem'] = '2026-07';
            break;
        case 'future-origin': $input['groups'][0]['detBCN'][0]['perOrigem'] = '2026-10';
            $input['groups'][0]['detBCN'][0]['codBCN'] = '1010I20261000';
            break;
        case 'duplicate-origin': $input['groups'][0]['detBCN'][] = $input['groups'][0]['detBCN'][0];
            break;
        case 'excess-consumption': $input['groups'][0]['positive_base'] = '29.99';
            break;
        case 'both-signs': $input['groups'][1]['positive_base'] = '1.00';
            break;
        case 'initial-exceeded': $input['groups'][0]['detBCN'][0]['vOrigemIni'] = '69.99';
            break;
        case 'detail-final': $input['groups'][0]['detBCN'][0]['vSaldoFinal'] = '40.01';
            break;
        case 'current-use': $input['groups'][1]['detBCN'][1]['vUtilPer'] = '1.00';
            break;
        case 'current-previous': $input['groups'][1]['detBCN'][1]['vSaldoAnt'] = '1.00';
            break;
        case 'missing-generation': $input['groups'][1]['generated_negative'] = '0.00';
            break;
        case 'group-final': $input['groups'][1]['vSaldoFinal'] = '99.99';
            break;
        case 'fractional-cent': $input['groups'][0]['detBCN'][0]['vSaldoFinal'] = '40.001';
            break;
    }
    $result = (new DereNegativeBaseReturnReconciler)->reconcile($input, '11111111000191', '2026-09');
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_BCN_RETURN_'.$reason)
        ->and($result['eligible_for_consumption'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
})->with([
    ['supplier', 'SCOPE_CONFLICT'], ['period', 'SCOPE_CONFLICT'], ['base', 'ROOT_OR_FORMING_BASE_CONFLICT'],
    ['duplicate-root', 'ROOT_OR_FORMING_BASE_CONFLICT'], ['missing-origin', 'AGGREGATE_CONSERVATION_OR_COVERAGE_CONFLICT'],
    ['cbs-in-ibs', 'ORIGIN_IDENTITY_OR_PERIOD_CONFLICT'], ['origin-key-period', 'ORIGIN_IDENTITY_OR_PERIOD_CONFLICT'],
    ['future-origin', 'ORIGIN_IDENTITY_OR_PERIOD_CONFLICT'], ['duplicate-origin', 'ORIGIN_IDENTITY_OR_PERIOD_CONFLICT'],
    ['excess-consumption', 'CURRENT_BASE_OR_CONSUMPTION_CONFLICT'], ['both-signs', 'CURRENT_BASE_OR_CONSUMPTION_CONFLICT'],
    ['initial-exceeded', 'ORIGIN_BALANCE_EXCEEDED'], ['detail-final', 'DETAIL_CONSERVATION_CONFLICT'],
    ['current-use', 'CURRENT_ORIGIN_CONFLICT'], ['current-previous', 'CURRENT_ORIGIN_CONFLICT'],
    ['missing-generation', 'AGGREGATE_CONSERVATION_OR_COVERAGE_CONFLICT'], ['group-final', 'AGGREGATE_CONSERVATION_OR_COVERAGE_CONFLICT'],
    ['fractional-cent', 'DETAIL_AMOUNT_INVALID'],
]);
