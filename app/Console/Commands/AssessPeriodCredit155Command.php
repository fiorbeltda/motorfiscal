<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\PeriodCreditAssessment155;
use RuntimeException;
use Throwable;

final class AssessPeriodCredit155Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:assess-period-credit {ledger : Qualified period ledger JSON}';

    protected $description = 'Compose B11 adjustments and B14 utilization without posting or extinguishing debts';

    public function handle(): int
    {
        try {
            $raw = file_get_contents((string) $this->argument('ledger'));
            if ($raw === false) {
                throw new RuntimeException('Readable ledger JSON required.');
            }
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input) || array_is_list($input)) {
                throw new RuntimeException('Ledger JSON object required.');
            }
            $assessor = new PeriodCreditAssessment155;
            $report = $assessor->assess($input);
            $replay = $assessor->replay($report);
            $this->outputJson(['report' => $report, 'replay_matches' => $replay]);

            return $report['status'] === 'LOCAL_PERIOD_CREDIT_ASSESSMENT' && $replay ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'production_ready' => false]);

            return self::FAILURE;
        }
    }
}
