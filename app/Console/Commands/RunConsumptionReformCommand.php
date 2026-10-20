<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Engine\DecisionStatus;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\JsonFileWriter;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Throwable;

final class RunConsumptionReformCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:run {path : Scenario JSON path} {--bundle-manifest=bundles/consumption-phase4b-2026.09.25-1.json} {--snapshot= : Immutable local replay snapshot path}';

    protected $description = 'Run the separate IBS/CBS subengine, including post-ISS transition dates';

    public function handle(): int
    {
        try {
            $execution = (new RtcScenarioRunService(new FiscalEngineFactory))->execute(
                resource_path('fiscal'),
                (string) $this->option('bundle-manifest'),
                (string) $this->argument('path'),
            );
            $snapshotPath = $this->option('snapshot');
            if (is_string($snapshotPath) && $snapshotPath !== '') {
                (new JsonFileWriter)->write($snapshotPath, $execution->snapshot());
            }
            $this->outputJson($execution->result->toArray());

            return $execution->result->status === DecisionStatus::INVALID ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
