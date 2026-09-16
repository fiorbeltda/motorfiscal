<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final class InMemoryMunicipalIssRateSink implements MunicipalIssRateSink
{
    /** @var array<string, MunicipalIssRateRow> */
    private array $rows = [];

    private int $duplicates = 0;

    public function accept(MunicipalIssRateRow $row): void
    {
        $key = $row->validityKey();
        if (isset($this->rows[$key])) {
            $this->duplicates++;

            return;
        }

        $this->rows[$key] = $row;
    }

    public function finish(): ImportSinkSummary
    {
        return new ImportSinkSummary(count($this->rows), $this->duplicates);
    }

    /** @return list<MunicipalIssRateRow> */
    public function rows(): array
    {
        return array_values($this->rows);
    }
}
