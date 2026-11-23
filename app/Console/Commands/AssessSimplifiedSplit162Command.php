<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitCalculation162;
use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitIntegration162;
use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitPeriod162;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use RuntimeException;
use Throwable;

final class AssessSimplifiedSplit162Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:assess-simplified-split {input : Supplied legal split JSON} {--mode=calculation : calculation or period} {--bundle-manifest=bundles/consumption-phase4b-2026.11.23-a162-draft1.json}';

    protected $description = 'Assess local art33 calculation or period records without posting extinction or granting credit';

    public function handle(): int
    {
        try {
            $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), (string) $this->option('bundle-manifest'));
            $codes = $knowledge['documents']['rtc_code_catalog'];
            if (! SimplifiedSplitIntegration162::valid($codes)) {
                throw new RuntimeException('Qualified A162 policy required.');
            }
            $raw = file_get_contents((string) $this->argument('input'));
            if ($raw === false) {
                throw new RuntimeException('Readable JSON required.');
            }
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input) || array_is_list($input)) {
                throw new RuntimeException('Assessment object required.');
            }
            if ($this->option('mode') === 'calculation') {
                $service = new SimplifiedSplitCalculation162($codes[SimplifiedSplitIntegration162::PARAMETERS], $codes[SimplifiedSplitIntegration162::MANDATES]);
                $report = $service->assess($input);
            } elseif ($this->option('mode') === 'period') {
                $service = new SimplifiedSplitPeriod162;
                $report = $service->reconcile($input);
            } else {
                throw new RuntimeException('Unsupported assessment mode.');
            }
            $replay = $service->replay($report);
            $this->outputJson(['bundle_id' => $knowledge['rule_bundle_id'], 'report' => $report, 'replay_matches' => $replay]);

            return $replay && in_array($report['status'], ['LOCAL_SIMPLIFIED_SPLIT_CALCULATED', 'LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED'], true)
                ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'posted' => false]);

            return self::FAILURE;
        }
    }
}
