<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\DpsForeignPolicy128;
use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\JsonFileWriter;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsForeignAcquirerCandidate;
use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection;
use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection127;
use App\Modules\Fiscal\NFSe\DpsXmlProjector;
use App\Modules\Fiscal\NFSe\DpsXmlProjector127;
use RuntimeException;
use Throwable;

final class ProjectDpsXmlCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:dps:project-xml
        {snapshot : Integrated fiscal decision snapshot JSON}
        {identity : Explicit DPS identity JSON}
        {--bundle-manifest=bundles/consumption-phase4b-2026.10.02-14.json}
        {--output= : Immutable local XML path after successful schema validation}
        {--candidate-output= : Immutable local candidate XML path, including a failed schema check}
        {--report= : Immutable local evidence report JSON}';

    protected $description = 'Build a local DPS candidate from an integrated decision and check the bound official XSD';

    public function handle(): int
    {
        try {
            $snapshot = $this->readJson((string) $this->argument('snapshot'));
            $identity = $this->readJson((string) $this->argument('identity'));
            $factory = new FiscalEngineFactory;
            $knowledge = $factory->captureKnowledge(
                resource_path('fiscal'), (string) $this->option('bundle-manifest'));
            $projectionContract = $knowledge['documents']['rtc_code_catalog']['local_xml_projection']['contract_version'] ?? null;
            if (! in_array($projectionContract, [DpsXmlProjector::CONTRACT_VERSION, DpsXmlProjector::NBS_CONTRACT_VERSION, DpsXmlProjector::IMMUNITY_CONTRACT_VERSION, DpsRestrictedSchemaSelection::CONTRACT, DpsForeignAcquirerCandidate::CONTRACT, DpsLayoutPolicy127::CONTRACT, DpsForeignPolicy128::CONTRACT], true)) {
                throw new RuntimeException('Bundle does not bind the supported local DPS projection contract.');
            }
            if (($snapshot['result']['decision']['versions']['rule_bundle_id'] ?? null) !== $knowledge['rule_bundle_id']) {
                throw new RuntimeException('Snapshot decision bundle differs from selected bundle.');
            }
            if (! in_array($snapshot['result']['status'] ?? null, ['VALID', 'VALID_WITH_WARNINGS'], true)) {
                throw new RuntimeException('Snapshot has no final valid integrated decision.');
            }
            if (! is_array($snapshot['input'] ?? null) || ! is_array($snapshot['knowledge'] ?? null)
                || ($snapshot['knowledge']['content_hashes']['bundle_manifest'] ?? null) !== $knowledge['content_hashes']['bundle_manifest']) {
                throw new RuntimeException('Snapshot does not bind the selected bundle content hash.');
            }
            $scenario = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
            $replayed = $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($scenario->input);
            if (CanonicalJson::encode($replayed->toArray()) !== CanonicalJson::encode($snapshot['result'])) {
                throw new RuntimeException('Snapshot decision fails deterministic replay.');
            }
            if (($identity['competence_date'] ?? null) !== ($snapshot['input']['competence_date'] ?? null)) {
                throw new RuntimeException('DPS competence differs from the replayed decision.');
            }
            $selection = null;
            if (in_array($projectionContract, [DpsLayoutPolicy127::CONTRACT, DpsForeignPolicy128::CONTRACT], true)) {
                $selection = (new DpsRestrictedSchemaSelection127)->qualify($identity,
                    $knowledge['documents']['rtc_code_catalog'], (string) ($snapshot['input']['knowledge_at'] ?? ''));
                if ($selection['status'] !== 'VALID') {
                    $this->outputJson(['scope' => 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION', ...$selection]);

                    return self::FAILURE;
                }
            }
            if (in_array($projectionContract, [DpsRestrictedSchemaSelection::CONTRACT, DpsForeignAcquirerCandidate::CONTRACT], true)) {
                $selection = (new DpsRestrictedSchemaSelection)->qualify($identity,
                    $knowledge['documents']['rtc_code_catalog']['local_xml_projection'],
                    $knowledge['documents']['rtc_code_catalog']['dps_xsd'] ?? [],
                    (string) ($snapshot['input']['knowledge_at'] ?? ''));
                if ($selection['status'] !== 'VALID') {
                    $this->outputJson(['scope' => 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION', ...$selection]);

                    return self::FAILURE;
                }
            }
            $projected = in_array($projectionContract, [DpsLayoutPolicy127::CONTRACT, DpsForeignPolicy128::CONTRACT], true)
                ? (new DpsXmlProjector127)->project($snapshot['result']['decision'], $identity,
                    $projectionContract === DpsForeignPolicy128::CONTRACT ? $knowledge['documents']['rtc_code_catalog'] : null)
                : (new DpsXmlProjector)->project($snapshot['result']['decision'], $identity,
                    $projectionContract === DpsForeignAcquirerCandidate::CONTRACT ? $knowledge['documents']['rtc_code_catalog'] : null);
            if ($projected['status'] !== 'PROJECTED' || $projected['xml'] === null) {
                $this->outputJson(['scope' => 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION', ...$projected]);

                return self::FAILURE;
            }
            $schema = $knowledge['documents']['rtc_code_catalog']['dps_xsd'] ?? null;
            if (! is_array($schema) || ! is_string($schema['source_sha256'] ?? null)
                || ! is_string($schema['root_schema'] ?? null)
                || ! is_string($schema['source_file'] ?? null)
                || ! preg_match('/^[A-Za-z0-9_.-]+\.zip$/', $schema['source_file'])) {
                throw new RuntimeException('Bundle does not bind a DPS XSD archive.');
            }
            $validation = (new DpsXmlSchemaValidator)->validate($projected['xml'],
                resource_path('fiscal/sources/official/'.$schema['source_file']),
                $schema['source_sha256'], $schema['root_schema']);
            $output = $this->option('output');
            if ($validation['status'] === 'VALID' && is_string($output) && $output !== '') {
                $this->writeXml($output, $projected['xml']);
            }
            $candidate = $this->option('candidate-output');
            if (is_string($candidate) && $candidate !== '') {
                $this->writeXml($candidate, $projected['xml']);
            }
            $serieConflict = $validation['errors'] !== [] && array_all($validation['errors'],
                static fn (array $error): bool => str_contains($error['message'], "}serie':")
                    && str_contains($error['message'], "[facet 'pattern']"));
            $report = [
                'status' => $serieConflict ? 'ACTION_REQUIRED' : $validation['status'],
                'reason' => $serieConflict ? 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT' : $validation['reason'],
                'scope' => 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION',
                'bundle_id' => $knowledge['rule_bundle_id'],
                'projection_contract' => $projectionContract,
                'snapshot_hash' => CanonicalJson::hash($snapshot),
                'decision_hash' => $replayed->hash(),
                'identity_hash' => CanonicalJson::hash($identity),
                'projection' => array_diff_key($projected, ['xml' => true]),
                'schema_validation' => $validation,
                'output' => $validation['status'] === 'VALID' && is_string($output) && $output !== '' ? $output : null,
                'candidate_output' => is_string($candidate) && $candidate !== '' ? $candidate : null,
                'production_ready' => false,
            ];
            if ($selection !== null) {
                $report['schema_selection'] = $selection;
            }
            $reportPath = $this->option('report');
            if (is_string($reportPath) && $reportPath !== '') {
                (new JsonFileWriter)->write($reportPath, $report);
            }
            $this->outputJson($report);

            return $validation['status'] === 'VALID' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }

    private function writeXml(string $path, string $xml): void
    {
        $stream = @fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException('Refusing to overwrite local DPS XML or unable to create it.');
        }
        try {
            if (fwrite($stream, $xml) !== strlen($xml) || ! fflush($stream)) {
                throw new RuntimeException('Unable to write local DPS XML.');
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('JSON file not found.');
        }
        $content = file_get_contents($path);
        if (! is_string($content)) {
            throw new RuntimeException('Unable to read JSON file.');
        }
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || array_is_list($payload)) {
            throw new RuntimeException('JSON root must be an object.');
        }

        return $payload;
    }
}
