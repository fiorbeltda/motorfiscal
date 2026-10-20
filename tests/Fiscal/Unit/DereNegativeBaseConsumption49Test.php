<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseConsumptionPlanner;

/** Synthetic previous closing and current request; no government receipt. @return array<string, mixed> */
function bcnConsumption49(): array
{
    $groups = [];
    foreach (['I', 'C'] as $tax) {
        $groups[] = ['codBC' => '1010', 'codBCNRaiz' => '1010'.$tax, 'positive_base' => '0.00',
            'generated_negative' => '0.00', 'vSaldoAnt' => '100.00', 'vUtilPer' => '0.00', 'vSaldoFinal' => '100.00',
            'detBCN' => [
                ['codBCN' => '1010'.$tax.'20260700', 'perOrigem' => '2026-07', 'vOrigemIni' => '60.00',
                    'vSaldoAnt' => '60.00', 'vUtilPer' => '0.00', 'vSaldoFinal' => '60.00'],
                ['codBCN' => '1010'.$tax.'20260600', 'perOrigem' => '2026-06', 'vOrigemIni' => '40.00',
                    'vSaldoAnt' => '40.00', 'vUtilPer' => '0.00', 'vSaldoFinal' => '40.00'],
            ]];
    }

    return ['prior' => ['supplier_id' => '11111111000191', 'period' => '2026-09', 'groups' => $groups],
        'parameters' => ['infoBCN' => [
            ['codBCNRaiz' => '1010I', 'usarBCNAcum' => '1', 'metodoAproveit' => '0'],
            ['codBCNRaiz' => '1010C', 'usarBCNAcum' => '1', 'metodoAproveit' => '1'],
        ], 'detBCNeg' => [['codBCN' => '1010C20260700', 'vUsarBCN' => '90.00']]],
        'bases' => ['1010I' => '50.00', '1010C' => '20.00']];
}

/** @param array<string, mixed> $fixture @return array<string, mixed> */
function planBcn49(array $fixture, string $period = '2026-10'): array
{
    return (new DereNegativeBaseConsumptionPlanner)->plan($fixture['prior'], $fixture['parameters'],
        $fixture['bases'], '11111111000191', $period);
}

it('uses oldest IBS stock first and limits an independent CBS manual request with an alert', function (): void {
    $result = planBcn49(bcnConsumption49());
    expect($result['status'])->toBe('LOCAL_CONSUMPTION_PLANNED')->and($result['plans'][0]['deduction'])->toBe('50.00')
        ->and(array_column($result['plans'][0]['allocations'], 'used', 'codBCN'))->toBe([
            '1010I20260600' => '40.00', '1010I20260700' => '10.00',
        ])->and($result['plans'][0]['allocations'][1]['remaining'])->toBe('50.00')
        ->and($result['plans'][1]['deduction'])->toBe('20.00')->and($result['plans'][1]['allocations'][0]['remaining'])->toBe('40.00')
        ->and($result['warnings'])->toBe([['code' => 'AVISO_CONFERIR_SALDO_BCN', 'codBCN' => '1010C20260700', 'requested' => '90.00', 'available' => '60.00']])
        ->and($result['received_return_verified'])->toBeFalse()->and($result['eligible_for_posting'])->toBeFalse();
});

it('uses an omitted manual cap as available stock without changing the requested options', function (): void {
    $fixture = bcnConsumption49();
    unset($fixture['parameters']['detBCNeg'][0]['vUsarBCN']);
    $fixture['bases']['1010C'] = '100.00';
    $result = planBcn49($fixture);
    expect($result['status'])->toBe('LOCAL_CONSUMPTION_PLANNED')->and($result['plans'][1]['deduction'])->toBe('60.00')
        ->and($result['plans'][1]['assessed_base'])->toBe('40.00')->and($result['warnings'])->toBe([]);
});

it('preserves explicit opt out and zero positive bases without consuming stock', function (): void {
    $fixture = bcnConsumption49();
    $fixture['parameters']['infoBCN'][0] = ['codBCNRaiz' => '1010I', 'usarBCNAcum' => '0'];
    $fixture['bases']['1010C'] = '0.00';
    $result = planBcn49($fixture);
    expect($result['status'])->toBe('LOCAL_CONSUMPTION_PLANNED')->and($result['plans'][0]['deduction'])->toBe('0.00')
        ->and($result['plans'][0]['allocations'])->toBe([])->and($result['plans'][1]['deduction'])->toBe('0.00');
});

