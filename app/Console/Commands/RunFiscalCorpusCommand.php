<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\MunicipalParameters\DatabaseMunicipalParameterProvider;
use App\Modules\Fiscal\Infrastructure\Persistence\DatabaseRuleBundleRepository;
use App\Modules\Fiscal\Infrastructure\Scenario\CorpusRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\DatabaseRuleBundleKnowledgeLoader;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class RunFiscalCorpusCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:corpus:run
        {path? : Scenario directory}
        {--expected= : Expected result directory}
        {--municipal-source-key= : Resolve municipal parameters from this imported database source}
        {--municipal-source-version= : Exact imported municipal source version}
        {--rule-bundle-key= : Load rules from this published database bundle}
        {--rule-bundle-version= : Exact published database rule bundle version}
        {--bundle-manifest= : Bundle manifest path, relative to resources/fiscal or absolute}';

    protected $description = 'Run the fiscal golden-test corpus';

    public function handle(): int
    {
        try {
            $scenarioRoot = (string) ($this->argument('path') ?: resource_path('fiscal/scenarios'));
            $expectedRoot = (string) ($this->option('expected') ?: resource_path('fiscal/expected'));
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
            $report = (new CorpusRunService(
                $runner,
                new JsonSchemaValidator,
                resource_path('fiscal/schemas/expected.schema.json'),
            ))->run($scenarioRoot, $expectedRoot);
            $this->outputJson($report);

            return $report['valid'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
