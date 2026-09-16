<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class IssLocationRuleRow
{
    public function __construct(
        public string $ctribnacCode,
        public string $description,
        public ?string $strategy,
        public ?string $importOverride,
        public ?string $requiredDetailGroup,
    ) {}
}
