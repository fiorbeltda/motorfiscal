<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('imports official municipal codes, consumes them and replays without the live database provider', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'fiscal-official-rates-');
    if ($temporary === false) {
        throw new RuntimeException('Could not create temporary ZIP path.');
    }
    unlink($temporary);
    $zipPath = $temporary.'.zip';
    $snapshotPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fiscal-snapshot-'.Str::uuid()->toString().'.json';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('SP.csv', implode("\n", [
        'codigo_ibge;uf;nome_municipio;codigo_servico;incidencia;aliquota;dt_ini;dt_fim',
        '3552205;SP;Sorocaba;07.02.02.000;S;5,00;2026-01-01T00:00:00;',
    ]));
    $zip->close();

    try {
        $this->artisan('fiscal:catalog:import', [
            'type' => 'municipal-rates',
            'path' => $zipPath,
            '--source-key' => 'TEST.MUNICIPAL.OFFICIAL',
            '--source-version' => '2026-09-03-extr1',
            '--authority' => 'OFFICIAL_FILE',
            '--effective-from' => '2026-01-01',
        ])->assertSuccessful();
        $this->artisan('fiscal:catalog:import', [
            'type' => 'municipal-rates',
            'path' => $zipPath,
            '--source-key' => 'TEST.MUNICIPAL.OFFICIAL',
            '--source-version' => '2026-09-03-extr1',
            '--authority' => 'OFFICIAL_FILE',
            '--effective-from' => '2026-01-01',
        ])->assertSuccessful();
        DB::table('fiscal_source_versions')->update(['obtained_at' => '2026-09-16 10:00:00']);

        expect(DB::table('fiscal_source_versions')->count())->toBe(1)
            ->and(DB::table('fiscal_import_runs')->count())->toBe(2)
            ->and(DB::table('municipal_iss_rates')->count())->toBe(1)
            ->and(DB::table('municipal_iss_rates')->value('service_code'))->toBe('07.02.02.000')
            ->and(DB::table('municipal_iss_rates')->value('ctribnac_code'))->toBe('070202')
            ->and(DB::table('municipal_iss_rates')->value('municipal_service_code'))->toBe('000');

        $this->artisan('fiscal:scenario:run', [
            'path' => resource_path('fiscal/scenarios/construction/valid.json'),
            '--municipal-source-key' => 'TEST.MUNICIPAL.OFFICIAL',
            '--municipal-source-version' => '2026-09-03-extr1',
            '--snapshot' => $snapshotPath,
        ])->assertSuccessful();

        $snapshot = json_decode((string) file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
        expect($snapshot['result']['status'])->toBe('VALID_WITH_WARNINGS')
            ->and($snapshot['result']['decision']['iss']['municipal_parameters']['source_type'])->toBe('OFFICIAL_FILE')
            ->and($snapshot['result']['decision']['iss']['municipal_parameters']['source_id'])->toBe('TEST.MUNICIPAL.OFFICIAL')
            ->and($snapshot['result']['decision']['iss']['municipal_parameters']['source_version'])->toBe('2026-09-03-extr1')
            ->and($snapshot['result']['decision']['iss']['municipal_parameters']['rate'])->toBe('5.000000')
            ->and($snapshot['knowledge']['documents']['municipal_parameters']['responses'])->toHaveCount(1)
            ->and($snapshot['knowledge']['content_hashes']['municipal_parameters'])->toBe(
                CanonicalJson::hash($snapshot['knowledge']['documents']['municipal_parameters']),
            );

        DB::table('municipal_iss_rates')->delete();
        $this->artisan('fiscal:decision:replay', ['snapshot' => $snapshotPath])->assertSuccessful();
    } finally {
        if (is_file($zipPath)) {
            unlink($zipPath);
        }
        if (is_file($snapshotPath)) {
            unlink($snapshotPath);
        }
    }
});
