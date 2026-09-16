<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

enum ComparisonOperator: string
{
    case EQ = 'EQ';
    case NEQ = 'NEQ';
    case IN = 'IN';
    case NOT_IN = 'NOT_IN';
    case EXISTS = 'EXISTS';
    case NOT_EXISTS = 'NOT_EXISTS';
    case GT = 'GT';
    case GTE = 'GTE';
    case LT = 'LT';
    case LTE = 'LTE';
    case BETWEEN = 'BETWEEN';
    case DATE_BEFORE = 'DATE_BEFORE';
    case DATE_ON_OR_BEFORE = 'DATE_ON_OR_BEFORE';
    case DATE_AFTER = 'DATE_AFTER';
    case DATE_ON_OR_AFTER = 'DATE_ON_OR_AFTER';
    case CODE_PREFIX = 'CODE_PREFIX';
    case IN_CATALOG = 'IN_CATALOG';
    case NOT_IN_CATALOG = 'NOT_IN_CATALOG';
}
