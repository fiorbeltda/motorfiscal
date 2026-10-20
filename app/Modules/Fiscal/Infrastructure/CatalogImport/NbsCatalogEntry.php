<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class NbsCatalogEntry implements FiscalCatalogEntry
{
    public function __construct(
        public string $code,
        public string $description,
    ) {}

    public function entryKey(): string
    {
        return $this->code;
    }

    public function toCatalogPayload(): array
    {
        return [
            'entry_key' => $this->entryKey(),
            'code' => $this->code,
            'description' => $this->description,
        ];
    }
}
