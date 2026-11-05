<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\ConsumptionReform\SplitPayment158;
use App\Modules\Fiscal\ConsumptionReform\SplitTransaction158;
use RuntimeException;
use Throwable;

final class AssessSplitPayment158Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:assess-split-payment {input : Qualified split JSON} {--mode=arithmetic : arithmetic, transaction or ledger}';

    protected $description = 'Calculate local standard split arithmetic without authorizing deployment or posting extinction';

    public function handle(): int
    {
        try {
            $raw = file_get_contents((string) $this->argument('input'));
            if ($raw === false) {
                throw new RuntimeException('Readable input required.');
            }
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input) || array_is_list($input)) {
                throw new RuntimeException('Calculation object required.');
            }
            if ($this->option('mode') === 'arithmetic') {
                $service = new SplitPayment158;
                $report = $service->calculateSegregation($input);
                $replay = $service->replay($report);
            } elseif ($this->option('mode') === 'transaction') {
                $transaction = new SplitTransaction158;
                $report = $transaction->assess($input);
                $replay = $transaction->replay($report);
            } elseif ($this->option('mode') === 'ledger') {
                $ledger = new SplitLedger158;
                $report = $ledger->reconcile($input);
                $replay = $ledger->replay($report);
            } else {
                throw new RuntimeException('Unsupported assessment mode.');
            }
            $this->outputJson(['report' => $report, 'replay_matches' => $replay]);

            return in_array($report['status'], ['LOCAL_SPLIT_ARITHMETIC_CALCULATED', 'LOCAL_SPLIT_TRANSACTION_ASSESSED', 'LOCAL_SPLIT_LEDGER_RECONCILED'], true)
                && $replay ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'posted' => false]);

            return self::FAILURE;
        }
    }
}
