<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class Lc116ServiceEntry implements FiscalCatalogEntry
{
    /** @param list<array{text: string, href: string|null}> $sourceAnnotations */
    public function __construct(
        public string $code,
        public string $itemCode,
        public string $subitemCode,
        public string $itemLegalTitle,
        public string $legalDescription,
        public string $status,
        public array $sourceAnnotations = [],
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
            'item_code' => $this->itemCode,
            'subitem_code' => $this->subitemCode,
            'item_legal_title' => $this->itemLegalTitle,
            'legal_description' => $this->legalDescription,
            'status' => $this->status,
            'source_annotations' => $this->sourceAnnotations,
        ];
    }
}
