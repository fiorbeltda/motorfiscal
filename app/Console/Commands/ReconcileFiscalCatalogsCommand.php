<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\CatalogImport\CatalogCoverageAnalyzer;
use App\Modules\Fiscal\Infrastructure\CatalogImport\CtribNacCatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\IssLocationCatalogImporter;
use App\Modules\Fiscal\Infrastructure\CatalogImport\Lc116CatalogImporter;
use Throwable;

final class ReconcileFiscalCatalogsCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:catalog:reconcile
        {lc116 : LC 116 official HTML snapshot}
        {ctribnac : Official Anexo B XLSX}
        {territorial : Derived ISS territorial Markdown catalog}';

    protected $description = 'Reconcile LC 116, cTribNac and territorial ISS catalog coverage';

    public function handle(): int
    {
        try {
            $lc116 = (new Lc116CatalogImporter)->import((string) $this->argument('lc116'));
            $ctribNac = (new CtribNacCatalogImporter)->import((string) $this->argument('ctribnac'));
            $territorial = (new IssLocationCatalogImporter)->import((string) $this->argument('territorial'));
            $report = (new CatalogCoverageAnalyzer)->analyze($lc116->entries, $ctribNac->entries, $territorial->rows);
            $status = ! $report['valid']
                ? 'INVALID'
                : ($report['territorial_complete'] ? 'VALID' : 'VALID_WITH_GAPS');

            $this->outputJson([
                'status' => $status,
                'report' => $report,
            ]);

            return $report['valid'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
