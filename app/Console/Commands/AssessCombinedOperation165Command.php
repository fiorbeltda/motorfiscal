<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\CombinedOperation165;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use RuntimeException;
use Throwable;

final class AssessCombinedOperation165Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:assess-combined-operation {input : Specified supplies and document allocations JSON} {--bundle-manifest=bundles/consumption-phase4b-2026.11.23-a165-draft1.json}';

    protected $description = 'Execute each combined supply in both runners; preserve documentary dependencies and nominal arithmetic separately';

    public function handle(): int
    {
        try {
            $raw = file_get_contents((string) $this->argument('input'));
            if ($raw === false) {
                throw new RuntimeException('Readable JSON required.');
            }
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input) || array_is_list($input)) {
                throw new RuntimeException('Operation object required.');
            }
            $service = new CombinedOperation165(new FiscalEngineFactory, resource_path('fiscal'), (string) $this->option('bundle-manifest'));
            $report = $service->assess($input);
            $replay = $service->replay($report);
            $this->outputJson(['report' => $report, 'replay_matches' => $replay]);

            return $replay && $report['status'] === 'LOCAL_COMBINED_OPERATION_CALCULATED' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'posting_authorized' => false]);

            return self::FAILURE;
        }
    }
}
