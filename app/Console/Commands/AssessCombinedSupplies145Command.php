<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\CombinedSupplyAssessment145;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use RuntimeException;
use Throwable;

final class AssessCombinedSupplies145Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:assess-combined-supplies {operation : Specified independent supplies JSON}';

    protected $description = 'Assess separately specified supplies and reconcile local IBS/CBS totals without a combined fiscal document';

    public function handle(): int
    {
        try {
            $raw = file_get_contents((string) $this->argument('operation'));
            if ($raw === false) {
                throw new RuntimeException('Readable operation JSON required.');
            }
            $operation = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($operation) || array_is_list($operation)) {
                throw new RuntimeException('Operation JSON object required.');
            }
            $assessor = new CombinedSupplyAssessment145(new FiscalEngineFactory, resource_path('fiscal'));
            $report = $assessor->assess($operation);
            $replayed = isset($report['report_hash']) && $assessor->replay($report);
            $this->outputJson(['report' => $report, 'replay_matches' => $replayed]);

            return $report['status'] === 'LOCAL_COMBINED_SUPPLIES_ASSESSED' && $replayed ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(),
                'document_obligation_complete' => false, 'production_ready' => false]);

            return self::FAILURE;
        }
    }
}
