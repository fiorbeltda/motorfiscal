<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class MunicipalIssRateImportReport
{
    /** @param list<ImportIssue> $issues */
    public function __construct(
        public int $files,
        public int $readRows,
        public int $acceptedRows,
        public int $rejectedRows,
        public int $blankRates,
        public int $municipalities,
        public int $serviceCodes,
        public string $logicalHash,
        public ImportSinkSummary $sink,
        public array $issues,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'files' => $this->files,
            'read_rows' => $this->readRows,
            'accepted_rows' => $this->acceptedRows,
            'rejected_rows' => $this->rejectedRows,
            'blank_rates' => $this->blankRates,
            'municipalities' => $this->municipalities,
            'service_codes' => $this->serviceCodes,
            'logical_hash' => $this->logicalHash,
            'inserted' => $this->sink->inserted,
            'duplicates' => $this->sink->duplicates,
            'issues' => array_map(fn (ImportIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
