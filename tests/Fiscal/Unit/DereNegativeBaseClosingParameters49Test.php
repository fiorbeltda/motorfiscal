<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseClosingParametersValidator;

/** Synthetic D1199 requests; these identifiers are not claims of received D9199 returns. @return array<string, mixed> */
function bcnParameters49(): array
{
    return ['infoBCN' => [
        ['codBCNRaiz' => '1010I', 'usarBCNAcum' => '1', 'metodoAproveit' => '1'],
        ['codBCNRaiz' => '1010C', 'usarBCNAcum' => '1', 'metodoAproveit' => '0'],
    ], 'detBCNeg' => [['codBCN' => '1010I20260600', 'vUsarBCN' => '20.00']]];
}

it('preserves separate IBS manual and CBS automatic options without deriving balances', function (): void {
    $input = bcnParameters49();
    $result = (new DereNegativeBaseClosingParametersValidator)->validate($input, '2026-09');
    expect($result['status'])->toBe('PARAMETERS_VALIDATED')->and($result['parameters'])->toBe($input)
        ->and($result['stock_qualified'])->toBeFalse()->and($result['consumption_calculated'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse();
});

it('preserves optional cap semantics without inventing stock or prematurely limiting amounts', function (?string $cap): void {
    $input = bcnParameters49();
    if ($cap === null) {
        unset($input['detBCNeg'][0]['vUsarBCN']);
    } else {
        $input['detBCNeg'][0]['vUsarBCN'] = $cap;
    }
    $result = (new DereNegativeBaseClosingParametersValidator)->validate($input, '2026-09');
    expect($result['status'])->toBe('PARAMETERS_VALIDATED')->and($result['parameters'])->toBe($input);
})->with([null, '0.00', '999999999999999.99']);

it('accepts explicit opt out without a method or origin details', function (): void {
    $input = ['infoBCN' => [['codBCNRaiz' => '1005C', 'usarBCNAcum' => '0']]];
    expect((new DereNegativeBaseClosingParametersValidator)->validate($input, '2026-09')['status'])->toBe('PARAMETERS_VALIDATED');
});

it('rejects inconsistent origin and method requests with specific reasons', function (string $mutation, string $reason): void {
    $input = bcnParameters49();
    $period = '2026-09';
    switch ($mutation) {
        case 'bad-period': $period = '2026-13';
            break;
        case 'unknown-field': $input['balance'] = '100.00';
            break;
        case 'no-roots': $input['infoBCN'] = [];
            break;
        case 'unsupported-root': $input['infoBCN'][0]['codBCNRaiz'] = '2010I';
            break;
        case 'duplicate-root': $input['infoBCN'][] = $input['infoBCN'][0];
            break;
        case 'missing-method': unset($input['infoBCN'][0]['metodoAproveit']);
            break;
        case 'opt-out-method': $input['infoBCN'][0]['usarBCNAcum'] = '0';
            break;
        case 'key-length': $input['detBCNeg'][0]['codBCN'] .= '0';
            break;
        case 'current-origin': $input['detBCNeg'][0]['codBCN'] = '1010I20260900';
            break;
        case 'invalid-origin-month': $input['detBCNeg'][0]['codBCN'] = '1010I20261300';
            break;
        case 'cbs-detail-on-fifo': $input['detBCNeg'][0]['codBCN'] = '1010C20260600';
            break;
        case 'duplicate-key': $input['detBCNeg'][] = $input['detBCNeg'][0];
            break;
        case 'negative-cap': $input['detBCNeg'][0]['vUsarBCN'] = '-1.00';
            break;
        case 'fractional-cent': $input['detBCNeg'][0]['vUsarBCN'] = '1.001';
            break;
        case 'numeric-cap': $input['detBCNeg'][0]['vUsarBCN'] = 1.0;
            break;
        case 'missing-detail': unset($input['detBCNeg']);
            break;
    }
    $result = (new DereNegativeBaseClosingParametersValidator)->validate($input, $period);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_BCN_PARAMETERS_'.$reason)
        ->and($result['production_ready'])->toBeFalse();
})->with([
    ['bad-period', 'PERIOD_INVALID'], ['unknown-field', 'GROUP_SHAPE_INVALID'], ['no-roots', 'GROUP_SHAPE_INVALID'],
    ['unsupported-root', 'FINANCIAL_ROOT_UNSUPPORTED_OR_INVALID'], ['duplicate-root', 'DUPLICATE_ROOT'],
    ['missing-method', 'METHOD_CONFLICT'], ['opt-out-method', 'METHOD_CONFLICT'], ['key-length', 'ORIGIN_KEY_INVALID'],
    ['current-origin', 'ORIGIN_PERIOD_INVALID_OR_NOT_PRIOR'], ['invalid-origin-month', 'ORIGIN_PERIOD_INVALID_OR_NOT_PRIOR'],
    ['cbs-detail-on-fifo', 'DETAIL_WITHOUT_MANUAL_ROOT'], ['duplicate-key', 'DUPLICATE_ORIGIN_KEY'],
    ['negative-cap', 'MANUAL_CAP_INVALID'], ['fractional-cent', 'MANUAL_CAP_INVALID'], ['numeric-cap', 'MANUAL_CAP_INVALID'],
    ['missing-detail', 'MANUAL_ROOT_WITHOUT_DETAIL'],
]);
