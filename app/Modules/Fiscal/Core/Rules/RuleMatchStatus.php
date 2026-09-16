<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

enum RuleMatchStatus: string
{
    case MATCHED = 'MATCHED';
    case NOT_MATCHED = 'NOT_MATCHED';
    case NEEDS_FACTS = 'NEEDS_FACTS';
    case ERROR = 'ERROR';
}
