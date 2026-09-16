<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\CatalogImport\InMemoryMunicipalIssRateSink;
use App\Modules\Fiscal\Infrastructure\CatalogImport\IssLocationCatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\MunicipalIssRateImporter;

it('parses ISS location rows without converting codes to integers', function (): void {
    $report = (new IssLocationCatalogImporter)->import(base_path('tests/Fixtures/iss-location-sample.md'));

    expect($report->rows)->toHaveCount(3)
        ->and($report->rows[0]->ctribnacCode)->toBe('010101')
        ->and($report->rows[0]->strategy)->toBe('EP')
        ->and($report->rows[1]->requiredDetailGroup)->toBe('obra')
        ->and($report->rows[2]->strategy)->toBeNull();
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
        '3550308;SP;São Paulo;010101;S;2,50;2026-01-01T00:00:00;',
        '3550308;SP;São Paulo;070202;S;;2026-01-01T00:00:00;2026-12-31T00:00:00',
        '3550308;SP;São Paulo;070202;S;;2026-01-01T00:00:00;2026-12-31T00:00:00',
    ]));
    $zip->close();

    try {
        $sink = new InMemoryMunicipalIssRateSink;
        $report = (new MunicipalIssRateImporter)->import($zipPath, $sink);

        expect($report->acceptedRows)->toBe(3)
            ->and($report->blankRates)->toBe(2)
            ->and($report->sink->inserted)->toBe(2)
            ->and($report->sink->duplicates)->toBe(1)
            ->and($sink->rows()[0]->rate)->toBe('2.50');
    } finally {
        unlink($zipPath);
    }
});
