<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\NFSe\PartialExportFederalPair137;
use App\Modules\Fiscal\NFSe\PartialExportFederalPair139;
use RuntimeException;
use Throwable;

final class ProjectPartialExportFederalPair137Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:dps:project-export-pair-withholdings
        {snapshot : Complete integrated nonfinal134 snapshot}
        {declarations : Own-tax,COMEX,documentary,payment and own-selection declarations137}
        {--output-dir= : New directory for two validated unsigned sandbox DPS documents and report}
        {--public-payment-contract= : Explicit139 payment contract; omission preserves archived137 replay}';

    protected $description = 'Replay a partial-export pair and bind own contributions and fresh per-document federal withholdings';

    public function handle(): int
    {
        try {
            $snapshot = $this->readJson((string) $this->argument('snapshot'));
            $declarations = $this->readJson((string) $this->argument('declarations'));
            $keys = ['own_tax_proofs', 'comex_proofs', 'documentary_proof', 'payment_proofs', 'own_selection_proofs'];
            if (count($declarations) !== count($keys) || array_diff(array_keys($declarations), $keys) !== []
                || ! is_array($declarations['payment_proofs']) || ! is_array($declarations['own_selection_proofs'])) {
                throw new RuntimeException('Exact137 declaration groups required.');
            }
            $prior = array_diff_key($declarations, ['payment_proofs' => true, 'own_selection_proofs' => true]);
            $projector = match ($this->option('public-payment-contract')) {
                null => new PartialExportFederalPair137,
                '139' => new PartialExportFederalPair139,
                default => throw new RuntimeException('Unknown public payment contract selection.'),
            };
            $report = $projector->project($snapshot, $prior,
                $declarations['payment_proofs'], $declarations['own_selection_proofs'], resource_path('fiscal'));
            if ($report['status'] !== 'LOCAL_OWN_AND_WITHHELD_PAIR_XML_XSD_VALIDATED') {
                $this->outputJson($report);

                return self::FAILURE;
            }
            $directory = $this->option('output-dir');
            if ($directory !== null) {
                if (! is_string($directory) || $directory === '' || file_exists($directory)
                    || ! is_dir(dirname($directory)) || ! mkdir($directory, 0700)) {
                    throw new RuntimeException('Output directory must be new with an existing parent.');
                }
                foreach ($report['records'] as $document) {
                    $this->writeExclusive($directory.'/'.$document['role'].'.xml', $document['xml']);
                }
                $this->writeExclusive($directory.'/report.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
            }
            $this->outputJson($report);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->outputJson(['status' => 'ERROR', 'error' => $e->getMessage(), 'complete_dps_built' => false,
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
