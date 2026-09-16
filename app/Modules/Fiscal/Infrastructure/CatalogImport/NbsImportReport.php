<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class NbsImportReport
{
    /**
     * @param  list<NbsCatalogEntry>  $entries
     * @param  list<ImportIssue>  $issues
     */
    public function __construct(
        public array $entries,
        public array $issues,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'entries' => count($this->entries),
            'issues' => array_map(fn (ImportIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
