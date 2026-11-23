<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\RefundDeadline164;
use App\Modules\Fiscal\ConsumptionReform\RefundIntegration164;
use App\Modules\Fiscal\ConsumptionReform\SplitDeadlineIntegration163;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use RuntimeException;
use Throwable;

final class AssessRefundDeadline164Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:assess-refund-deadlines {input : Supplied refund ledger JSON} {--bundle-manifest=bundles/consumption-phase4b-2026.11.23-a164-draft1.json}';

    protected $description = 'Assess source-bound refund allocations and business-date clocks without posting transfers';

    public function handle(): int
    {
        try {
            $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), (string) $this->option('bundle-manifest'));
            $codes = $knowledge['documents']['rtc_code_catalog'];
            if (! RefundIntegration164::valid($codes)) {
                throw new RuntimeException('Qualified A164 policy required.');
            }
            $raw = file_get_contents((string) $this->argument('input'));
            if ($raw === false) {
                throw new RuntimeException('Readable JSON required.');
            }
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input) || array_is_list($input)) {
                throw new RuntimeException('Assessment object required.');
            }
            $service = new RefundDeadline164($codes[SplitDeadlineIntegration163::CALENDARS]);
            $report = $service->assess($input);
            $replay = $service->replay($report);
            $this->outputJson(['bundle_id' => $knowledge['rule_bundle_id'], 'report' => $report, 'replay_matches' => $replay]);

            return $replay && $report['status'] === 'LOCAL_REFUND_DEADLINES_ASSESSED' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'posted' => false]);

            return self::FAILURE;
        }
    }
}
