<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class NationalServiceCodeEntry implements FiscalCatalogEntry
{
    public function __construct(
        public string $code,
        public string $formattedCode,
        public string $itemCode,
        public string $subitemCode,
        public string $splitCode,
        public ?string $lc116Code,
        public string $officialDescription,
        public bool $specialNoIss,
        public string $status,
        public string $sourceSheet,
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
            'formatted_code' => $this->formattedCode,
            'item_code' => $this->itemCode,
            'subitem_code' => $this->subitemCode,
            'split_code' => $this->splitCode,
            'lc116_code' => $this->lc116Code,
            'official_description' => $this->officialDescription,
            'special_no_iss' => $this->specialNoIss,
            'status' => $this->status,
            'source_sheet' => $this->sourceSheet,
        ];
    }
}
