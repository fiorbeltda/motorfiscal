<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\CatalogImport\IssServiceCatalogMaterializer;
use InvalidArgumentException;
use Throwable;

final class MaterializeIssServiceCatalogCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:catalog:materialize-iss-services
        {ctribnac : Official cTribNac XLSX path}
        {iss-location : Official or derived ISS territorial catalog path}
        {output : Destination JSON path}
        {--catalog-id=ISS_SERVICES : Stable catalog identifier}
        {--catalog-version= : Immutable catalog version}';

    protected $description = 'Materialize the complete ISS service catalog from cTribNac and territorial sources';

    public function handle(): int
    {
        try {
            $version = (string) $this->option('catalog-version');
            if ($version === '') {
                throw new InvalidArgumentException('--catalog-version is required.');
            }
            $output = (string) $this->argument('output');
            if (is_file($output)) {
                throw new InvalidArgumentException("Refusing to overwrite immutable catalog: {$output}");
            }
            $directory = dirname($output);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new InvalidArgumentException("Could not create catalog directory: {$directory}");
            }

            $catalog = (new IssServiceCatalogMaterializer)->materialize(
                (string) $this->argument('ctribnac'),
                (string) $this->argument('iss-location'),
                (string) $this->option('catalog-id'),
                $version,
            );
            file_put_contents($output, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);

            $this->outputJson([
                'output' => $output,
                'content_hash' => CanonicalJson::hash($catalog),
                'coverage' => $catalog['coverage'],
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
