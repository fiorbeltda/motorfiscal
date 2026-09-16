<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

enum FactSourceType: string
{
    case USER = 'USER';
    case DERIVED = 'DERIVED';
    case CATALOG = 'CATALOG';
    case OFFICIAL_FILE = 'OFFICIAL_FILE';
    case OFFICIAL_API = 'OFFICIAL_API';
    case DOCUMENT = 'DOCUMENT';
    case FIXTURE = 'FIXTURE';
}
