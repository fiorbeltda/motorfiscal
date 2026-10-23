<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\PartialExportDpsPair135;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/phase4b_dps_pair135_fixture.php';

function pair135Case(): array
{
    static $case;

    return $case ??= pair135Fixture();
}

function pair135Project(array $case): array
{
    return (new PartialExportDpsPair135)->project($case['snapshot'], $case['declarations'], resource_path('fiscal'));
}

it('replays134 and produces two complete unsigned sandbox DPS documents bound to their own values and identities', function (): void {
    $case = pair135Case();
    $before = CanonicalJson::hash($case);
    $result = pair135Project($case);
    $docs = array_column($result['records'], null, 'role');
    expect($result['status'])->toBe('LOCAL_PARTIAL_EXPORT_PAIR_DPS_XSD_VALIDATED')
        ->and($result['complete_dps_built'])->toBeTrue()
        ->and($result['base_official_sources_valid'])->toBe(112)
        ->and($result['additional_sources_valid'])->toBe(8)
        ->and($result['nbs_selection']['normalized_reference'])->toBe('1.1502.20.00')
        ->and($result['nbs_selection']['correlation_is_decision_authority'])->toBeFalse()
        ->and($result['complete_fiscal_pair_qualified'])->toBeFalse()
        ->and($result['own_pis_cofins_projected_in_xml'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse()
        ->and($docs['RTC_FOREIGN']['validation']['status'])->toBe('VALID')
        ->and($docs['RTC_DOMESTIC']['validation']['status'])->toBe('VALID')
        ->and($docs['RTC_FOREIGN']['gross_document_price_brl'])->toBe('400.00')
        ->and($docs['RTC_DOMESTIC']['gross_document_price_brl'])->toBe('641.33')
        ->and($docs['RTC_DOMESTIC']['own_iss_tax_brl'])->toBe('18.60')
        ->and(CanonicalJson::hash($case))->toBe($before);
    foreach ($docs as $role => $document) {
        $xml = new DOMDocument;
        $xml->loadXML($document['xml'], LIBXML_NONET);
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('d', 'http://www.sped.fazenda.gov.br/nfse');
        $foreign = $role === 'RTC_FOREIGN';
        expect($xpath->evaluate('string(/d:DPS/d:infDPS/d:nDPS)'))->toBe($foreign ? '1353' : '1354')
            ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:IBSCBS/d:valores/d:trib/d:cClassTrib)'))->toBe($foreign ? '410027' : '000001')
            ->and($xpath->evaluate('count(/d:DPS/d:infDPS/d:IBSCBS/d:cIndOp)'))->toBe($foreign ? 0.0 : 1.0)
            ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:serv/d:comExt/d:vServMoeda)'))->toBe($foreign ? '400.00' : '641.33')
            ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:valores/d:vServPrest/d:vServ)'))->toBe($foreign ? '400.00' : '641.33')
            ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:toma/d:xNome)'))->toBe('Foreign & Company');
    }
});

it('is deterministic across proof order and JSON object order while preserving official XML field order', function (): void {
    $case = pair135Case();
    $expected = pair135Project($case);
    $case['declarations']['own_tax_proofs'] = array_reverse($case['declarations']['own_tax_proofs']);
    $case['declarations']['comex_proofs'] = array_reverse($case['declarations']['comex_proofs']);
    $options = &$case['declarations']['documentary_proof']['value']['options'];
    foreach ($options['foreign_trade'] as &$fields) {
        $fields = array_reverse($fields, true);
    }
    unset($fields, $options);
    $case['declarations'] = pair135Reseal($case['snapshot'], $case['declarations']);
    expect(pair135Project($case))->toBe($expected);
});

it('checks a separately reviewed noncustomized NBS rather than hardcoding a single software code', function (): void {
    $case = pair135Case();
    $case['declarations']['documentary_proof']['value']['options']['software_type'] = 'NON_CUSTOMIZED';
    $case['declarations']['documentary_proof']['value']['options']['nbs'] = '115021000';
    $case['declarations'] = pair135Reseal($case['snapshot'], $case['declarations']);
    expect(pair135Project($case)['nbs_selection']['normalized_reference'])->toBe('1.1502.10.00');
});

it('refuses recaptured documentary contradictions before any XML files are persisted', function (string $caseName): void {
    $case = pair135Case();
    $options = &$case['declarations']['documentary_proof']['value']['options'];
    match ($caseName) {
        'number' => $options['numbers']['RTC_DOMESTIC'] = '9999',
        'series' => $options['series'] = '99',
        'future issue' => $options['issued_at'] = '2026-10-24T12:00:00+00:00',
        'municipality' => $options['issuer_municipality'] = '3509502',
        'service place' => $options['service_municipality'] = '3509502',
        'simples' => $options['simples_option'] = '3',
        'regime' => $options['special_regime'] = '1',
        'NBS mismatch' => $options['nbs'] = '115021000',
        'software unspecified' => $options['software_type'] = 'UNKNOWN',
        'NBS evidence empty' => $options['nbs_review_evidence_id'] = '',
        'NBS unknown' => $options['nbs'] = '999999999',
        'foreign alias' => $options['foreign_acquirer_internal_id'] = 'OTHER',
        'recipient alias' => $options['domestic_recipient_internal_id'] = 'OTHER',
        'party country' => $options['foreign_acquirer']['country'] = 'BR',
        'NIF contradiction' => $options['foreign_acquirer']['no_nif_reason'] = '0',
        'COMEX amount' => $options['foreign_trade']['RTC_DOMESTIC']['vServMoeda'] = '617.92',
        'COMEX mode' => $options['foreign_trade']['RTC_FOREIGN']['mdPrestacao'] = '2',
        'values document' => $options['values_options'][1]['document_reference'] = 'OTHER',
        'empty description' => $options['service_description'] = '',
    };
    unset($options);
    $case['declarations'] = pair135Reseal($case['snapshot'], $case['declarations']);
    expect(fn () => pair135Project($case))->toThrow(RuntimeException::class);
})->with(['number', 'series', 'future issue', 'municipality', 'service place', 'simples', 'regime', 'NBS mismatch',
    'software unspecified', 'NBS evidence empty', 'NBS unknown', 'foreign alias', 'recipient alias', 'party country',
    'NIF contradiction', 'COMEX amount', 'COMEX mode', 'values document', 'empty description']);

