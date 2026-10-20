<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseClosingVersionReconciler;

/** Synthetic close/reopen/successor sequence, derived from preserved synthetic return bytes. @return list<array<string, mixed>> */
function bcnVersions49(): array
{
    $root = dirname(__DIR__, 3);
    $original = file_get_contents($root.'/storage/app/phase4b-negative-base49-preparation-20261009/synthetic-return.xml');
    $first = str_replace(['<seqEvento>01', '1010C20260901'], ['<seqEvento>00', '1010C20260900'], $original);
    $receipt = '1199-202609-'.str_repeat('0', 19);
    $id = 'DeRE11981'.'11111111000191'.str_repeat('0', 19);
    $request = '<DeRE xmlns="http://www.dere.gov.br/schemas/evtReabertMensal/v0_0_1"><evtReabertMensal id="'.$id.'">'
        .'<ideEvento><tpOper>1</tpOper><tpAmb>2</tpAmb><aplicEmi>1</aplicEmi><verAplic>1.0</verAplic></ideEvento>'
        .'<ideContrib><nrInsc>11111111</nrInsc></ideContrib><idePeriodo><perApur>2026-09</perApur></idePeriodo>'
        .'<infoReabertura><nrReciboReab>'.$receipt.'</nrReciboReab></infoReabertura></evtReabertMensal></DeRE>';
    $response = '<DeRE xmlns="http://www.dere.gov.br/schemas/evtRetornoReabert/v0_0_1"><evtRetornoReabert id="'.$id.'">'
        .'<ideContrib><nrInsc>11111111</nrInsc></ideContrib><ideStatus><cdRetorno>1</cdRetorno><descRetorno>Sucesso</descRetorno></ideStatus>'
        .'<infoRecEv><nrRecibo>1198-202609-'.str_repeat('0', 19).'</nrRecibo><seqEvento>00</seqEvento>'
        .'<dhRecepcao>2026-10-01T12:01:00.0000000-03:00</dhRecepcao><dhProcess>2026-10-01T12:01:01.0000000-03:00</dhProcess>'
        .'<tpEv>D-1198</tpEv><hash>'.str_repeat('B', 43).'=</hash></infoRecEv>'
        .'<infoEvento><idePeriodo><perApur>2026-09</perApur></idePeriodo></infoEvento></evtRetornoReabert></DeRE>';
    $successor = str_replace([
        $receipt, str_repeat('A', 43).'=', '<vDedBCN>30.00', '<vUtilPer>30.00', '<vSaldoFinal>40.00',
        '<vBCApurIBS>0.00', '<vBCNCBS>20.00', '<vOrigemIni>20.00', '<vSaldoFinal>20.00', '<vSaldoFinal>100.00',
    ], [
        '1199-202609-'.str_repeat('0', 18).'1', str_repeat('C', 43).'=', '<vDedBCN>20.00', '<vUtilPer>20.00', '<vSaldoFinal>50.00',
        '<vBCApurIBS>10.00', '<vBCNCBS>10.00', '<vOrigemIni>10.00', '<vSaldoFinal>10.00', '<vSaldoFinal>90.00',
    ], $original);
    $successor = str_replace(['T12:00:00.', 'T12:00:01.'], ['T12:02:00.', 'T12:02:01.'], $successor);

    return [
        ['kind' => 'CLOSE', 'xml' => $first, 'expected_event_hash' => str_repeat('A', 43).'='],
        ['kind' => 'REOPEN', 'request_xml' => $request, 'return_xml' => $response, 'expected_event_hash' => str_repeat('B', 43).'='],
        ['kind' => 'CLOSE', 'xml' => $successor, 'expected_event_hash' => str_repeat('C', 43).'='],
    ];
}

/** @param list<array<string, mixed>> $events @return array<string, mixed> */
function reconcileBcnVersions49(array $events, bool $synthetic = true): array
{
    return (new DereNegativeBaseClosingVersionReconciler)->reconcile($events, '11111111000191', '2026-09',
        dirname(__DIR__, 3).'/resources/fiscal/sources/official', $synthetic);
}

it('replaces superseded consumption and generation rather than accumulating both closing versions', function (): void {
    $events = bcnVersions49();
    $result = reconcileBcnVersions49($events);
    expect($result['status'])->toBe('LOCAL_CLOSING_VERSION_CHAIN_RECONCILED')
        ->and($result['active_return']['declared_sequence'])->toBe('01')->and($result['superseded'])->toHaveCount(1)
        ->and($result['trace'])->toHaveCount(3)
        ->and(array_column($result['active_return']['stock_after_return'], 'available_after_return', 'codBCN'))->toBe([
            '1010I20260600' => '50.00', '1010C20260600' => '80.00', '1010C20260901' => '10.00',
        ])->and($result['received_return_verified'])->toBeFalse()->and($result['eligible_for_posting'])->toBeFalse();
});

