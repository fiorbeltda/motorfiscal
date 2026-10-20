<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use RuntimeException;
use Throwable;

final class RunConsumptionReformCorpusCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:corpus {path=resources/fiscal/scenarios/consumption-phase4b} {--expected=resources/fiscal/expected/consumption-phase4b} {--bundle-manifest=bundles/consumption-phase4b-2026.09.25-1.json}';

    protected $description = 'Check the IBS/CBS golden scenario corpus';

    public function handle(): int
    {
        try {
            $paths = glob(rtrim((string) $this->argument('path'), '/\\').'/*.json') ?: [];
            sort($paths, SORT_STRING);
            if ($paths === []) {
                throw new RuntimeException('RTC scenario corpus is empty.');
            }
            $expectedRoot = rtrim((string) $this->option('expected'), '/\\');
            $runner = new RtcScenarioRunService(new FiscalEngineFactory);
            $reports = [];
            foreach ($paths as $path) {
                $expectedPath = $expectedRoot.DIRECTORY_SEPARATOR.basename($path);
                $expected = json_decode((string) file_get_contents($expectedPath), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($expected)) {
                    throw new RuntimeException("RTC expected result must be an object: {$expectedPath}");
                }
                $result = $runner->execute(resource_path('fiscal'), (string) $this->option('bundle-manifest'), $path)->result;
                $actual = $result->toArray();
                $matches = ($expected['status'] ?? null) === $actual['status']
                    && ($expected['decision_hash'] ?? null) === $actual['decision_hash']
                    && ($expected['reason_codes'] ?? null) === $actual['reason_codes']
                    && ($expected['missing_facts'] ?? null) === $actual['missing_facts'];
                $reports[] = ['scenario' => basename($path), 'passed' => $matches,
                    'expected_hash' => $expected['decision_hash'] ?? null, 'actual_hash' => $actual['decision_hash']];
            }
            $passed = count(array_filter($reports, static fn (array $row): bool => $row['passed']));
            $report = ['valid' => $passed === count($reports), 'total' => count($reports), 'passed' => $passed,
                'failed' => count($reports) - $passed, 'scenarios' => $reports];
            $this->outputJson($report);

            return $report['valid'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
