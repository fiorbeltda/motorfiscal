<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\AcquirerCollection157;
use RuntimeException;
use Throwable;

final class ReconcileAcquirerCollection157Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:reconcile-acquirer-collection {ledger : Complete qualified collection ledger JSON}';

    protected $description = 'Reconcile article36 receipts and excess transfers without posting, credit appropriation or moving funds';

    public function handle(): int
    {
        try {
            $raw = file_get_contents((string) $this->argument('ledger'));
            if ($raw === false) {
                throw new RuntimeException('Readable ledger required.');
            }
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input) || array_is_list($input)) {
                throw new RuntimeException('Ledger object required.');
            }
            $service = new AcquirerCollection157;
            $report = $service->reconcile($input);
            $replay = $service->replay($report);
            $this->outputJson(['report' => $report, 'replay_matches' => $replay]);

            return $report['status'] === 'LOCAL_ACQUIRER_COLLECTION_RECONCILED' && $replay ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'posted' => false]);

            return self::FAILURE;
        }
    }
}
