<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Shared\LocalDate;

interface MunicipalParameterProvider
{
    public function resolve(
        LocationIdentifier $location,
        ServiceCode $service,
        LocalDate $competence,
        FiscalDecisionContext $context,
    ): MunicipalFiscalParametersResult;
}
