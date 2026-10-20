<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Engine;

enum DecisionStatus: string
{
    case VALID = 'VALID';
    case VALID_WITH_WARNINGS = 'VALID_WITH_WARNINGS';
    case ACTION_REQUIRED = 'ACTION_REQUIRED';
    case INVALID = 'INVALID';
}
