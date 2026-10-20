<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

use App\Modules\Fiscal\Core\Engine\FiscalEvaluationResult;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\MunicipalParameters\CapturingMunicipalParameterProvider;
use App\Modules\Fiscal\ISS\MunicipalParameterProvider;
use RuntimeException;

final readonly class ScenarioRunService
{
    public function __construct(
        private FiscalEngineFactory $factory,
        private string $resourceRoot,
        private ?MunicipalParameterProvider $municipalProvider = null,
        private ?DatabaseRuleBundleKnowledgeLoader $databaseRules = null,
        private ?string $bundleManifestPath = null,
    ) {}

    public function run(string $scenarioPath): FiscalEvaluationResult
    {
        return $this->execute($scenarioPath)->result;
    }

    /** @return array<string, mixed> */
    public function snapshot(string $scenarioPath): array
    {
        return $this->execute($scenarioPath)->snapshot();
    }

    public function execute(string $scenarioPath): ScenarioExecution
    {
        $knowledge = $this->factory->captureKnowledge($this->resourceRoot, $this->bundleManifestPath);
        if ($this->databaseRules !== null) {
            $knowledge = $this->databaseRules->loadInto($knowledge);
        }
        $scenario = $this->factory->scenarioLoaderFromKnowledge($knowledge)->load($scenarioPath);
        if ($this->municipalProvider === null) {
            $result = $this->factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

            return new ScenarioExecution($scenario, $result, $knowledge);
        }

        $capturingProvider = new CapturingMunicipalParameterProvider($this->municipalProvider);
        $this->factory->makeFromKnowledge($knowledge, $capturingProvider)->evaluate($scenario->input);
        $documents = $knowledge['documents'] ?? null;
        $hashes = $knowledge['content_hashes'] ?? null;
        $catalogVersions = $knowledge['catalog_versions'] ?? null;
        if (! is_array($documents) || ! is_array($hashes) || ! is_array($catalogVersions)) {
            throw new RuntimeException('Knowledge cannot receive a municipal execution snapshot.');
        }
        $municipalVersion = $catalogVersions['municipal_parameters'] ?? null;
        if (! is_string($municipalVersion) || $municipalVersion === '') {
            throw new RuntimeException('Municipal catalog version is missing.');
        }
        $baseMunicipalHash = $hashes['municipal_parameters'] ?? null;
        if (! is_string($baseMunicipalHash) || $baseMunicipalHash === '') {
            throw new RuntimeException('Municipal base catalog hash is missing.');
        }
        $documents['municipal_parameters'] = $capturingProvider->snapshotCatalog($municipalVersion, $baseMunicipalHash);
        $hashes['municipal_parameters'] = CanonicalJson::hash($documents['municipal_parameters']);
        $knowledge['documents'] = $documents;
        $knowledge['content_hashes'] = $hashes;
        $result = $this->factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

        return new ScenarioExecution($scenario, $result, $knowledge);
    }
}
