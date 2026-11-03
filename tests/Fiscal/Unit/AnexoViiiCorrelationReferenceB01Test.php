<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AnexoViiiCorrelationReferenceB01;

function b01Root(): string
{
    return dirname(__DIR__, 3);
}

function b01Catalog(): array
{
    return json_decode((string) file_get_contents(b01Root().'/'.AnexoViiiCorrelationReferenceB01::CATALOG_PATH), true, 512, JSON_THROW_ON_ERROR);
}

function b01Source(string $file = AnexoViiiCorrelationReferenceB01::SOURCE_FILE): string
{
    return b01Root().'/resources/fiscal/sources/official/'.$file;
}

/**
 * Independent oracle: reads the official XLSX straight from its ZIP/XML parts,
 * without the openpyxl reader used by the materialization script.
 *
 * @return list<array<string, mixed>>
 */
function b01OracleRows(string $path): array
{
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        throw new RuntimeException('XLSX not readable.');
    }
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $book = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
    $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
    $relId = null;
    foreach ($book->children($ns)->sheets->sheet as $sheet) {
        if ((string) $sheet->attributes()['name'] === 'tabela geral') {
            $relId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        }
    }
    $target = null;
    foreach ($rels->Relationship as $rel) {
        if ((string) $rel->attributes()['Id'] === $relId) {
            $target = 'xl/'.ltrim(str_replace('/xl/', '', (string) $rel->attributes()['Target']), '/');
        }
    }
    if ($relId === null || $target === null) {
        throw new RuntimeException('Sheet "tabela geral" not found by the oracle.');
    }
    $shared = [];
    $sst = $zip->getFromName('xl/sharedStrings.xml');
    if ($sst !== false) {
        foreach (simplexml_load_string($sst)->children($ns)->si as $si) {
            $text = '';
            foreach ($si->xpath('.//*[local-name()="t"]') as $t) {
                $text .= (string) $t;
            }
            $shared[] = $text;
        }
    }
    $xml = simplexml_load_string((string) $zip->getFromName((string) $target));
    $zip->close();
    $out = [];
    foreach ($xml->children($ns)->sheetData->row as $row) {
        $number = (int) $row->attributes()['r'];
        if ($number === 1) {
            continue;
        }
        $cells = [];
        foreach ($row->c as $c) {
            $col = preg_replace('/[0-9]+/', '', (string) $c->attributes()['r']);
            $type = (string) $c->attributes()['t'];
            $value = $type === 's' ? $shared[(int) $c->v] : ($type === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
            $cells[$col] = $value;
        }
        $get = fn (string $col): string => $cells[$col] ?? '';
        if ($get('C') !== '' && $get('G') !== '' && $get('I') !== '') {
            $out[] = ['row' => $number, 'lc116_code' => $get('A'), 'nbs' => $get('C'),
                'cindop' => str_pad($get('G'), 6, '0', STR_PAD_LEFT), 'ibs_incidence_place' => $get('H'),
                'cclasstrib' => $get('I'), 'kind' => 'OFFICIAL_CORRELATION', 'decision_authority' => false];
        }
    }

    return $out;
}

$known = new DateTimeImmutable('2026-11-03T13:00:00+00:00');

it('materializes every complete literal 1.02.00 row exactly as an independent XLSX reader observes it', function (): void {
    $catalog = b01Catalog();
    expect(hash_file('sha256', b01Source()))->toBe(AnexoViiiCorrelationReferenceB01::SOURCE_SHA256)
        ->and($catalog['correlations'])->toBe(b01OracleRows(b01Source()))
        ->and($catalog['counts']['complete_correlations'])->toBe(251)
        ->and($catalog['counts']['incomplete_nonempty_rows_not_filled'])->toBe(1358);
});