it('refuses own or COMEX evidence contradictions despite resealed envelope hashes', function (string $caseName): void {
    $case = pair135Case();
    if ($caseName === 'own revoked') {
        $case['declarations']['own_tax_proofs'][1]['revoked_at'] = PartialExportDpsPair135::AVAILABLE_AT;
        $case['declarations']['own_tax_proofs'][1] = own135Capture($case['declarations']['own_tax_proofs'][1]);
    } else {
        $proof = &$case['declarations']['comex_proofs'][1];
        match ($caseName) {
            'comex revoked' => $proof['revoked_at'] = PartialExportDpsPair135::AVAILABLE_AT,
            'comex amount' => $proof['value']['trade_fields']['vServMoeda'] = '617.92',
            'comex stale iss' => $proof['value']['fresh_iss_binding_hash'] = str_repeat('0', 64),
            'comex before price' => $proof['observed_at'] = '2026-10-23T11:03:08.451956+00:00',
            'duplicate evidence' => $proof['evidence_id'] = $case['declarations']['own_tax_proofs'][1]['evidence_id'],
        };
        $proof = own135Capture($proof);
        unset($proof);
    }
    $case['declarations'] = pair135Reseal($case['snapshot'], $case['declarations']);
    expect(fn () => pair135Project($case))->toThrow(RuntimeException::class);
})->with(['own revoked', 'comex revoked', 'comex amount', 'comex stale iss', 'comex before price', 'duplicate evidence']);

it('does not project the former contract that reconciled only ISS', function (): void {
    expect(fn () => pair135Project(pair135Fixture('617.92')))->toThrow(RuntimeException::class, 'Own-tax price bridge refused');
});

it('refuses detached knowledge,result and documentary source material', function (string $caseName): void {
    $case = pair135Case();
    match ($caseName) {
        'snapshot result' => $case['snapshot']['result']['status'] = 'VALID',
        'snapshot facts' => $case['snapshot']['input']['facts']['rtc.gross_amount'] = '1000.01',
        'knowledge' => $case['snapshot']['knowledge']['engine_version'] = 'OTHER',
        'raw proof' => $case['declarations']['documentary_proof']['origin']['content'] .= ' ',
        'expired proof' => $case['declarations']['documentary_proof']['valid_to'] = '2026-09-30',
        'future proof' => $case['declarations']['documentary_proof']['observed_at'] = '2026-10-24T12:00:00+00:00',
        'revoked proof' => $case['declarations']['documentary_proof']['revoked_at'] = PartialExportDpsPair135::AVAILABLE_AT,
    };
    if (! in_array($caseName, ['raw proof', 'knowledge'], true)) {
        $case['declarations'] = pair135Reseal($case['snapshot'], $case['declarations']);
    }
    expect(fn () => pair135Project($case))->toThrow(RuntimeException::class);
})->with(['snapshot result', 'snapshot facts', 'knowledge', 'raw proof', 'expired proof', 'future proof', 'revoked proof']);

it('writes both XSD-validated XMLs and a report through the CLI and refuses overwrite or invalid creation', function (): void {
    $case = pair135Case();
    $parent = sys_get_temp_dir().'/pair135-cli-'.bin2hex(random_bytes(8));
    mkdir($parent, 0700);
    file_put_contents($parent.'/snapshot.json', CanonicalJson::encode($case['snapshot']));
    file_put_contents($parent.'/declarations.json', CanonicalJson::encode($case['declarations']));
    $arguments = ['snapshot' => $parent.'/snapshot.json', 'declarations' => $parent.'/declarations.json', '--output-dir' => $parent.'/valid'];
    expect(Artisan::call('fiscal:dps:project-export-pair', $arguments))->toBe(0);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($report['complete_dps_built'])->toBeTrue()->and(is_file($parent.'/valid/report.json'))->toBeTrue();
    foreach ($report['records'] as $record) {
        expect(hash_file('sha256', $parent.'/valid/'.$record['role'].'.xml'))->toBe($record['xml_sha256']);
    }
    $before = hash_file('sha256', $parent.'/valid/report.json');
    expect(Artisan::call('fiscal:dps:project-export-pair', $arguments))->toBe(1)
        ->and(hash_file('sha256', $parent.'/valid/report.json'))->toBe($before);
    $case['declarations']['documentary_proof']['value']['options']['numbers']['RTC_DOMESTIC'] = '9999';
    $case['declarations'] = pair135Reseal($case['snapshot'], $case['declarations']);
    file_put_contents($parent.'/declarations.json', CanonicalJson::encode($case['declarations']));
    $arguments['--output-dir'] = $parent.'/invalid';
    expect(Artisan::call('fiscal:dps:project-export-pair', $arguments))->toBe(1)
        ->and(file_exists($parent.'/invalid'))->toBeFalse();
});
