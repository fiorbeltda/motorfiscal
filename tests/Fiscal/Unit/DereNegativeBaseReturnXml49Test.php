<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseReturnXmlReader;

/** Synthetic full unsigned return; receipt strings are fixture data, never evidence of transmission. */
function bcnXml49(): string
{
    $hash = str_repeat('A', 43).'=';
    $receipt = '1199-202609-'.str_repeat('0', 19);
    $bookReceipt = '1101-202609-'.str_repeat('0', 19);
    $id = 'DeRE11991'.'11111111000191'.str_repeat('0', 19);

    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<DeRE xmlns="http://www.dere.gov.br/schemas/evtRetornoMensal/v0_0_2"><evtRetornoMensal id="'.$id.'">'
        .'<ideContrib><nrInsc>11111111</nrInsc></ideContrib><ideStatus><cdRetorno>1</cdRetorno><descRetorno>Sucesso</descRetorno></ideStatus>'
        .'<infoRecEv><nrRecibo>'.$receipt.'</nrRecibo><seqEvento>01</seqEvento>'
        .'<dhRecepcao>2026-10-01T12:00:00.0000000-03:00</dhRecepcao><dhProcess>2026-10-01T12:00:01.0000000-03:00</dhProcess><tpEv>D-1199</tpEv><hash>'.$hash.'</hash></infoRecEv>'
        .'<infoEvento><idePeriodo><perApur>2026-09</perApur></idePeriodo><infoAdic><nrReciboBalancete>'.$bookReceipt.'</nrReciboBalancete></infoAdic>'
        .'<infoTotFinanceiro><detBC><codBC>1010</codBC><xDetBC>Synthetic credit FX securities no ISS</xDetBC><memoriaCalculo>Synthetic local arithmetic</memoriaCalculo>'
        .'<gBCIBS><vBCIBS>30.00</vBCIBS><vDedBCN>30.00</vDedBCN><vBCApurIBS>0.00</vBCApurIBS><pIBSMun>0.000000</pIBSMun><vIBSMun>0.00</vIBSMun><pIBSUF>0.100000</pIBSUF><vIBSUF>0.00</vIBSUF><pIBS>0.100000</pIBS><vIBSTot>0.00</vIBSTot></gBCIBS>'
        .'<gBCCBS><vBCCBS>0.00</vBCCBS><vBCNCBS>20.00</vBCNCBS><vBCApurCBS>0.00</vBCApurCBS><pCBS>0.900000</pCBS><vCBS>0.00</vCBS></gBCCBS>'
        .'<infoBCN><gBCNIBS><codBCNRaiz>1010I</codBCNRaiz><vSaldoAnt>70.00</vSaldoAnt><vUtilPer>30.00</vUtilPer><vSaldoFinal>40.00</vSaldoFinal><qtdOrigens>1</qtdOrigens>'
        .'<detBCN><codBCN>1010I20260600</codBCN><perOrigem>2026-06</perOrigem><vOrigemIni>100.00</vOrigemIni><vSaldoAnt>70.00</vSaldoAnt><vUtilPer>30.00</vUtilPer><vSaldoFinal>40.00</vSaldoFinal></detBCN></gBCNIBS>'
        .'<gBCNCBS><codBCNRaiz>1010C</codBCNRaiz><vSaldoAnt>80.00</vSaldoAnt><vUtilPer>0.00</vUtilPer><vSaldoFinal>100.00</vSaldoFinal><qtdOrigens>2</qtdOrigens>'
        .'<detBCN><codBCN>1010C20260600</codBCN><perOrigem>2026-06</perOrigem><vOrigemIni>200.00</vOrigemIni><vSaldoAnt>80.00</vSaldoAnt><vUtilPer>0.00</vUtilPer><vSaldoFinal>80.00</vSaldoFinal></detBCN>'
        .'<detBCN><codBCN>1010C20260901</codBCN><perOrigem>2026-09</perOrigem><vOrigemIni>20.00</vOrigemIni><vSaldoAnt>0.00</vSaldoAnt><vUtilPer>0.00</vUtilPer><vSaldoFinal>20.00</vSaldoFinal></detBCN></gBCNCBS></infoBCN></detBC>'
        .'<totalTributos><vIBSMun>0.00</vIBSMun><vIBSUF>0.00</vIBSUF><vIBSTot>0.00</vIBSTot><vCBS>0.00</vCBS></totalTributos></infoTotFinanceiro>'
        .'<totalTributosGeral><vIBSMun>0.00</vIBSMun><vIBSUF>0.00</vIBSUF><vIBSTot>0.00</vIBSTot><vCBS>0.00</vCBS></totalTributosGeral></infoEvento></evtRetornoMensal></DeRE>';
}

/** @return array<string, mixed> */
function readBcnXml49(string $xml, bool $synthetic = true): array
{
    return (new DereNegativeBaseReturnXmlReader)->read($xml,
        ['supplier_id' => '11111111000191', 'period' => '2026-09', 'closing_event_hash' => str_repeat('A', 43).'='],
        dirname(__DIR__, 3).'/resources/fiscal/sources/official', $synthetic);
}

it('checks the original schema and reconciles the actual return bytes as explicitly synthetic', function (): void {
    $xml = bcnXml49();
    $result = readBcnXml49($xml);
    expect($result['status'])->toBe('LOCAL_RETURN_RECONCILED')->and($result['xml_sha256'])->toBe(hash('sha256', $xml))
        ->and($result['schema']['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')
        ->and($result['declared_sequence'])->toBe('01')->and($result['stock_after_return'])->toHaveCount(3)
        ->and($result['received_return_verified'])->toBeFalse()->and($result['eligible_for_consumption'])->toBeFalse();
});

it('rejects unsigned synthetic bytes in the ordinary return mode', function (): void {
    expect(readBcnXml49(bcnXml49(), false)['reason'])->toBe('RTC_DERE_BCN_XML_SCHEMA_UNPROVEN');
});

it('rejects semantic return conflicts even when the official schema accepts their shape', function (string $from, string $to, string $reason): void {
    $result = readBcnXml49(str_replace($from, $to, bcnXml49()));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($reason)
        ->and($result['received_return_verified'])->toBeFalse();
})->with([
    ['<nrInsc>11111111', '<nrInsc>22222222', 'RTC_DERE_BCN_XML_RETURN_SCOPE_OR_CLOSING_CONFLICT'],
    ['<seqEvento>01', '<seqEvento>02', 'RTC_DERE_BCN_XML_CURRENT_ORIGIN_SEQUENCE_CONFLICT'],
    ['<vDedBCN>30.00', '<vDedBCN>29.99', 'RTC_DERE_BCN_XML_TAX_POOL_OR_DEDUCTION_CONFLICT'],
    ['<codBCNRaiz>1010I', '<codBCNRaiz>1010C', 'RTC_DERE_BCN_XML_TAX_POOL_OR_DEDUCTION_CONFLICT'],
    ['<vSaldoFinal>40.00', '<vSaldoFinal>40.01', 'RTC_DERE_BCN_RETURN_DETAIL_CONSERVATION_CONFLICT'],
    ['<vBCApurIBS>0.00', '<vBCApurIBS>0.01', 'RTC_DERE_BCN_XML_ASSESSED_BASE_CONFLICT'],
    ['<qtdOrigens>1', '<qtdOrigens>2', 'RTC_DERE_BCN_XML_ORIGIN_COUNT_CONFLICT'],
]);
