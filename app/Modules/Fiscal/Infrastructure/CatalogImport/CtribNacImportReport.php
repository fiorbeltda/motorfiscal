<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class CtribNacImportReport
{
    /**
     * @param  list<NationalServiceCodeEntry>  $entries
     * @param  list<ImportIssue>  $issues
     */
    public function __construct(public array $entries, public array $issues) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $linkedCodes = array_values(array_filter(
            $this->entries,
            static fn (NationalServiceCodeEntry $entry): bool => $entry->lc116Code !== null,
        ));
        $lcCodes = array_values(array_unique(array_map(
            static fn (NationalServiceCodeEntry $entry): string => (string) $entry->lc116Code,
            $linkedCodes,
        )));
        $frequencies = array_count_values(array_map(
            static fn (NationalServiceCodeEntry $entry): string => (string) $entry->lc116Code,
            $linkedCodes,
        ));

        return [
            'entries' => count($this->entries),
            'lc116_linked_entries' => count($linkedCodes),
            'special_entries' => count($this->entries) - count($linkedCodes),
            'referenced_lc116_codes' => count($lcCodes),
            'single_code_lc116_items' => count(array_filter($frequencies, static fn (int $count): bool => $count === 1)),
            'multiple_code_lc116_items' => count(array_filter($frequencies, static fn (int $count): bool => $count > 1)),
            'issues' => array_map(static fn (ImportIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
