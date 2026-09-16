<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Temporal;

enum TemporalResolutionStatus: string
{
    case RESOLVED = 'RESOLVED';
    case NOT_FOUND = 'NOT_FOUND';
    case CONFLICT = 'CONFLICT';
}
