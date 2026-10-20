<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\JsonFileWriter;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class CalculateDereDebtDailyMemoryCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:dere:calculate-debt-daily-memory {position?}
        {--bundle-manifest=bundles/consumption-phase4b-2026.10.06-26.json}
        {--knowledge-at=} {--snapshot=} {--replay=}';

    protected $description = 'Reconstruct and replay local D2101 daily cost, movements, contractual and Selic memory';

    public function handle(): int
    {
        try {
            $replay = $this->option('replay');
            $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), (string) $this->option('bundle-manifest'));
            $stored = null;
            if (is_string($replay) && $replay !== '') {
                if ($this->argument('position') !== null || $this->option('snapshot') !== null || $this->option('knowledge-at') !== null) {
                    throw new RuntimeException('Replay uses its captured position and knowledge time.');
                }
                $stored = json_decode((string) file_get_contents($replay), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($stored) || ($stored['contract'] ?? null) !== 'DERE-DAILY-MEMORY-SNAPSHOT-1'
                    || CanonicalJson::encode($stored['knowledge'] ?? null) !== CanonicalJson::encode($knowledge)) {
                    throw new RuntimeException('Replay knowledge differs from the selected frozen bundle.');
                }
                $position = $stored['position'] ?? null;
                $time = $stored['known_at'] ?? null;
            } else {
                $path = $this->argument('position');
                if (! is_string($path) || $path === '') {
                    throw new RuntimeException('A position input is required.');
                }
                $position = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                $time = $this->option('knowledge-at') ?? $knowledge['documents']['bundle_manifest']['knowledge_available_at'];
            }
            if (! is_array($position) || array_is_list($position) || ! is_string($time)) {
                throw new RuntimeException('Structured position and knowledge time required.');
            }
            $knownAt = new DateTimeImmutable($time);
            if ($knownAt < new DateTimeImmutable($knowledge['documents']['bundle_manifest']['knowledge_available_at'])) {
                throw new RuntimeException('Bundle was not available at the selected knowledge time.');
            }
            $policy = $knowledge['documents']['rtc_code_catalog']['dere_debt_daily_memory_calculation'] ?? null;
            if (! is_array($policy) || ! is_array($policy['series'] ?? null)
                || ! is_string($policy['official_data_file'] ?? null) || preg_match('/^[A-Za-z0-9_.-]+$/', $policy['official_data_file']) !== 1) {
                throw new RuntimeException('Frozen daily series policy unavailable.');
            }
            $raw = file_get_contents(resource_path('fiscal/sources/official/'.$policy['official_data_file']));
            if (! is_string($raw) || hash('sha256', $raw) !== $policy['official_data_sha256']) {
                throw new RuntimeException('Observed official BCB response hash mismatch.');
            }
            $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new RuntimeException('Official BCB response must be a row list.');
            }
            $normalized = [];
            foreach ($rows as $row) {
                if (! is_array($row) || ! is_string($row['data'] ?? null) || ! is_string($row['valor'] ?? null)) {
                    throw new RuntimeException('Official BCB daily row malformed.');
                }
                $date = DateTimeImmutable::createFromFormat('!d/m/Y', $row['data']);
                if ($date === false || $date->format('d/m/Y') !== $row['data']) {
                    throw new RuntimeException('Official BCB date malformed.');
                }
                $normalized[] = ['date' => $date->format('Y-m-d'), 'daily_percent' => $row['valor']];
            }
            if (CanonicalJson::encode($normalized) !== CanonicalJson::encode($policy['series']['rows'])) {
                throw new RuntimeException('Captured daily rates differ from the observed BCB response.');
            }
            $result = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], $knownAt, $policy);
            if ($stored !== null && CanonicalJson::encode($result) !== CanonicalJson::encode($stored['result'] ?? null)) {
                throw new RuntimeException('Captured calculation did not reproduce.');
            }
            $snapshot = $this->option('snapshot');
            if (is_string($snapshot) && $snapshot !== '' && $result['status'] === 'RESOLVED') {
                (new JsonFileWriter)->write($snapshot, ['contract' => 'DERE-DAILY-MEMORY-SNAPSHOT-1', 'position' => $position,
                    'known_at' => $knownAt->format(DATE_ATOM), 'knowledge' => $knowledge, 'result' => $result]);
            }
            $this->outputJson(['bundle_id' => $knowledge['rule_bundle_id'], 'knowledge_hash' => CanonicalJson::hash($knowledge),
                'result_hash' => CanonicalJson::hash($result), 'result' => $result,
                ...($stored === null ? [] : ['replay_matches' => true]), 'production_ready' => false]);

            return $result['status'] === 'RESOLVED' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
