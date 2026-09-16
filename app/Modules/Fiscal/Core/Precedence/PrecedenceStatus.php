<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Precedence;

enum PrecedenceStatus: string
{
    case RESOLVED = 'RESOLVED';
    case CONFLICT = 'CONFLICT';
    case EMPTY = 'EMPTY';
}
