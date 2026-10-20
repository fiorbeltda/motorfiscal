<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Classification;

enum ServiceFamily: string
{
    case GENERAL = 'GENERAL';
    case SOFTWARE = 'SOFTWARE';
    case EVENT = 'EVENT';
    case CONSTRUCTION = 'CONSTRUCTION';
}
