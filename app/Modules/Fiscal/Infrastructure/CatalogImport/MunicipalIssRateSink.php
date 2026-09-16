<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

interface MunicipalIssRateSink
{
    public function accept(MunicipalIssRateRow $row): void;

    public function finish(): ImportSinkSummary;
}
