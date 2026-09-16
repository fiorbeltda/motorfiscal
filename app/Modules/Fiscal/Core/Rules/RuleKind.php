<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

enum RuleKind: string
{
    case CLASSIFICATION = 'CLASSIFICATION';
    case INCIDENCE = 'INCIDENCE';
    case LOCATION = 'LOCATION';
    case RESPONSIBILITY = 'RESPONSIBILITY';
    case TREATMENT = 'TREATMENT';
    case CALCULATION = 'CALCULATION';
    case PROJECTION = 'PROJECTION';
    case INVARIANT = 'INVARIANT';
}
