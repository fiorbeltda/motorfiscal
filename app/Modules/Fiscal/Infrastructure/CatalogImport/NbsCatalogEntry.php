<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class NbsCatalogEntry
{
    public function __construct(
        public string $code,
        public string $description,
    ) {}
}
