<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class ImportSinkSummary
{
    public function __construct(
        public int $inserted,
        public int $duplicates,
    ) {}
}
