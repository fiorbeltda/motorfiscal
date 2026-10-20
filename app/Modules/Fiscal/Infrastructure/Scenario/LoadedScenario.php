<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

use App\Modules\Fiscal\Core\Engine\FiscalDecisionInput;

final readonly class LoadedScenario
{
    /** @param array<string, mixed> $source */
    public function __construct(
        public FiscalDecisionInput $input,
        public array $source,
    ) {}
}
