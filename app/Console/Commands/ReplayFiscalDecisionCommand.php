<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ReplayFiscalDecisionCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:decision:replay {snapshot : Immutable snapshot JSON path or decision run UUID}';

    protected $description = 'Replay a fiscal decision and verify its deterministic hash';

    public function handle(): int
    {
        try {
            $path = (string) $this->argument('snapshot');
            $persistedHash = null;
            if (is_file($path)) {
                $raw = (string) file_get_contents($path);
            } else {
                [$raw, $persistedHash] = $this->loadPersistedSnapshot($path);
            }
            $snapshot = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($snapshot) || ! isset($snapshot['input'], $snapshot['knowledge'], $snapshot['result']) || ! is_array($snapshot['input']) || ! is_array($snapshot['knowledge']) || ! is_array($snapshot['result'])) {
                throw new RuntimeException('Snapshot structure is invalid.');
            }
            if ($persistedHash !== null && ! hash_equals($persistedHash, CanonicalJson::hash($snapshot))) {
                throw new RuntimeException('Persisted snapshot payload hash mismatch.');
            }
            $factory = new FiscalEngineFactory;
            $scenario = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
            $result = $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($scenario->input);
            $originalHash = isset($snapshot['result']['decision_hash']) ? (string) $snapshot['result']['decision_hash'] : null;
            $matches = $originalHash !== null && hash_equals($originalHash, $result->hash());
            $this->outputJson([
                'replay_matches' => $matches,
                'original_hash' => $originalHash,
                'replay_hash' => $result->hash(),
                'result' => $result->toArray(),
            ]);

            return $matches ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }

    /** @return array{string, string} */
    private function loadPersistedSnapshot(string $runId): array
    {
        $row = DB::table('fiscal_execution_snapshots')
            ->where('decision_run_id', $runId)
            ->first(['payload', 'payload_hash']);
        if (! $row instanceof \stdClass || ! is_string($row->payload) || ! is_string($row->payload_hash)) {
            throw new RuntimeException("Snapshot not found for decision run: {$runId}");
        }

        return [$row->payload, $row->payload_hash];
    }
}
