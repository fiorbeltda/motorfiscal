<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\DocumentIssIntegration134;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsDocumentValues134;
use RuntimeException;
use Throwable;

final class ProjectDpsDocumentValues134Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:dps:project-document-values
        {snapshot : Complete integrated nonfinal134 snapshot}
        {options : Two explicit per-document options JSON records}
        {--output-dir= : New directory for validated local fragments and report}';

    protected $description = 'Project per-document values against the official DPS complex type without claiming a complete DPS';

    public function handle(): int
    {
        try {
            $snapshot = $this->readJson((string) $this->argument('snapshot'));
            $options = $this->readJson((string) $this->argument('options'));
            $factory = new FiscalEngineFactory;
            $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-'.DocumentIssIntegration134::VERSION.'.json');
            foreach (DocumentIssIntegration134::policy()['base_policy']['sources'] as $file => $digest) {
                if (hash_file('sha256', resource_path('fiscal/sources/official/iss133-'.$file)) !== $digest) {
                    throw new RuntimeException('Document ISS source bytes missing or changed.');
                }
            }
            if (! is_array($snapshot['knowledge'] ?? null) || ! is_array($snapshot['input'] ?? null)
                || ! is_array($snapshot['result'] ?? null) || CanonicalJson::hash($snapshot['knowledge']) !== CanonicalJson::hash($knowledge)) {
                throw new RuntimeException('Snapshot knowledge differs from immutable134 bundle.');
            }
            $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($snapshot['input']);
            $fresh = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
            if (CanonicalJson::encode($fresh) !== CanonicalJson::encode($snapshot['result'])) {
                throw new RuntimeException('Snapshot fails complete deterministic replay.');
            }
            if ($fresh['status'] !== 'ACTION_REQUIRED' || $fresh['reason_codes'] !== ['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN']) {
                throw new RuntimeException('Exact nonfinal134 partial-export assessment required.');
            }
            $rtc = array_values(array_filter($fresh['trace'], static fn (array $item): bool => $item['type'] === 'CONSUMPTION_REFORM'));
            $stages = count($rtc) === 1 ? array_values(array_filter($rtc[0]['payload']['trace'],
                static fn (array $item): bool => $item['stage'] === 'PARTIAL_EXPORT_DOCUMENT_ISS134')) : [];
            if (count($stages) !== 1) {
                throw new RuntimeException('Unique fresh per-document ISS134 assessment required.');
            }
            $projection = (new DpsDocumentValues134)->project($stages[0], $options);
            if ($projection['status'] !== 'LOCAL_DOCUMENT_VALUES_PROJECTED') {
                $this->outputJson($projection);

                return self::FAILURE;
            }
            $schema = $knowledge['documents']['rtc_code_catalog']['dps_xsd'];
            foreach ($projection['fragments'] as &$fragment) {
                $fragment['validation'] = (new DpsXmlSchemaValidator)->validateValuesFragment($fragment['xml'],
                    resource_path('fiscal/sources/official/'.$schema['source_file']), $schema['source_sha256'], $schema['root_schema']);
                if ($fragment['validation']['status'] !== 'VALID') {
                    $this->outputJson(['status' => 'FRAGMENT_REJECTED', 'validation' => $fragment['validation'], 'complete_dps_built' => false]);

                    return self::FAILURE;
                }
            }
            unset($fragment);
            $report = ['status' => 'LOCAL_DOCUMENT_VALUES_VALIDATED', 'projection' => $projection,
                'snapshot_hash' => CanonicalJson::hash($snapshot), 'snapshot_replay_matches' => true,
                'validation_scope' => 'DPS_VALUES_FRAGMENTS_ONLY', 'fiscal_decision_status' => 'ACTION_REQUIRED',
                'complete_dps_built' => false, 'country_list_qualified' => false, 'documentary_options_authenticated' => false,
                'document_obligation_complete' => false, 'production_ready' => false];
            $directory = $this->option('output-dir');
            if ($directory !== null) {
                if (! is_string($directory) || $directory === '' || file_exists($directory) || ! is_dir(dirname($directory))
                    || ! mkdir($directory, 0700)) {
                    throw new RuntimeException('Output directory must be new with an existing parent.');
                }
                foreach ($projection['fragments'] as $fragment) {
                    $this->writeExclusive($directory.'/'.$fragment['role'].'-values.xml', $fragment['xml']);
                }
                $this->writeExclusive($directory.'/report.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
            }
            $this->outputJson($report);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'complete_dps_built' => false, 'production_ready' => false]);

            return self::FAILURE;
        }
    }

    /** @return array<int|string, mixed> */
    private function readJson(string $path): array
    {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new RuntimeException('JSON object or records required.');
        }

        return $value;
    }

    private function writeExclusive(string $path, string $raw): void
    {
        $stream = @fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException('Refusing to overwrite fragment artifact.');
        }
        try {
            if (fwrite($stream, $raw) !== strlen($raw) || ! fflush($stream) || ! fsync($stream)) {
                throw new RuntimeException('Unable to persist complete fragment artifact.');
            }
        } finally {
            fclose($stream);
        }
    }
}
