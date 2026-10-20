<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\MunicipalParameters\DatabaseMunicipalParameterProvider;
use App\Modules\Fiscal\Infrastructure\Persistence\DatabaseDecisionSnapshotRepository;
use App\Modules\Fiscal\Infrastructure\Persistence\DatabaseRuleBundleRepository;
use App\Modules\Fiscal\Infrastructure\Scenario\DatabaseRuleBundleKnowledgeLoader;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\JsonFileWriter;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class RunFiscalScenarioCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:scenario:run
        {path : Scenario JSON path}
        {--snapshot= : Optional path for an immutable replay snapshot}
        {--persist : Persist the immutable execution snapshot in the configured database}
        {--municipal-source-key= : Resolve municipal parameters from this imported database source}
        {--municipal-source-version= : Exact imported municipal source version}
        {--rule-bundle-key= : Load rules from this published database bundle}
        {--rule-bundle-version= : Exact published database rule bundle version}
        {--bundle-manifest= : Bundle manifest path, relative to resources/fiscal or absolute}';

    protected $description = 'Run one headless fiscal scenario and emit JSON';

    public function handle(): int
    {
        try {
            $sourceKey = $this->option('municipal-source-key');
            $sourceVersion = $this->option('municipal-source-version');
            $hasSource = is_string($sourceKey) && $sourceKey !== '';
            $hasVersion = is_string($sourceVersion) && $sourceVersion !== '';
            if ($hasSource !== $hasVersion) {
                throw new InvalidArgumentException('--municipal-source-key and --municipal-source-version must be provided together.');
            }
            $provider = $hasSource && $hasVersion
                ? new DatabaseMunicipalParameterProvider(DB::connection(), $sourceKey, $sourceVersion)
                : null;
            $ruleBundleKey = $this->option('rule-bundle-key');
            $ruleBundleVersion = $this->option('rule-bundle-version');
            $hasRuleBundleKey = is_string($ruleBundleKey) && $ruleBundleKey !== '';
            $hasRuleBundleVersion = is_string($ruleBundleVersion) && $ruleBundleVersion !== '';
            if ($hasRuleBundleKey !== $hasRuleBundleVersion) {
                throw new InvalidArgumentException('--rule-bundle-key and --rule-bundle-version must be provided together.');
            }
            $databaseRules = $hasRuleBundleKey && $hasRuleBundleVersion
                ? new DatabaseRuleBundleKnowledgeLoader(
                    new DatabaseRuleBundleRepository(DB::connection()),
                    $ruleBundleKey,
                    $ruleBundleVersion,
                )
                : null;
            $manifestPath = $this->option('bundle-manifest');
            $runner = new ScenarioRunService(
                new FiscalEngineFactory,
                resource_path('fiscal'),
                $provider,
                $databaseRules,
                is_string($manifestPath) && $manifestPath !== '' ? $manifestPath : null,
            );
            $path = (string) $this->argument('path');
            $execution = $runner->execute($path);
            $result = $execution->result;
            $payload = $result->toArray();
            $snapshotPath = $this->option('snapshot');
            if (is_string($snapshotPath) && $snapshotPath !== '') {
                (new JsonFileWriter)->write($snapshotPath, $execution->snapshot());
            }
            if ((bool) $this->option('persist')) {
                $documents = $execution->knowledge['documents'];
                if (! is_array($documents) || ! isset($documents['fact_definitions']) || ! is_array($documents['fact_definitions'])) {
                    throw new \RuntimeException('Execution knowledge has no fact definitions.');
                }
                $repository = new DatabaseDecisionSnapshotRepository(
                    DB::connection(),
                    (new FactDefinitionFileLoader)->fromArray($documents['fact_definitions']),
                );
                $payload['decision_run_id'] = $repository->persist(
                    $execution->scenario,
                    $result,
                    $execution->knowledge,
                );
            }
            $this->outputJson($payload);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
