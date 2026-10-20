<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Evidence\SourceArtifact;
use App\Modules\Fiscal\Infrastructure\CatalogImport\CountingMunicipalIssRateSink;
use App\Modules\Fiscal\Infrastructure\CatalogImport\CtribNacCatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\DatabaseMunicipalCoverageBuilder;
use App\Modules\Fiscal\Infrastructure\CatalogImport\DatabaseMunicipalIssRateSink;
use App\Modules\Fiscal\Infrastructure\CatalogImport\DatabaseMunicipalRateDiffService;
use App\Modules\Fiscal\Infrastructure\CatalogImport\DatabaseMunicipalRateIntegrityAnalyzer;
use App\Modules\Fiscal\Infrastructure\CatalogImport\IssLocationCatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\Lc116CatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\MunicipalIssRateImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\MunicipalParameterSnapshotImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\NbsCatalogImporter;
use App\Modules\Fiscal\Infrastructure\Persistence\FiscalCatalogPublisher;
use App\Modules\Fiscal\Infrastructure\Persistence\FiscalImportRunRecorder;
use App\Modules\Fiscal\Infrastructure\Persistence\FiscalSourceRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class ImportFiscalCatalogCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:catalog:import
        {type : municipal-rates, municipal-parameters, iss-location, nbs, lc116 or ctribnac}
        {path : Source artifact path}
        {--source-key= : Stable source key}
        {--source-version= : Source version}
        {--source-version-id= : Optional assertion for an already registered source version UUID}
        {--authority=OFFICIAL_FILE : Authority/source type}
        {--published-on= : Publication date (YYYY-MM-DD)}
        {--effective-from= : Proven fiscal validity start; without it catalog versions remain CANDIDATE}
        {--compare-to= : Previous source version UUID for ADDED/CHANGED/REMOVED/UNCHANGED diff}
        {--dry-run : Parse and validate without persistence}';

    protected $description = 'Stream, validate and optionally persist a fiscal catalog';

    public function handle(): int
    {
        try {
            $type = (string) $this->argument('type');
            $path = (string) $this->argument('path');
            $sourceKey = (string) ($this->option('source-key') ?: 'SOURCE.'.strtoupper(str_replace('-', '_', $type)));
            $version = (string) ($this->option('source-version') ?: 'UNSPECIFIED');
            $artifact = SourceArtifact::fromFile($sourceKey, $version, $path, 'IMPORTED_INPUT');
            $dryRun = (bool) $this->option('dry-run');
            $sourceVersionId = null;
            $importRunId = null;

            if ($dryRun) {
                $report = $this->dryRun($type, $path);
            } else {
                if ($version === 'UNSPECIFIED') {
                    throw new InvalidArgumentException('--source-version is required for persistence.');
                }
                $connection = DB::connection();
                $sourceVersionId = (new FiscalSourceRegistry($connection))->register(
                    $artifact,
                    (string) $this->option('authority'),
                    $this->nullableOption('published-on'),
                    $this->nullableOption('effective-from'),
                );
                $assertedId = (string) $this->option('source-version-id');
                if ($assertedId !== '' && $assertedId !== $sourceVersionId) {
                    throw new InvalidArgumentException('--source-version-id does not match the registered artifact.');
                }
                $recorder = new FiscalImportRunRecorder($connection);
                $importRunId = $recorder->start($sourceVersionId, $this->importerName($type), $artifact->manifest());
                try {
                    $report = $connection->transaction(fn (): array => $this->persist($type, $path, $version, $sourceVersionId));
                    $recorder->finish($importRunId, 'COMPLETED', $report);
                } catch (Throwable $exception) {
                    $recorder->finish($importRunId, 'FAILED', ['error' => $exception->getMessage()]);
                    throw $exception;
                }
            }

            $this->outputJson([
                'artifact' => $artifact->manifest(),
                'dry_run' => $dryRun,
                'source_version_id' => $sourceVersionId,
                'import_run_id' => $importRunId,
                'report' => $report,
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }

    /** @return array<string, mixed> */
    private function dryRun(string $type, string $path): array
    {
        $report = match ($type) {
            'municipal-rates' => (new MunicipalIssRateImporter)->import($path, new CountingMunicipalIssRateSink)->toArray(),
            'municipal-parameters' => (new MunicipalParameterSnapshotImporter)->import($path),
            'iss-location' => (new IssLocationCatalogImporter)->import($path)->toArray(),
            'nbs' => (new NbsCatalogImporter)->import($path)->toArray(),
            'lc116' => (new Lc116CatalogImporter)->import($path)->toArray(),
            'ctribnac' => (new CtribNacCatalogImporter)->import($path)->toArray(),
            default => throw new InvalidArgumentException("Unsupported catalog type: {$type}"),
        };
        $this->assertNonEmpty($type, $report);

        return $report;
    }

    /** @return array<string, mixed> */
    private function persist(string $type, string $path, string $version, string $sourceVersionId): array
    {
        $connection = DB::connection();
        if ($type === 'municipal-rates') {
            $report = (new MunicipalIssRateImporter)->import(
                $path,
                new DatabaseMunicipalIssRateSink($connection, $sourceVersionId),
            )->toArray();
            $this->assertNonEmpty($type, $report);
            $report['coverage'] = (new DatabaseMunicipalCoverageBuilder($connection))->rebuild($sourceVersionId);
            $report['integrity'] = (new DatabaseMunicipalRateIntegrityAnalyzer($connection))->analyze($sourceVersionId)->toArray();
            $previous = (string) $this->option('compare-to');
            if ($previous !== '') {
                $report['diff'] = (new DatabaseMunicipalRateDiffService($connection))->compare($previous, $sourceVersionId)->toArray();
            }

            return $report;
        }
        if ($type === 'municipal-parameters') {
            $report = (new MunicipalParameterSnapshotImporter)->import($path, $connection, $sourceVersionId);
            $this->assertNonEmpty($type, $report);

            return $report;
        }

        $publisher = new FiscalCatalogPublisher($connection);
        if ($type === 'iss-location') {
            $import = (new IssLocationCatalogImporter)->import($path);
            $report = $import->toArray();
            $this->assertNonEmpty($type, $report);
            $report['catalog'] = $publisher->publish('ISS_LOCATION', 'ISS territorial location', $version, $sourceVersionId, $this->nullableOption('effective-from'), $import->rows);

            return $report;
        }
        if ($type === 'nbs') {
            $import = (new NbsCatalogImporter)->import($path);
            $report = $import->toArray();
            $this->assertNonEmpty($type, $report);
            $report['catalog'] = $publisher->publish('NBS', 'Nomenclatura Brasileira de Serviços', $version, $sourceVersionId, $this->nullableOption('effective-from'), $import->entries);

            return $report;
        }
        if ($type === 'lc116') {
            $import = (new Lc116CatalogImporter)->import($path);
            $report = $import->toArray();
            $this->assertNonEmpty($type, $report);
            $report['catalog'] = $publisher->publish('LC116', 'Lista de serviços da LC 116', $version, $sourceVersionId, $this->nullableOption('effective-from'), $import->entries);

            return $report;
        }
        if ($type === 'ctribnac') {
            $import = (new CtribNacCatalogImporter)->import($path);
            $report = $import->toArray();
            $this->assertNonEmpty($type, $report);
            $report['catalog'] = $publisher->publish('CTRIBNAC', 'Código de Tributação Nacional da NFS-e', $version, $sourceVersionId, $this->nullableOption('effective-from'), $import->entries);

            return $report;
        }

        throw new InvalidArgumentException("Unsupported catalog type: {$type}");
    }

    /** @param array<string, mixed> $report */
    private function assertNonEmpty(string $type, array $report): void
    {
        $count = match ($type) {
            'municipal-rates', 'municipal-parameters' => (int) ($report['accepted_rows'] ?? 0),
            'iss-location' => (int) ($report['rows'] ?? 0),
            'nbs', 'lc116', 'ctribnac' => (int) ($report['entries'] ?? 0),
            default => 0,
        };
        if ($count === 0) {
            throw new InvalidArgumentException("Import rejected: {$type} produced no valid entries.");
        }
    }

    private function importerName(string $type): string
    {
        return match ($type) {
            'municipal-rates' => 'municipal-iss-rate-importer-v1',
            'municipal-parameters' => 'municipal-parameter-snapshot-importer-v1',
            'iss-location' => 'iss-location-importer-v1',
            'nbs' => 'nbs-importer-v1',
            'lc116' => 'lc116-html-importer-v1',
            'ctribnac' => 'ctribnac-xlsx-importer-v1',
            default => throw new InvalidArgumentException("Unsupported catalog type: {$type}"),
        };
    }

    private function nullableOption(string $name): ?string
    {
        $value = $this->option($name);
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException("--{$name} must be a string.");
        }

        return $value;
    }
}
