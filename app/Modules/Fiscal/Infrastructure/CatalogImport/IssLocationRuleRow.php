<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class IssLocationRuleRow implements FiscalCatalogEntry
{
    public function __construct(
        public string $ctribnacCode,
        public string $description,
        public ?string $strategy,
        public ?string $importOverride,
        public ?string $requiredDetailGroup,
    ) {}

    public function entryKey(): string
    {
        return $this->ctribnacCode;
    }

    public function toCatalogPayload(): array
    {
        return [
            'entry_key' => $this->entryKey(),
            'ctribnac_code' => $this->ctribnacCode,
            'description' => $this->description,
            'strategy' => $this->strategy,
            'import_override' => $this->importOverride,
            'required_detail_group' => $this->requiredDetailGroup,
        ];
    }
}
