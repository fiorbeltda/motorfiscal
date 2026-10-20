<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsXmlProjector;

/** @return array<string, mixed> */
function localDpsIdentity(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dps-local-2026.10.01-2/standard-identity.json'), true, 512, JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> */
function localDpsDecision(): array
{
    return [
        'iss' => ['incidence' => ['value' => 'TAXABLE'], 'location' => ['municipality_ibge' => '3550308']],
        'dps_projection' => ['service' => ['cTribNac' => '010101', 'xDescServ' => 'Software & serviços <locais>', 'detail' => []],
            'values' => ['vServ' => '1000.00'], 'iss' => ['tpRetISSQN' => '1'],
            'rtc' => ['status' => 'VALID', 'issuer_supplied' => [
                'IBSCBS.finNFSe' => '0', 'IBSCBS.indDest' => '0', 'IBSCBS.cIndOp' => '100301',
                'IBSCBS.valores.trib.gIBSCBS.CST' => '000', 'IBSCBS.valores.trib.gIBSCBS.cClassTrib' => '000001',
            ]]],
        'facts_used' => [
            ['key' => 'rtc.supplier_id', 'value' => '11111111000191'],
            ['key' => 'rtc.acquirer_id', 'value' => '22222222000191'],
            ['key' => 'rtc.acquirer_country', 'value' => 'BR'],
            ['key' => 'rtc.recipient_id', 'value' => '22222222000191'],
            ['key' => 'provider.tax_regime', 'value' => 'LUCRO_REAL'],
            ['key' => 'provider.municipality_ibge', 'value' => '3550308'],
        ],
        'federal_withholdings' => ['status' => 'NOT_REQUESTED'],
    ];
}

it('builds complete local XML with separate service location and deterministic identity', function (): void {
    $projector = new DpsXmlProjector;
    $first = $projector->project(localDpsDecision(), localDpsIdentity());
    $second = $projector->project(localDpsDecision(), localDpsIdentity());
    $xml = $first['xml'];
    expect($first['status'])->toBe('PROJECTED')->and($first)->toBe($second)
        ->and($xml)->toContain('Id="DPS355030821111111100019100001000000000000001"',
            '<opSimpNac>1</opSimpNac>', '<cLocPrestacao>3509502</cLocPrestacao>',
            '<CNPJ>22222222000191</CNPJ>', 'Software &amp; serviços &lt;locais&gt;', '<serie>1</serie>');
    $archive = dirname(__DIR__, 3).'/resources/fiscal/sources/official/nfse-esquemas_xsd-v1-01-20260209.zip';
    $validator = new DpsXmlSchemaValidator;
    $hash = 'e7935cbd9470527c6cc32984c1b2263e614183bf0139ce2733eaaed2de9a8072';
    $report = $validator->validate($xml, $archive, $hash, 'Schemas/1.01/DPS_v1.01.xsd');
    expect($report['status'])->toBe('INVALID')->and($report['errors'])->toHaveCount(1)
        ->and($report['errors'][0]['message'])->toContain('serie');
    // Synthetic structural probe only; the projector always retains the ordinary numeric series.
    $structural = $validator->validate(str_replace('<serie>1</serie>', '<serie>^1$</serie>', $xml), $archive, $hash, 'Schemas/1.01/DPS_v1.01.xsd');
    expect($structural['status'])->toBe('VALID');
});

it('requires documentary evidence and rejects identity conflicts', function (string $key, mixed $value, string $reason): void {
    $identity = localDpsIdentity();
    $identity[$key] = $value;
    $report = (new DpsXmlProjector)->project(localDpsDecision(), $identity);
    expect($report['reason'])->toBe($reason)->and($report['xml'])->toBeNull();
})->with([
    ['identity_evidence_id', '', 'RTC_DPS_IDENTITY_REQUIRED'],
    ['supplier_cnpj', '33333333000191', 'RTC_DPS_SUPPLIER_MISMATCH'],
    ['simples_option', '2', 'RTC_DPS_REGIME_MISMATCH'],
    ['issuer_municipality', '3509502', 'RTC_DPS_ISSUER_MUNICIPALITY_MISMATCH'],
    ['id', 'DPS'.str_repeat('0', 42), 'RTC_DPS_ID_COMPONENTS_MISMATCH'],
    ['competence_date', '2026-02-30', 'RTC_DPS_IDENTITY_FORMAT_INVALID'],
    ['issued_at', '2026-02-30T13:00:00-03:00', 'RTC_DPS_IDENTITY_FORMAT_INVALID'],
    ['series', '^1$', 'RTC_DPS_IDENTITY_FORMAT_INVALID'],
    ['number', '0', 'RTC_DPS_IDENTITY_FORMAT_INVALID'],
    ['total_tax_indicator', '1', 'RTC_DPS_IDENTITY_FORMAT_INVALID'],
]);

it('projects a separately identified destination without dropping RTC roles', function (): void {
    $decision = localDpsDecision();
    $decision['facts_used'][3]['value'] = '12345678901';
    $decision['dps_projection']['rtc']['issuer_supplied']['IBSCBS.indDest'] = '1';
    $identity = localDpsIdentity();
    $missing = (new DpsXmlProjector)->project($decision, $identity);
    expect($missing['status'])->toBe('ACTION_REQUIRED')->and($missing['missing_fields'])->toBe(['recipient_name']);
    $identity['recipient_name'] = 'Destinatário de teste';
    $report = (new DpsXmlProjector)->project($decision, $identity);
    expect($report['status'])->toBe('PROJECTED')->and($report['xml'])->toContain('<indDest>1</indDest>', '<CPF>12345678901</CPF>');
});

it('refuses to silently discard an additional issuer group', function (): void {
    $decision = localDpsDecision();
    $decision['dps_projection']['rtc']['issuer_supplied']['IBSCBS.tpEnteGov'] = '1';
    $report = (new DpsXmlProjector)->project($decision, localDpsIdentity());
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe('RTC_DPS_ADDITIONAL_GROUP_REQUIRED');
});

it('requires documentary groups instead of dropping base adjustments or special treatment', function (string $case): void {
    $decision = localDpsDecision();
    $identity = localDpsIdentity();
    if ($case === 'deduction') {
        $decision['iss']['calculation']['deduction_amount'] = '100.00';
    } elseif ($case === 'discount') {
        $decision['consumption_reform']['decision']['base']['adjustments'] = [
            ['kind' => 'UNCONDITIONAL_DISCOUNT', 'amount' => '100.00', 'evidence_id' => 'discount-proof'],
        ];
    } elseif ($case === 'iss_treatment') {
        $decision['iss']['treatment']['value'] = 'REDUCED_BASE';
    } else {
        $identity['special_regime'] = '6';
    }
    $result = (new DpsXmlProjector)->project($decision, $identity);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason'])->toBe('RTC_DPS_ADJUSTMENT_OR_SPECIAL_GROUP_REQUIRED')
        ->and($result['xml'])->toBeNull();
})->with(['deduction', 'discount', 'iss_treatment', 'special_regime']);