it('keeps the preserved 1.01.00 bytes and the active 141 catalog correlations unchanged', function (): void {
    $previous = b01Source('anexoviii-correlacaoitemnbsindopcclasstrib_ibscbs_v1-01-00.xlsx');
    $active = json_decode((string) file_get_contents(b01Root().'/resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $oracle = array_map(fn (array $r): array => array_diff_key($r, ['ibs_incidence_place' => true]), b01OracleRows($previous));
    expect(hash_file('sha256', $previous))->toBe(AnexoViiiCorrelationReferenceB01::PREVIOUS_SOURCE_SHA256)
        ->and($active['sources']['correlation']['sha256'])->toBe(AnexoViiiCorrelationReferenceB01::PREVIOUS_SOURCE_SHA256)
        ->and($active['sources']['correlation']['version'])->toBe('1.01.00')
        ->and($active['correlations'])->toBe($oracle)
        ->and(count($oracle))->toBe(200);
});

it('records the literal difference against 1.01.00 without inheriting or inventing rows', function (): void {
    $catalog = b01Catalog();
    $key = fn (array $r): string => implode('|', [$r['lc116_code'], $r['nbs'], $r['cindop'], $r['cclasstrib']]);
    $current = array_count_values(array_map($key, b01OracleRows(b01Source())));
    $previous = array_count_values(array_map($key, b01OracleRows(b01Source('anexoviii-correlacaoitemnbsindopcclasstrib_ibscbs_v1-01-00.xlsx'))));
    $added = $removed = [];
    $same = 0;
    foreach ($current as $k => $n) {
        $same += min($n, $previous[$k] ?? 0);
        for ($i = $previous[$k] ?? 0; $i < $n; $i++) {
            $added[] = $k;
        }
    }
    foreach ($previous as $k => $n) {
        for ($i = $current[$k] ?? 0; $i < $n; $i++) {
            $removed[] = $k;
        }
    }
    $normalized = function (array $keys): array {
        sort($keys, SORT_STRING);

        return $keys;
    };
    expect($normalized(array_map(fn (array $k): string => implode('|', $k), $catalog['diff_vs_previous']['added'])))->toBe($normalized($added))
        ->and($normalized(array_map(fn (array $k): string => implode('|', $k), $catalog['diff_vs_previous']['removed'])))->toBe($normalized($removed))
        ->and($catalog['diff_vs_previous']['unchanged'])->toBe($same)
        ->and([count($added), count($removed), $same])->toBe([72, 21, 179])
        ->and(array_column($catalog['structural_changes_from_previous']['changed_columns'], 'current'))->toBe(['COD_ATIV_SN', 'DESCRIÇÃO ATIV_SN']);
});

it('binds the reference and never grants decision authority', function () use ($known): void {
    $result = (new AnexoViiiCorrelationReferenceB01)->qualify(b01Catalog(), b01Source(), $known);
    expect($result['status'])->toBe('RESOLVED')
        ->and($result['decision_authority'])->toBeFalse()->and($result['tax_treatment_selected'])->toBeFalse()
        ->and($result['cclasstrib_selected'])->toBeFalse()->and($result['cindop_selected'])->toBeFalse()
        ->and($result['official_effective_date_proven'])->toBeFalse()->and($result['bundle_bound'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse();
});

it('returns literal rows only as reference and keeps absence distinct from nonincidence', function () use ($known): void {
    $reference = new AnexoViiiCorrelationReferenceB01;
    $found = $reference->lookupNbs(b01Catalog(), b01Source(), '1.1502.10.00', 'LOCAL_SANDBOX', $known);
    $absent = $reference->lookupNbs(b01Catalog(), b01Source(), '9.9999.99.99', 'LOCAL_SANDBOX', $known);
    expect($found['status'])->toBe('REFERENCE_ROWS_AVAILABLE')
        ->and($found['rows'][0])->toMatchArray(['row' => 2, 'lc116_code' => '01.01', 'cindop' => '100301', 'cclasstrib' => '000001', 'decision_authority' => false])
        ->and($found['decision_authority'])->toBeFalse()->and($found['tax_treatment_selected'])->toBeFalse()
        ->and($absent['status'])->toBe('NO_COMPLETE_OFFICIAL_CORRELATION')
        ->and($absent['absence_is_not_nonincidence'])->toBeTrue()->and($absent['decision_authority'])->toBeFalse();
});

it('refuses tampering, unbound bytes, retrospective knowledge, official environments and malformed codes', function (string $case, string $reason, string $status) use ($known): void {
    $catalog = b01Catalog();
    $source = b01Source();
    $at = $known;
    $environment = 'LOCAL_SANDBOX';
    $nbs = '1.1502.10.00';
    $temp = null;
    switch ($case) {
        case 'tampered-row': $catalog['correlations'][0]['cclasstrib'] = '000002';
            break;
        case 'authority-escalation': $catalog['correlations'][0]['decision_authority'] = true;
            break;
        case 'catalog-authority': $catalog['decision_authority'] = true;
            break;
        case 'bundle-binding': $catalog['bundle_bound'] = true;
            break;
        case 'wrong-version': $catalog['version'] = 'anexoviii-correlation-1.01.00';
            break;
        case 'altered-bytes': $temp = tempnam(sys_get_temp_dir(), 'b01');
            file_put_contents($temp, (string) file_get_contents($source)."\0");
            $source = $temp;
            break;
        case 'missing-source': $source = b01Root().'/resources/fiscal/sources/official/does-not-exist.xlsx';
            break;
        case 'previous-bytes': $source = b01Source('anexoviii-correlacaoitemnbsindopcclasstrib_ibscbs_v1-01-00.xlsx');
            break;
        case 'before-observation': $at = new DateTimeImmutable('2026-11-03T12:04:29+00:00');
            break;
        case 'restricted-production': $environment = 'PRODUCAO_RESTRITA';
            break;
        case 'production': $environment = 'PRODUCAO';
            break;
        case 'malformed-nbs': $nbs = '115021000';
            break;
    }
    $result = (new AnexoViiiCorrelationReferenceB01)->lookupNbs($catalog, $source, $nbs, $environment, $at);
    if ($temp !== null) {
        unlink($temp);
    }
    expect($result['status'])->toBe($status)->and($result['reason'])->toBe('ANEXOVIII_B01_'.$reason)
        ->and($result['decision_authority'])->toBeFalse()->and($result)->not->toHaveKey('rows');
})->with([
    ['tampered-row', 'CATALOG_NOT_BOUND', 'ACTION_REQUIRED'],
    ['authority-escalation', 'CATALOG_NOT_BOUND', 'ACTION_REQUIRED'],
    ['catalog-authority', 'CATALOG_NOT_BOUND', 'ACTION_REQUIRED'],
    ['bundle-binding', 'CATALOG_NOT_BOUND', 'ACTION_REQUIRED'],
    ['wrong-version', 'CATALOG_NOT_BOUND', 'ACTION_REQUIRED'],
    ['altered-bytes', 'SOURCE_BYTES_NOT_BOUND', 'ACTION_REQUIRED'],
    ['missing-source', 'SOURCE_BYTES_NOT_BOUND', 'ACTION_REQUIRED'],
    ['previous-bytes', 'SOURCE_BYTES_NOT_BOUND', 'ACTION_REQUIRED'],
    ['before-observation', 'SOURCE_NOT_YET_OBSERVED', 'ACTION_REQUIRED'],
    ['restricted-production', 'OFFICIAL_ENVIRONMENT_EFFECTIVE_DATE_UNPROVEN', 'ACTION_REQUIRED'],
    ['production', 'OFFICIAL_ENVIRONMENT_EFFECTIVE_DATE_UNPROVEN', 'ACTION_REQUIRED'],
    ['malformed-nbs', 'MALFORMED_NBS', 'INVALID'],
]);

it('is not referenced by any bundle or active catalog', function (): void {
    $hits = [];
    foreach (glob(b01Root().'/resources/fiscal/{bundles,catalogs}/*.json', GLOB_BRACE) as $file) {
        if (basename($file) !== basename(AnexoViiiCorrelationReferenceB01::CATALOG_PATH)
            && str_contains((string) file_get_contents($file), AnexoViiiCorrelationReferenceB01::VERSION)) {
            $hits[] = basename($file);
        }
    }
    expect($hits)->toBe([]);
});
