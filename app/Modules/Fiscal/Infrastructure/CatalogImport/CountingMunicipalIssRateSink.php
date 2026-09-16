<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final class CountingMunicipalIssRateSink implements MunicipalIssRateSink
{
    private int $count = 0;

    public function accept(MunicipalIssRateRow $row): void
    {
        $this->count++;
    }

    public function finish(): ImportSinkSummary
    {
        return new ImportSinkSummary($this->count, 0);
    }
}
