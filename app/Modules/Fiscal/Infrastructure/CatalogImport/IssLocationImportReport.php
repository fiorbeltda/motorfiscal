<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class IssLocationImportReport
{
    /**
     * @param  list<IssLocationRuleRow>  $rows
     * @param  list<ImportIssue>  $issues
     */
    public function __construct(
        public array $rows,
        public array $issues,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $strategies = [];
        foreach ($this->rows as $row) {
            $key = $row->strategy ?? 'NONE';
            $strategies[$key] = ($strategies[$key] ?? 0) + 1;
        }
        ksort($strategies, SORT_STRING);

        return [
            'rows' => count($this->rows),
            'strategies' => $strategies,
            'issues' => array_map(fn (ImportIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
