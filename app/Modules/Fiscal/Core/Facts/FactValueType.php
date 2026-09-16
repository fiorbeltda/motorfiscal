<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

enum FactValueType: string
{
    case BOOLEAN = 'BOOLEAN';
    case STRING = 'STRING';
    case CODE = 'CODE';
    case INTEGER = 'INTEGER';
    case DECIMAL = 'DECIMAL';
    case DATE = 'DATE';
    case ENUM = 'ENUM';
}
