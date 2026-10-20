<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('imports official master catalogs as candidates when legal effectiveness is unknown', function (): void {
    $this->artisan('fiscal:catalog:import', [
        'type' => 'lc116',
        'path' => resource_path('fiscal/sources/official/lc116-consolidada-senado-2026-09-16.html'),
        '--source-key' => 'SOURCE.LC116.SENADO.CONSOLIDADA',
        '--source-version' => 'snapshot-2026-09-16',
        '--authority' => 'OFFICIAL_CONSOLIDATED_TEXT',
    ])->assertSuccessful();

    $this->artisan('fiscal:catalog:import', [
        'type' => 'ctribnac',
        'path' => resource_path('fiscal/sources/official/anexo-b-servicos-nacionais-v1.01-20260122.xlsx'),
        '--source-key' => 'SOURCE.NFSE.ANEXO_B.SERVICOS_NACIONAIS',
        '--source-version' => 'v1.01-20260122',
        '--authority' => 'OFFICIAL_TECHNICAL_ARTIFACT',
    ])->assertSuccessful();

    expect(DB::table('fiscal_catalog_versions')->orderBy('version')->pluck('status')->all())
        ->toBe(['CANDIDATE', 'CANDIDATE'])
        ->and(DB::table('fiscal_catalog_versions')->whereNotNull('effective_from')->count())->toBe(0)
        ->and(DB::table('fiscal_catalog_entries')->count())->toBe(543)
        ->and(DB::table('fiscal_import_runs')->orderBy('importer')->pluck('importer')->all())
        ->toBe(['ctribnac-xlsx-importer-v1', 'lc116-html-importer-v1']);
});

it('publishes a master catalog only when a proven effectiveness date is supplied', function (): void {
    $this->artisan('fiscal:catalog:import', [
        'type' => 'lc116',
        'path' => resource_path('fiscal/sources/official/lc116-consolidada-senado-2026-09-16.html'),
        '--source-key' => 'SOURCE.LC116.TEST.PUBLISHED',
        '--source-version' => 'test-effective-version',
        '--effective-from' => '2026-01-01',
    ])->assertSuccessful();

    expect(DB::table('fiscal_catalog_versions')->value('status'))->toBe('PUBLISHED')
        ->and(DB::table('fiscal_catalog_versions')->value('effective_from'))->toBe('2026-01-01');
});
