<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\JsonFileWriter;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereDeductionXmlProjector;
use RuntimeException;
use Throwable;

final class ProjectDereDeductionXmlCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:dere:project-deductions {snapshot} {deductions}
        {--bundle-manifest=bundles/consumption-phase4b-2026.10.02-16.json}
        {--candidate-output=} {--report=}';

    protected $description = 'Project a local unsigned monthly D1121 candidate from replayed qualified RTC calculations';

    public function handle(): int
    {
        try {
            $snapshot = json_decode((string) file_get_contents((string) $this->argument('snapshot')), true, 512, JSON_THROW_ON_ERROR);
            $allocation = json_decode((string) file_get_contents((string) $this->argument('deductions')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($snapshot) || ! is_array($allocation) || array_is_list($allocation)
                || ! is_array($snapshot['input'] ?? null) || ! is_array($snapshot['knowledge'] ?? null)) {
                throw new RuntimeException('Snapshot and allocation must bind structured input and captured knowledge.');
            }
            $factory = new FiscalEngineFactory;
            $knowledge = $factory->captureKnowledge(resource_path('fiscal'), (string) $this->option('bundle-manifest'));
            if (($snapshot['knowledge']['content_hashes']['bundle_manifest'] ?? null) !== $knowledge['content_hashes']['bundle_manifest']) {
                throw new RuntimeException('Selected bundle differs from captured snapshot.');
            }
            $scenario = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
            $result = (new RtcScenarioRunService($factory))->evaluate($snapshot['knowledge'], $scenario)->toArray();
            if (CanonicalJson::encode($result) !== CanonicalJson::encode($snapshot['result'] ?? null)
                || ($result['status'] ?? null) !== 'ACTION_REQUIRED'
                || ($result['reason_codes'] ?? null) !== ['RTC_FINANCIAL_DOCUMENT_PENDING']) {
                throw new RuntimeException('Snapshot must reproduce the specific financial document-pending stage; it is not a final integrated decision.');
            }
            $stages = array_column($result['trace'], null, 'stage');
            $policy = $knowledge['documents']['rtc_code_catalog']['dere_deduction_projection'] ?? null;
            if (! is_array($policy) || ! is_array($policy['xsd'] ?? null)) {
                throw new RuntimeException('Bundle does not bind a DeRE projection policy.');
            }
            $projection = (new DereDeductionXmlProjector)->project($stages['FINANCIAL_OPERATION_AND_BASE'], $allocation['deductions'] ?? [],
                $allocation['identity'] ?? [], $scenario->input->context->knowledgeAt, $policy);
            if ($projection['status'] !== 'PROJECTED' || ! is_string($projection['xml'] ?? null)) {
                $this->outputJson($projection);

                return self::FAILURE;
            }
            $schema = $policy['xsd'];
            foreach (['source_file', 'signature_source_file'] as $key) {
                if (! is_string($schema[$key] ?? null) || ! preg_match('/^[A-Za-z0-9_.-]+$/', $schema[$key])) {
                    throw new RuntimeException('Invalid bound schema filename.');
                }
            }
            $validation = (new DereXmlSchemaValidator)->validate($projection['xml'], resource_path('fiscal/sources/official/'.$schema['source_file']),
                $schema['source_sha256'], $projection['schema'],
                resource_path('fiscal/sources/official/'.$schema['signature_source_file']), $schema['signature_source_sha256']);
            $candidate = $this->option('candidate-output');
            if (is_string($candidate) && $candidate !== '') {
                $stream = @fopen($candidate, 'x');
                if ($stream === false) {
                    throw new RuntimeException('Refusing to overwrite candidate XML.');
                }
                try {
                    if (fwrite($stream, $projection['xml']) !== strlen($projection['xml']) || ! fflush($stream)) {
                        throw new RuntimeException('Unable to write local candidate.');
                    }
                } finally {
                    fclose($stream);
                }
            }
            $report = ['status' => $validation['status'], 'reason' => $validation['reason'], 'production_ready' => false,
                'scope' => 'LOCAL_UNSIGNED_D1121_CANDIDATE_PGCC_DOCUMENT_VERACITY_AND_CLOSING_PENDING',
                'bundle_id' => $knowledge['rule_bundle_id'], 'snapshot_hash' => CanonicalJson::hash($snapshot),
                'decision_hash' => $result['decision_hash'], 'deductions_and_identity_hash' => CanonicalJson::hash($allocation),
                'projection' => array_diff_key($projection, ['xml' => true]), 'schema_validation' => $validation,
                'candidate_output' => is_string($candidate) && $candidate !== '' ? $candidate : null];
            $path = $this->option('report');
            if (is_string($path) && $path !== '') {
                (new JsonFileWriter)->write($path, $report);
            }
            $this->outputJson($report);

            return $validation['reason'] === 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
