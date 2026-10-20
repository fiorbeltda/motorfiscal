<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

enum MunicipalParameterStatus: string
{
    case FOUND = 'FOUND';
    case NOT_FOUND = 'NOT_FOUND';
    case CONFLICT = 'CONFLICT';
}
