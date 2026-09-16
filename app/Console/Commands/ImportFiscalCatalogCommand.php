<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Evidence\SourceArtifact;
use App\Modules\Fiscal\Infrastructure\CatalogImport\CountingMunicipalIssRateSink;
use App\Modules\Fiscal\Infrastructure\CatalogImport\DatabaseMunicipalIssRateSink;
use App\Modules\Fiscal\Infrastructure\CatalogImport\IssLocationCatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\MunicipalIssRateImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\NbsCatalogImporter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ImportFiscalCatalogCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:catalog:import
        {type : municipal-rates, iss-location or nbs}
        {path : Source artifact path}
        {--source-key= : Stable source key}
        {--source-version= : Source version}
        {--source-version-id= : Persisted fiscal source version UUID}
        {--dry-run : Parse and validate without persistence}';

    protected $description = 'Stream, validate and optionally persist a fiscal catalog';

    public function handle(): int
    {
        $type = (string) $this->argument('type');
        $path = (string) $this->argument('path');
        $sourceKey = (string) ($this->option('source-key') ?: 'SOURCE.'.strtoupper(str_replace('-', '_', $type)));
        $version = (string) ($this->option('source-version') ?: 'UNSPECIFIED');
        $artifact = SourceArtifact::fromFile($sourceKey, $version, $path, 'IMPORTED_INPUT');

        $report = match ($type) {
            'municipal-rates' => $this->importMunicipalRates($path),
            'iss-location' => (new IssLocationCatalogImporter)->import($path)->toArray(),
            'nbs' => (new NbsCatalogImporter)->import($path)->toArray(),
            default => throw new InvalidArgumentException("Unsupported catalog type: {$type}"),
        };

        $this->outputJson([
            'artifact' => $artifact->manifest(),
            'dry_run' => (bool) $this->option('dry-run'),
            'report' => $report,
        ]);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function importMunicipalRates(string $path): array
    {
        $sourceVersionId = (string) $this->option('source-version-id');

        if (! $this->option('dry-run') && $sourceVersionId === '') {
            throw new InvalidArgumentException('--source-version-id is required unless --dry-run is used.');
        }

        $sink = $this->option('dry-run')
            ? new CountingMunicipalIssRateSink
            : new DatabaseMunicipalIssRateSink(DB::connection(), $sourceVersionId);

        return (new MunicipalIssRateImporter)->import($path, $sink)->toArray();
    }
}
