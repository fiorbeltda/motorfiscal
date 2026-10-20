<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

final readonly class FactResolutionResult
{
    /**
     * @param  list<FactValue>  $facts
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     */
    public function __construct(
        public array $facts = [],
        public array $warnings = [],
        public array $errors = [],
    ) {}
}
