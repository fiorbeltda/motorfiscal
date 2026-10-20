<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use RuntimeException;
use Throwable;

final class ReplayConsumptionReformCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:replay {snapshot : Immutable RTC snapshot JSON path}';

    protected $description = 'Replay an IBS/CBS decision from its captured knowledge';

    public function handle(): int
    {
        try {
            $snapshot = json_decode((string) file_get_contents((string) $this->argument('snapshot')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($snapshot)) {
                throw new RuntimeException('RTC snapshot must be an object.');
            }
            $matches = (new RtcScenarioRunService(new FiscalEngineFactory))->replay($snapshot);
            $this->outputJson(['replay_matches' => $matches, 'original_hash' => $snapshot['result']['decision_hash'] ?? null]);

            return $matches ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
