<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\CatalogImport\CatalogCoverageAnalyzer;
use App\Modules\Fiscal\Infrastructure\CatalogImport\CountingMunicipalIssRateSink;
use App\Modules\Fiscal\Infrastructure\CatalogImport\CtribNacCatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\InMemoryMunicipalIssRateSink;
use App\Modules\Fiscal\Infrastructure\CatalogImport\IssLocationCatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\Lc116CatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\MunicipalIssRateImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\RestrictedXlsxReader;

it('imports the official LC 116 snapshot without mixing legislative annotations into legal text', function (): void {
    $report = (new Lc116CatalogImporter)->import(
        dirname(__DIR__, 3).'/resources/fiscal/sources/official/lc116-consolidada-senado-2026-09-16.html',
    );
    $byCode = [];
    foreach ($report->entries as $entry) {
        $byCode[$entry->code] = $entry;
    }

    expect($report->toArray())
        ->items->toBe(40)
        ->entries->toBe(205)
        ->active_entries->toBe(200)
        ->vetoed_codes->toBe(['03.01', '07.14', '07.15', '13.01', '17.07'])
        ->issues->toBe([])
        ->and($byCode['01.03']->legalDescription)->not->toContain('Subitem com redação')
        ->and($byCode['01.03']->sourceAnnotations)->toHaveCount(1)
        ->and($byCode['26.01']->itemLegalTitle)->not->toContain('Vide ADI');
});

it('imports the official cTribNac worksheet with stable string codes and the special non-ISS entry', function (): void {
    $report = (new CtribNacCatalogImporter)->import(
        dirname(__DIR__, 3).'/resources/fiscal/sources/official/anexo-b-servicos-nacionais-v1.01-20260122.xlsx',
    );
    $byCode = [];
    foreach ($report->entries as $entry) {
        $byCode[$entry->code] = $entry;
    }

    expect($report->toArray())
        ->entries->toBe(338)
        ->lc116_linked_entries->toBe(337)
        ->special_entries->toBe(1)
        ->referenced_lc116_codes->toBe(200)
        ->single_code_lc116_items->toBe(138)
        ->multiple_code_lc116_items->toBe(62)
        ->issues->toBe([])
        ->and($byCode['010101']->formattedCode)->toBe('01.01.01')
        ->and($byCode['990101']->specialNoIss)->toBeTrue()
        ->and($byCode['990101']->lc116Code)->toBeNull();
});

it('reconciles the master catalogs without inferring missing territorial rules', function (): void {
    $lc116 = (new Lc116CatalogImporter)->import(
        dirname(__DIR__, 3).'/resources/fiscal/sources/official/lc116-consolidada-senado-2026-09-16.html',
    );
    $ctribNac = (new CtribNacCatalogImporter)->import(
        dirname(__DIR__, 3).'/resources/fiscal/sources/official/anexo-b-servicos-nacionais-v1.01-20260122.xlsx',
    );
    $territorial = (new IssLocationCatalogImporter)->import(dirname(__DIR__, 2).'/Fixtures/iss-location-sample.md');

    $report = (new CatalogCoverageAnalyzer)->analyze($lc116->entries, $ctribNac->entries, $territorial->rows);

    expect($report['active_lc116_codes'])->toBe(200)
        ->and($report['ctribnac_codes'])->toBe(338)
        ->and($report['territorial_complete'])->toBeFalse()
        ->and($report['missing_lc116_references'])->toBe([])
        ->and($report['invalid_or_vetoed_lc116_references'])->toBe([])
        ->and($report['missing_territorial_codes'])->toContain('200102');
});

it('rejects formulas and worksheet relationships outside xl worksheets', function (): void {
    $makeWorkbook = static function (string $target, string $worksheetXml): string {
        $temporary = tempnam(sys_get_temp_dir(), 'restricted-xlsx-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create temporary XLSX path.');
        }
        unlink($temporary);
        $path = $temporary.'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="LISTA.SERV.NAC." sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="'.$target.'"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->close();

        return $path;
    };
    $formulaPath = $makeWorkbook(
        'worksheets/sheet1.xml',
        '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>',
    );
    $escapePath = $makeWorkbook(
        '../evil.xml',
        '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>',
    );

    try {
        expect(fn () => (new RestrictedXlsxReader)->readWorksheet($formulaPath, 'LISTA.SERV.NAC.'))
            ->toThrow(RuntimeException::class, 'Formulas are not permitted')
            ->and(fn () => (new RestrictedXlsxReader)->readWorksheet($escapePath, 'LISTA.SERV.NAC.'))
            ->toThrow(RuntimeException::class, 'must remain inside xl/worksheets');
    } finally {
        unlink($formulaPath);
        unlink($escapePath);
    }
});

