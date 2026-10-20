<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Classification;

enum MappingStatus: string
{
    case CONFIRMED = 'CONFIRMED';
    case AMBIGUOUS = 'AMBIGUOUS';
    case UNSUPPORTED = 'UNSUPPORTED';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
}
