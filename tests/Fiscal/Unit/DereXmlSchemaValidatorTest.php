<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereAccessKey;

/** Synthetic unsigned candidate for unchanged official XSD validation only. */
function dereUnsignedSchemaFixture(): string
{
    $day = LocalDate::fromString('2026-09-20');
    $key = (new DereAccessKey)->group('11111111', 'BR', '1', '22222222000191', '1005', $day, $day, 1);

    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<DeRE xmlns="http://www.dere.gov.br/schemas/evtOperFinanc/v0_0_1">'
        .'<evtOperFinanc id="DeRE22111111111110001912026093012000000001">'
        .'<ideEvento><finEvt>11</finEvt><tpAmb>2</tpAmb><aplicEmi>1</aplicEmi><verAplic>local-candidate</verAplic></ideEvento>'
        .'<ideContrib><nrInsc>11111111</nrInsc></ideContrib>'
        .'<idePeriodo><perApur>2026-09</perApur><dhEmi>2026-09-30T12:00:00.000-03:00</dhEmi></idePeriodo>'
        .'<infoOper><gOper><chDeRE>'.$key.'</chDeRE><codBC>1005</codBC>'
        .'<idePartes><infoAdq><CNPJ>22222222000191</CNPJ><cMun>3550308</cMun></infoAdq></idePartes>'
        .'<dadosOper><detOper seq="001"><dhOper>2026-09-20T12:00:00.000-03:00</dhOper>'
        .'<qualifOper><indMoedaEstr>0</indMoedaEstr></qualifOper><gBC><vBCApur>800.00</vBCApur></gBC>'
        .'<gTributos><vBCTrib>800.00</vBCTrib><pIBSMunTrib>0.000000</pIBSMunTrib><vIBSMunTrib>0.00</vIBSMunTrib>'
        .'<pIBSUFTrib>0.100000</pIBSUFTrib><vIBSUFTrib>0.80</vIBSUFTrib><pCBSTrib>0.900000</pCBSTrib><vCBSTrib>7.20</vCBSTrib>'
        .'</gTributos></detOper></dadosOper></gOper></infoOper></evtOperFinanc></DeRE>';
}

/** @return array<string, mixed> */
function dereSchemaResult(string $xml, ?string $signatureHash = null): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';

    return (new DereXmlSchemaValidator)->validate($xml,
        $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
        'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', 'evtOperFinanc-v0_0_1.xsd',
        $root.'w3c-xmldsig-core-schema-observed-20261002.xsd',
        $signatureHash ?? 'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
}

it('checks the unsigned transaction candidate against unmodified official schemas without approval', function (): void {
    $result = dereSchemaResult(dereUnsignedSchemaFixture());
    expect($result['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')
        ->and($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['unsigned_candidate_structure_checked'])->toBeTrue()
        ->and($result['signature_verified'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
});

it('does not attribute malformed or invalid body errors solely to the absent signature', function (): void {
    $result = dereSchemaResult(str_replace('<vBCTrib>800.00</vBCTrib>', '<vBCTrib>-800.00</vBCTrib>', dereUnsignedSchemaFixture()));
    expect($result['reason'])->toBe('RTC_DERE_XML_XSD_INVALID')->and($result['unsigned_candidate_structure_checked'])->toBeFalse();
    expect(dereSchemaResult('<DeRE>')['reason'])->toBe('RTC_DERE_XML_MALFORMED');
});

it('requires the independently preserved dependency hash and rejects entity declarations', function (): void {
    expect(dereSchemaResult(dereUnsignedSchemaFixture(), str_repeat('0', 64))['reason'])->toBe('RTC_DERE_XSD_SOURCE_HASH_MISMATCH');
    expect(dereSchemaResult('<!DOCTYPE DeRE [<!ENTITY x SYSTEM "file:///etc/passwd">]><DeRE>&x;</DeRE>')['reason'])
        ->toBe('RTC_DERE_XML_OR_SCHEMA_INPUT_INVALID');
});