it('parses ISS location rows without converting codes to integers', function (): void {
    $report = (new IssLocationCatalogImporter)->import(dirname(__DIR__, 2).'/Fixtures/iss-location-sample.md');

    expect($report->rows)->toHaveCount(3)
        ->and($report->rows[0]->ctribnacCode)->toBe('010101')
        ->and($report->rows[0]->strategy)->toBe('EP')
        ->and($report->rows[1]->requiredDetailGroup)->toBe('obra')
        ->and($report->rows[2]->strategy)->toBeNull()
        ->and($report->declaredRows)->toBe(3)
        ->and($report->documentedDivergences)->toBe(['14.14.01', '11.03.01'])
        ->and(array_column($report->toArray()['issues'], 'code'))->toContain('SOURCE_INTERNAL_DIVERGENCE')
        ->and(array_column($report->toArray()['issues'], 'code'))->toContain('TERRITORIAL_CATALOG_PARTIAL_AGAINST_CTRIBNAC_REFERENCE');
});

it('streams locale-aware municipal rates and preserves blank rates', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'fiscal-rates-');
    if ($temporary === false) {
        throw new RuntimeException('Could not create temporary ZIP path.');
    }
    unlink($temporary);
    $zipPath = $temporary.'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('SP.csv', implode("\n", [
        "\xEF\xBB\xBFcodigo_ibge;uf;nome_municipio;codigo_servico;incidencia;aliquota;dt_ini;dt_fim",
        '3550308;SP;São Paulo;01.01.01.000;S;2,50;2026-01-01T00:00:00;',
        '3550308;SP;São Paulo;07.02.02.000;S;;2026-01-01T00:00:00;2026-12-31T00:00:00',
        '3550308;SP;São Paulo;07.02.02.000;S;;2026-01-01T00:00:00;2026-12-31T00:00:00',
    ]));
    $zip->close();

    try {
        $sink = new InMemoryMunicipalIssRateSink;
        $report = (new MunicipalIssRateImporter)->import($zipPath, $sink);

        expect($report->acceptedRows)->toBe(3)
            ->and($report->blankRates)->toBe(2)
            ->and($report->sink->inserted)->toBe(2)
            ->and($report->sink->duplicates)->toBe(1)
            ->and($report->logicalHash)->toHaveLength(64)
            ->and($sink->rows()[0]->rate)->toBe('2.50')
            ->and($sink->rows()[0]->serviceCode)->toBe('01.01.01.000')
            ->and($sink->rows()[0]->ctribnacCode)->toBe('010101')
            ->and($sink->rows()[0]->municipalServiceCode)->toBe('000');
    } finally {
        unlink($zipPath);
    }
});

it('rejects non-official municipal service code shapes and hashes normalized rows deterministically', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'fiscal-rates-shape-');
    if ($temporary === false) {
        throw new RuntimeException('Could not create temporary ZIP path.');
    }
    unlink($temporary);
    $zipPath = $temporary.'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('SP.csv', implode("\n", [
        'codigo_ibge;uf;nome_municipio;codigo_servico;incidencia;aliquota;dt_ini;dt_fim',
        '3550308;SP;São Paulo;01.01.01.000;S;2,00;2026-01-01T00:00:00;',
        '3550308;SP;São Paulo;01.01.01.000;S;2,00;2026-01-01T00:00:00;',
        '3550308;SP;São Paulo;010101;S;2,00;2026-01-01T00:00:00;',
    ]));
    $zip->close();

    try {
        $first = (new MunicipalIssRateImporter)->import($zipPath, new CountingMunicipalIssRateSink);
        $second = (new MunicipalIssRateImporter)->import($zipPath, new CountingMunicipalIssRateSink);

        expect($first->acceptedRows)->toBe(2)
            ->and($first->rejectedRows)->toBe(1)
            ->and($first->sink->inserted)->toBe(1)
            ->and($first->sink->duplicates)->toBe(1)
            ->and($first->logicalHash)->toBe($second->logicalHash)
            ->and($first->issues[0]->code)->toBe('INVALID_KEY_FIELDS');
    } finally {
        unlink($zipPath);
    }
});
