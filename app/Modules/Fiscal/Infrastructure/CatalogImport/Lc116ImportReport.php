<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class Lc116ImportReport
{
    /**
     * @param  list<Lc116ServiceEntry>  $entries
     * @param  list<ImportIssue>  $issues
     */
    public function __construct(public array $entries, public array $issues) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $items = array_values(array_unique(array_map(
            static fn (Lc116ServiceEntry $entry): string => $entry->itemCode,
            $this->entries,
        )));
        $vetoed = array_values(array_map(
            static fn (Lc116ServiceEntry $entry): string => $entry->code,
            array_filter($this->entries, static fn (Lc116ServiceEntry $entry): bool => $entry->status === 'VETOED'),
        ));

        return [
            'items' => count($items),
            'entries' => count($this->entries),
            'active_entries' => count($this->entries) - count($vetoed),
            'vetoed_entries' => count($vetoed),
            'vetoed_codes' => $vetoed,
            'issues' => array_map(static fn (ImportIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
