<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Evidence;

final readonly class EvidenceReference
{
    public function __construct(
        public string $sourceId,
        public string $locator,
        public ?string $contentHash = null,
    ) {}

    /** @return array{source_id: string, locator: string, content_hash: ?string} */
    public function toArray(): array
    {
        return [
            'source_id' => $this->sourceId,
            'locator' => $this->locator,
            'content_hash' => $this->contentHash,
        ];
    }
}
