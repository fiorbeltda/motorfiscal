<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Decision;

final readonly class DecisionNodeDefinition
{
    /** @param list<string> $dependsOn */
    public function __construct(
        public string $id,
        public array $dependsOn = [],
    ) {}
}
