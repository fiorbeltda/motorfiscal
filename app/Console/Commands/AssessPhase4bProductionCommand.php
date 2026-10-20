<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\Production\Phase4bReadinessAssessor;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Throwable;

final class AssessPhase4bProductionCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:production-assess
        {--bundle-manifest=bundles/consumption-phase4b-2026.10.02-14.json}
        {--controls=resources/fiscal/production/phase4b-controls-2026.10.02-14.json}
        {--target=production : production or local-validation}
        {--approval= : Signed approval record bound to the bundle and controls hashes}
        {--trusted-reviewer-key= : Public verification key provisioned outside the bundle}';

    protected $description = 'Read-only production readiness assessment for the integrated Phase 4B bundle';

    public function handle(): int
    {
        try {
            $target = (string) $this->option('target');
            if (! in_array($target, ['production', 'local-validation'], true)) {
                throw new \InvalidArgumentException('Target must be production or local-validation.');
            }
            $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
                resource_path('fiscal'),
                (string) $this->option('bundle-manifest'),
                (string) $this->option('controls'),
                $this->option('approval') === null ? null : (string) $this->option('approval'),
                $this->option('trusted-reviewer-key') === null ? null : (string) $this->option('trusted-reviewer-key'),
            );
            $this->outputJson($report);

            return ($target === 'production' ? $report['ready'] : $report['eligible_for_local_validation'])
                ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['ready' => false, 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
