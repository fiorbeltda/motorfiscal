<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\SplitBoletoPayments161;
use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\ConsumptionReform\SplitPayment158;
use App\Modules\Fiscal\ConsumptionReform\SplitTransaction158;
use RuntimeException;
use Throwable;

final class AssessSplitPayment158Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:assess-split-payment {input : Qualified split JSON} {--mode=arithmetic : arithmetic, transaction, fiscal-history, ledger, schedule, lifecycle, boleto or payment-ledger}';

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
            } elseif ($this->option('mode') === 'schedule') {
                $ledger = new SplitLedger158;
                $report = $ledger->reconcileSchedule($input);
                $replay = $ledger->replaySchedule($report);
            } elseif ($this->option('mode') === 'lifecycle') {
                $ledger = new SplitLedger158;
                $report = $ledger->reconcileLifecycle($input);
                $replay = $ledger->replayLifecycle($report);
            } elseif ($this->option('mode') === 'fiscal-history') {
                $transaction = new SplitTransaction158;
                $report = $transaction->assessHistory($input);
                $replay = $transaction->replayHistory($report);
            } elseif ($this->option('mode') === 'boleto') {
                $boleto = new SplitBoletoPayments161;
                $report = $boleto->assess($input);
                $replay = $boleto->replay($report);
            } elseif ($this->option('mode') === 'payment-ledger') {
                $ledger = new SplitLedger158;
                $report = $ledger->reconcilePayments($input);
                $replay = $ledger->replayPayments($report);
            } else {
                throw new RuntimeException('Unsupported assessment mode.');
            }
            $this->outputJson(['report' => $report, 'replay_matches' => $replay]);

            return in_array($report['status'], ['LOCAL_SPLIT_ARITHMETIC_CALCULATED', 'LOCAL_SPLIT_TRANSACTION_ASSESSED', 'LOCAL_SPLIT_LEDGER_RECONCILED', 'LOCAL_SPLIT_BOLETO_PAYMENTS_ASSESSED'], true)
                && $replay ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'posted' => false]);

            return self::FAILURE;
        }
    }
}
