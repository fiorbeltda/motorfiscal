<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Precedence;

enum RuleRelationType: string
{
    case OVERRIDES = 'OVERRIDES';
    case EXCEPTION_OF = 'EXCEPTION_OF';
    case SUPERSEDES = 'SUPERSEDES';
}
