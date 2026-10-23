<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\NFSe\PartialExportDpsPair136;
use RuntimeException;
use Throwable;

final class ProjectPartialExportDpsPair136Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:dps:project-export-pair-own
        {snapshot : Complete integrated nonfinal134 snapshot}
        {declarations : Explicit136 own-tax,COMEX,documentary and own-federal declarations}
        {--output-dir= : New directory for two validated unsigned sandbox DPS documents and report}';

    protected $description = 'Replay134/135 and add own PIS/Cofins fields to a local partial-export DPS pair';

    public function handle(): int
    {
        try {
            $snapshot = $this->readJson((string) $this->argument('snapshot'));
            $declarations = $this->readJson((string) $this->argument('declarations'));
            $report = (new PartialExportDpsPair136)->project($snapshot, $declarations, resource_path('fiscal'));
            $directory = $this->option('output-dir');
            if ($directory !== null) {
                if (! is_string($directory) || $directory === '' || file_exists($directory) || ! is_dir(dirname($directory)) || ! mkdir($directory, 0700)) {
                    throw new RuntimeException('Output directory must be new with an existing parent.');
                }
                foreach ($report['records'] as $document) {
                    $this->writeExclusive($directory.'/'.$document['role'].'.xml', $document['xml']);
                }
                $this->writeExclusive($directory.'/report.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
            }
            $this->outputJson($report);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'complete_dps_built' => false,
                'document_obligation_complete' => false, 'production_ready' => false]);

            return self::FAILURE;
        }
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value) || array_is_list($value)) {
            throw new RuntimeException('JSON object required.');
        }

        return $value;
    }

    private function writeExclusive(string $path, string $raw): void
    {
        $stream = @fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException('Refusing to overwrite a pair artifact.');
        }
        try {
            if (fwrite($stream, $raw) !== strlen($raw) || ! fflush($stream) || ! fsync($stream)) {
                throw new RuntimeException('Unable to persist complete pair artifact.');
            }
        } finally {
            fclose($stream);
        }
    }
}
