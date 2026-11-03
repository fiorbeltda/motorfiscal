<?php

declare(strict_types=1);

// B20 (4B.6.3/4B.6.4): own independent verification of the materialized cClassTrib v1.70 and NBS v1.01
// catalogs against the preserved official workbooks, read straight from ZIP/XML (no openpyxl).

/** @return list<array<string, string>> rows of a sheet keyed by column letter */
function b20Sheet(string $file, string $sheetName): array
{
    $zip = new ZipArchive;
    if ($zip->open($file) !== true) {
        throw new RuntimeException('XLSX not readable.');
    }
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $book = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
    $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
    $relId = null;
    foreach ($book->children($ns)->sheets->sheet as $sheet) {
        if ((string) $sheet->attributes()['name'] === $sheetName) {
            $relId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        }
    }
    $target = null;
    foreach ($rels->Relationship as $rel) {
        if ((string) $rel->attributes()['Id'] === $relId) {
            $target = 'xl/'.ltrim(str_replace('/xl/', '', (string) $rel->attributes()['Target']), '/');
        }
    }
    if ($target === null) {
        throw new RuntimeException("Sheet {$sheetName} not found.");
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
    $xml = simplexml_load_string((string) $zip->getFromName($target));
    $zip->close();
    $rows = [];
    foreach ($xml->children($ns)->sheetData->row as $row) {
        $cells = ['_row' => (string) $row->attributes()['r']];
        foreach ($row->c as $c) {
            $col = (string) preg_replace('/[0-9]+/', '', (string) $c->attributes()['r']);
            $type = (string) $c->attributes()['t'];
            $cells[$col] = $type === 's' ? $shared[(int) $c->v] : ($type === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
        }
        $rows[] = $cells;
    }

    return $rows;
}

function b20Catalog(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('4B.6.3: every materialized cClassTrib v1.70 row matches the preserved official workbook', function (): void {
    $catalog = b20Catalog();
    $source = dirname(__DIR__, 3).'/resources/fiscal/sources/official/'.$catalog['sources']['cclasstrib']['file'];
    expect(hash_file('sha256', $source))->toBe($catalog['sources']['cclasstrib']['sha256']);
    $sheetName = (string) $catalog['cclasstrib'][array_key_first($catalog['cclasstrib'])]['source_sheet'];
    $official = [];
    foreach (b20Sheet($source, $sheetName) as $row) {
        $code = $row['C'] ?? '';
        if (preg_match('/\A\d{6}\z/', $code) === 1) {
            $official[$code] = ['cst' => $row['A'] ?? '', 'name' => $row['D'] ?? '', 'row' => (int) $row['_row']];
        }
    }
    expect(array_keys($catalog['cclasstrib']))->toEqualCanonicalizing(array_keys($official))->and(count($official))->toBe(173);
    foreach ($catalog['cclasstrib'] as $code => $entry) {
        expect([$entry['cst'], $entry['name'], $entry['source_row']])->toBe([$official[$code]['cst'], $official[$code]['name'], $official[$code]['row']], (string) $code);
    }
});

it('4B.6.4: every materialized NBS v1.01 entry matches the preserved official workbook literally', function (): void {
    $catalog = b20Catalog();
    $source = dirname(__DIR__, 3).'/resources/fiscal/sources/official/'.$catalog['sources']['nbs']['file'];
    expect(hash_file('sha256', $source))->toBe($catalog['sources']['nbs']['sha256']);
    $official = [];
    foreach (array_slice(b20Sheet($source, 'LISTA.NBS_v2.0'), 1) as $row) {
        if (($row['A'] ?? '') !== '' && ($row['B'] ?? '') !== '') {
            $official[$row['A']] = $row['B'];
        }
    }
    $mismatch = [];
    foreach ($catalog['nbs'] as $code => $description) {
        if (($official[(string) $code] ?? null) !== $description) {
            $mismatch[] = (string) $code;
        }
    }
    expect(count($catalog['nbs']))->toBe(1208)->and($mismatch)->toBe([])->and(count($official))->toBe(1208);
});
