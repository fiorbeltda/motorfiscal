<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

interface FiscalCatalogEntry
{
    public function entryKey(): string;

    /** @return array<string, mixed> */
    public function toCatalogPayload(): array;
}
