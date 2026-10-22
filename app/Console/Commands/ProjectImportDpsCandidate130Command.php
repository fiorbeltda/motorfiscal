<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsImportCandidate130;
use App\Modules\Fiscal\NFSe\DpsImportCandidatePolicy130;
use App\Modules\Fiscal\NFSe\ImportDocumentValues132;
use RuntimeException;
use Throwable;

final class ProjectImportDpsCandidate130Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:dps:import-candidate
        {snapshot : Integrated nonfinal129 import snapshot}
        {identity : Explicit documentary identity JSON}
        {--values-document= : Single-line BRL invoice values contract132 JSON}
        {--invoice-source= : Original invoice bytes bound by the values document}
        {--candidate-output= : Immutable local candidate XML,only after successful XSD validation}
        {--report= : Immutable local evidence report}';

    protected $description = 'Build a local import DPS candidate without promoting the nonfinal fiscal assessment';

    public function handle(): int
    {
        try {
            $snapshot = $this->readJson((string) $this->argument('snapshot'));
            $identity = $this->readJson((string) $this->argument('identity'));
            $policy = $this->readJson(resource_path('fiscal/projections/import-dps-candidate-2026.10.22-130.json'));
            if (CanonicalJson::hash($policy) !== CanonicalJson::hash(DpsImportCandidatePolicy130::policy())) {
                throw new RuntimeException('Import candidate policy differs from the supported source contract.');
            }
            foreach ($policy['source_observations'] as $source) {
                if (! hash_equals($source['sha256'], (string) hash_file('sha256', resource_path('fiscal/sources/official/'.$source['file'])))) {
                    throw new RuntimeException('Import documentary source missing or changed.');
                }
            }
            $factory = new FiscalEngineFactory;
            $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.22-129-draft1.json');
            if (! is_array($snapshot['knowledge'] ?? null) || ! is_array($snapshot['input'] ?? null)
                || ! is_array($snapshot['result'] ?? null)
                || CanonicalJson::hash($snapshot['knowledge']) !== CanonicalJson::hash($knowledge)) {
                throw new RuntimeException('Snapshot knowledge does not bind the current immutable129 bundle.');
            }
            $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($snapshot['input']);
            $replayed = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);
            if (CanonicalJson::encode($replayed->toArray()) !== CanonicalJson::encode($snapshot['result'])) {
                throw new RuntimeException('Import snapshot fails complete deterministic replay.');
            }
            $valuesPath = $this->option('values-document');
            $sourcePath = $this->option('invoice-source');
            $values = null;
            if (($valuesPath !== null) !== ($sourcePath !== null)) {
                throw new RuntimeException('Values document and invoice source must be supplied together.');
            }
            if ($valuesPath !== null) {
                if (! is_string($valuesPath) || $valuesPath === '' || ! is_string($sourcePath)
                    || $sourcePath === '' || ! is_file($sourcePath) || ! is_readable($sourcePath)) {
                    throw new RuntimeException('Readable invoice source and values document required.');
                }
                $sourceHash = hash_file('sha256', $sourcePath);
                if ($sourceHash === false) {
                    throw new RuntimeException('Unable to hash invoice source.');
                }
                $values = (new ImportDocumentValues132)->reconcile($snapshot, $this->readJson($valuesPath), $sourceHash);
                if ($values['status'] !== 'RECONCILED_DECLARED_VALUES') {
                    $this->outputJson($values);

                    return self::FAILURE;
                }
            }
            $candidate = (new DpsImportCandidate130)->project($snapshot, $identity, $policy);
            if ($candidate['status'] !== 'CANDIDATE_BUILT' || ! is_string($candidate['xml'])) {
                $this->outputJson($candidate);

                return self::FAILURE;
            }
            $schema = $knowledge['documents']['rtc_code_catalog']['dps_xsd'];
            $validation = (new DpsXmlSchemaValidator)->validate($candidate['xml'],
                resource_path('fiscal/sources/official/'.$schema['source_file']), $schema['source_sha256'], $schema['root_schema']);
            if ($validation['status'] !== 'VALID') {
                $this->outputJson(['status' => 'CANDIDATE_REJECTED', 'schema_validation' => $validation,
                    'fiscal_decision_final' => false, 'production_ready' => false]);

                return self::FAILURE;
            }
            $xmlPath = $this->option('candidate-output');
            $reportPath = $this->option('report');
            $paths = array_values(array_filter([$xmlPath, $reportPath], static fn (mixed $path): bool => is_string($path) && $path !== ''));
            if (count($paths) !== count(array_unique($paths))) {
                throw new RuntimeException('Candidate and report paths must be distinct.');
            }
            foreach ($paths as $path) {
                if (file_exists($path) || ! is_dir(dirname($path))) {
                    throw new RuntimeException('Candidate/report destination must be new with an existing parent.');
                }
            }
            $report = ['status' => 'CANDIDATE_VALIDATED', 'scope' => $policy['scope'],
                'schema_validation' => $validation, 'projection' => array_diff_key($candidate, ['xml' => true]),
                'snapshot_replay_matches' => true, 'source_policy_sha256' => CanonicalJson::hash($policy),
                'fiscal_decision_status' => $snapshot['result']['status'], 'fiscal_decision_final' => false,
                'document_obligation_complete' => false, 'production_ready' => false];
            if ($values !== null) {
                $report['document_values132'] = $values;
            }
            if (is_string($xmlPath) && $xmlPath !== '') {
                $this->writeExclusive($xmlPath, $candidate['xml']);
                $report['candidate_output'] = $xmlPath;
            }
            if (is_string($reportPath) && $reportPath !== '') {
                $this->writeExclusive($reportPath, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
            }
            $this->outputJson($report);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(),
                'fiscal_decision_final' => false, 'production_ready' => false]);

            return self::FAILURE;
        }
    }

    private function writeExclusive(string $path, string $body): void
    {
        $stream = @fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException('Unable to create exclusive candidate artifact.');
        }
        try {
            $remaining = $body;
            while ($remaining !== '') {
                $written = fwrite($stream, $remaining);
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Incomplete candidate artifact write.');
                }
                $remaining = substr($remaining, $written);
            }
            if (! fflush($stream) || ! fsync($stream)) {
                throw new RuntimeException('Unable to flush candidate artifact to storage.');
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read import candidate JSON.');
        }
        $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value) || array_is_list($value)) {
            throw new RuntimeException('Import candidate JSON must be an object.');
        }

        return $value;
    }
}