it('keeps a complete original closing available when there is no reopening', function (): void {
    $result = reconcileBcnVersions49([bcnVersions49()[0]]);
    expect($result['status'])->toBe('LOCAL_CLOSING_VERSION_CHAIN_RECONCILED')->and($result['superseded'])->toBe([]);
});

it('compares declared timestamps across timezone offsets', function (): void {
    $events = bcnVersions49();
    $events[1]['return_xml'] = str_replace([
        'T12:01:00.0000000-03:00', 'T12:01:01.0000000-03:00',
    ], ['T15:01:00.0000000+00:00', 'T15:01:01.0000000+00:00'], $events[1]['return_xml']);
    $result = reconcileBcnVersions49($events);
    expect($result['status'])->toBe('LOCAL_CLOSING_VERSION_CHAIN_RECONCILED')
        ->and($result['declared_timestamp_order_verified'])->toBeTrue();
});

it('retains the seventh fractional digit when rejecting premature reopening', function (): void {
    $events = bcnVersions49();
    $events[0]['xml'] = str_replace('<dhProcess>2026-10-01T12:00:01.0000000', '<dhProcess>2026-10-01T12:00:01.0000001', $events[0]['xml']);
    $events[1]['return_xml'] = str_replace('<dhRecepcao>2026-10-01T12:01:00.0000000', '<dhRecepcao>2026-10-01T12:00:01.0000000', $events[1]['return_xml']);
    expect(reconcileBcnVersions49($events)['reason'])->toBe('RTC_DERE_BCN_VERSION_EVENT_TIMESTAMP_ORDER_CONFLICT');
});

it('requires provenance state and origin dependency reconciliation for closing version changes', function (string $mutation, string $reason): void {
    $events = bcnVersions49();
    switch ($mutation) {
        case 'no-reopening': unset($events[1]);
            $events = array_values($events);
            break;
        case 'open-period': array_pop($events);
            break;
        case 'no-first-close': array_shift($events);
            break;
        case 'sequence-gap': $events[2]['xml'] = str_replace(['<seqEvento>01', '1010C20260901'], ['<seqEvento>02', '1010C20260902'], $events[2]['xml']);
            break;
        case 'late-first': $events = [$events[2]];
            break;
        case 'stale-receipt': $events[1]['request_xml'] = str_replace('1199-202609-'.str_repeat('0', 19), '1199-202609-'.str_repeat('0', 18).'9', $events[1]['request_xml']);
            break;
        case 'wrong-supplier': $events[1]['return_xml'] = str_replace('<nrInsc>11111111', '<nrInsc>22222222', $events[1]['return_xml']);
            break;
        case 'wrong-hash': $events[1]['expected_event_hash'] = str_repeat('D', 43).'=';
            break;
        case 'prior-origin-changed': $events[2]['xml'] = str_replace('<vOrigemIni>100.00', '<vOrigemIni>101.00', $events[2]['xml']);
            break;
        case 'close-before-reopen': $events[2]['xml'] = str_replace(['T12:02:00.', 'T12:02:01.'], ['T12:00:00.', 'T12:00:01.'], $events[2]['xml']);
            break;
        case 'process-before-reception': $events[1]['return_xml'] = str_replace('T12:01:01.', 'T12:00:59.', $events[1]['return_xml']);
            break;
    }
    $result = reconcileBcnVersions49($events);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_BCN_VERSION_'.$reason)
        ->and($result['production_ready'])->toBeFalse();
})->with([
    ['no-reopening', 'CLOSE_WITHOUT_REOPENING'], ['open-period', 'PERIOD_OPEN_WITHOUT_SUCCESSOR_CLOSING'],
    ['no-first-close', 'REOPEN_WITHOUT_ACTIVE_CLOSING'], ['sequence-gap', 'CLOSING_SEQUENCE_GAP_OR_REGRESSION'],
    ['late-first', 'CLOSING_SEQUENCE_GAP_OR_REGRESSION'], ['stale-receipt', 'REOPENING_SCOPE_OR_ACTIVE_RECEIPT_CONFLICT'],
    ['wrong-supplier', 'REOPENING_SCOPE_OR_ACTIVE_RECEIPT_CONFLICT'], ['wrong-hash', 'REOPENING_SCOPE_OR_ACTIVE_RECEIPT_CONFLICT'],
    ['prior-origin-changed', 'PRIOR_ORIGIN_DEPENDENCY_REPROCESSING_REQUIRED'],
    ['close-before-reopen', 'EVENT_TIMESTAMP_ORDER_CONFLICT'], ['process-before-reception', 'EVENT_TIMESTAMP_ORDER_CONFLICT'],
]);