it('supports multiple manual origins when the positive base covers their combined requested amount', function (): void {
    $fixture = bcnConsumption49();
    $fixture['parameters']['detBCNeg'] = [['codBCN' => '1010C20260700', 'vUsarBCN' => '10.00'],
        ['codBCN' => '1010C20260600', 'vUsarBCN' => '15.00']];
    $fixture['bases']['1010C'] = '100.00';
    $result = planBcn49($fixture);
    expect($result['status'])->toBe('LOCAL_CONSUMPTION_PLANNED')->and($result['plans'][1]['deduction'])->toBe('25.00')
        ->and($result['plans'][1]['assessed_base'])->toBe('75.00')
        ->and(array_column($result['plans'][1]['allocations'], 'remaining', 'codBCN'))->toBe([
            '1010C20260700' => '50.00', '1010C20260600' => '25.00',
        ]);
});

it('limits automatic deduction to all available stock and preserves the uncovered positive base', function (): void {
    $fixture = bcnConsumption49();
    $fixture['bases']['1010I'] = '150.00';
    $result = planBcn49($fixture);
    expect($result['plans'][0]['deduction'])->toBe('100.00')->and($result['plans'][0]['assessed_base'])->toBe('50.00')
        ->and(array_column($result['plans'][0]['allocations'], 'remaining'))->toBe(['0.00', '0.00']);
});

it('preserves cents exactly in automatic and manual consumption', function (): void {
    $fixture = bcnConsumption49();
    $fixture['bases'] = ['1010I' => '50.01', '1010C' => '0.01'];
    $fixture['parameters']['detBCNeg'][0]['vUsarBCN'] = '0.01';
    $result = planBcn49($fixture);
    expect($result['plans'][0]['allocations'][1]['used'])->toBe('10.01')
        ->and($result['plans'][0]['allocations'][1]['remaining'])->toBe('49.99')
        ->and($result['plans'][1]['allocations'][0]['remaining'])->toBe('59.99');
});

it('does not require an expiry decision when the request cannot consume anything', function (): void {
    $fixture = bcnConsumption49();
    $fixture['prior']['period'] = '2031-05';
    $fixture['bases'] = ['1010I' => '0.00', '1010C' => '0.00'];
    $result = planBcn49($fixture, '2031-06');
    expect($result['status'])->toBe('LOCAL_CONSUMPTION_PLANNED')
        ->and(array_column($result['plans'], 'deduction'))->toBe(['0.00', '0.00'])
        ->and($result['eligible_for_posting'])->toBeFalse();
});

it('refuses gaps unknown origins ambiguous manual allocation and expiry boundary', function (string $mutation, string $reason): void {
    $fixture = bcnConsumption49();
    $period = '2026-10';
    switch ($mutation) {
        case 'gap': $fixture['prior']['period'] = '2026-08';
            break;
        case 'missing-base': unset($fixture['bases']['1010C']);
            break;
        case 'wrong-base': $fixture['bases']['1005I'] = '10.00';
            break;
        case 'negative-base': $fixture['bases']['1010I'] = '-1.00';
            break;
        case 'unknown-origin': $fixture['parameters']['detBCNeg'][0]['codBCN'] = '1010C20260800';
            break;
        case 'ambiguous-manual': $fixture['parameters']['detBCNeg'][] = ['codBCN' => '1010C20260600', 'vUsarBCN' => '10.00'];
            break;
        case 'expiry': $fixture['prior']['period'] = '2031-05';
            $period = '2031-06';
            break;
        case 'versions': $fixture['prior']['groups'][0]['detBCN'][0]['perOrigem'] = '2026-06';
            $fixture['prior']['groups'][0]['detBCN'][0]['codBCN'] = '1010I20260601';
            break;
    }
    $result = planBcn49($fixture, $period);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_BCN_CONSUMPTION_'.$reason)
        ->and($result['production_ready'])->toBeFalse();
})->with([
    ['gap', 'PRIOR_MONTH_CHAIN_REQUIRED'], ['missing-base', 'ROOT_STOCK_OR_CURRENT_BASE_MISSING'],
    ['wrong-base', 'CURRENT_POSITIVE_BASE_SCOPE_OR_AMOUNT_INVALID'], ['negative-base', 'CURRENT_POSITIVE_BASE_SCOPE_OR_AMOUNT_INVALID'],
    ['unknown-origin', 'REQUESTED_ORIGIN_NOT_IN_PRIOR_STOCK'], ['ambiguous-manual', 'MANUAL_ALLOCATION_PRIORITY_REQUIRED'],
    ['expiry', 'EXPIRY_BOUNDARY_AND_CALENDAR_REQUIRED'], ['versions', 'ORIGIN_VERSION_RECONCILIATION_REQUIRED'],
]);
