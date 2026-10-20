<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

enum FederalWithholdingStatus: string
{
    case WITHHOLD = 'WITHHOLD';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
    case EXEMPT = 'EXEMPT';
    case IMMUNE = 'IMMUNE';
    case ZERO_RATE = 'ZERO_RATE';
    case SUSPENDED = 'SUSPENDED';
    case DISPENSED_THRESHOLD = 'DISPENSED_THRESHOLD';
    case PENDING_TRIGGER = 'PENDING_TRIGGER';
    case ACTION_REQUIRED = 'ACTION_REQUIRED';
}
