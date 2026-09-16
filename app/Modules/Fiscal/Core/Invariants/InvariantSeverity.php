<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Invariants;

enum InvariantSeverity: string
{
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ACTION_REQUIRED = 'ACTION_REQUIRED';
    case BLOCKING = 'BLOCKING';
}
