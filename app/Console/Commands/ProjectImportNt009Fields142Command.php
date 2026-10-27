<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\NFSe\ImportNt009Model142;
use RuntimeException;
use Throwable;

final class ProjectImportNt009Fields142Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:import:project-nt009-fields
        {snapshot : Integrated141 snapshot to replay}
        {invoice : Canonical normalized invoice declaration JSON}
        {evidence : Explicit invoice source evidence JSON}
        {comext : Explicit bound documentary COMEX declaration JSON}';

    protected $description = 'Prepare NT009 documentary fields and report schema incompatibility without producing XML';

    public function handle(): int
    {
        try {
            $snapshot = $this->readObject((string) $this->argument('snapshot'));
            $evidence = $this->readObject((string) $this->argument('evidence'));
            $declaration = $this->readObject((string) $this->argument('comext'));
            $source = file_get_contents((string) $this->argument('invoice'));
            if ($source === false) {
                throw new RuntimeException('Readable normalized invoice required.');
            }
            $factory = new FiscalEngineFactory;
            $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.27-141-draft1.json');
            if (! is_array($snapshot['knowledge'] ?? null) || ! is_array($snapshot['input'] ?? null)
                || ! is_array($snapshot['result'] ?? null)
                || CanonicalJson::hash($snapshot['knowledge']) !== CanonicalJson::hash($knowledge)) {
                throw new RuntimeException('Snapshot must bind the immutable141 knowledge.');
            }
            $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($snapshot['input']);
            $replay = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
            if (CanonicalJson::hash($replay) !== CanonicalJson::hash($snapshot['result'])) {
                throw new RuntimeException('Complete snapshot replay differs.');
            }
            $report = (new ImportNt009Model142)->project($snapshot, $source, $evidence, $declaration, resource_path('fiscal/sources/official'));
            $this->outputJson([...$report, 'snapshot_replay_matches' => true]);

            // No success exit while the observed source cannot validate the required complete document.
            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(),
                'fiscal_decision_final' => false, 'xml_generated' => false,
                'document_obligation_complete' => false, 'production_ready' => false]);

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
