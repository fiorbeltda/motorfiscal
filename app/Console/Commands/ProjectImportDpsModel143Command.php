<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\NFSe\ImportDpsModel143;
use RuntimeException;
use Throwable;

final class ProjectImportDpsModel143Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:import:project-dps-model
        {snapshot : Exact integrated141 snapshot to replay}
        {invoice : Canonical normalized invoice declaration}
        {evidence : Explicit invoice source evidence}
        {comext : Explicit documentary COMEX declaration}
        {identity : Explicit document identity and parties}';

    protected $description = 'Assemble a complete local import DPS while retaining observed XSD/application refusals';

    public function handle(): int
    {
        try {
            $snapshot = $this->readObject((string) $this->argument('snapshot'));
            $source = file_get_contents((string) $this->argument('invoice'));
            if ($source === false) {
                throw new RuntimeException('Readable normalized invoice required.');
            }
            $factory = new FiscalEngineFactory;
            $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.27-141-draft1.json');
            if (! is_array($snapshot['knowledge'] ?? null) || ! is_array($snapshot['input'] ?? null)
                || ! is_array($snapshot['result'] ?? null) || CanonicalJson::hash($snapshot['knowledge']) !== CanonicalJson::hash($knowledge)) {
                throw new RuntimeException('Snapshot must bind the immutable141 knowledge.');
            }
            $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($snapshot['input']);
            if (CanonicalJson::hash($factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray()) !== CanonicalJson::hash($snapshot['result'])) {
                throw new RuntimeException('Complete snapshot replay differs.');
            }
            $report = (new ImportDpsModel143)->project($snapshot, $source,
                $this->readObject((string) $this->argument('evidence')), $this->readObject((string) $this->argument('comext')),
                $this->readObject((string) $this->argument('identity')), resource_path('fiscal/sources/official'),
                resource_path('fiscal/projections/import-dps-nt009-2026.10.27-143.json'));
            $this->outputJson([...$report, 'snapshot_replay_matches' => true]);

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'xml_generated' => false,
                'document_obligation_complete' => false, 'fiscal_decision_final' => false, 'production_ready' => false]);

            return self::FAILURE;
        }
    }

    /** @return array<string,mixed> */
    private function readObject(string $path): array
    {
        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException('Readable JSON object required.');
        }
        $object = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($object) || array_is_list($object)) {
            throw new RuntimeException('JSON object required.');
        }

        return $object;
    }
}
