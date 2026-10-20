<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

enum IssResponsibility: string
{
    case PROVIDER = 'PROVIDER';
    case CUSTOMER = 'CUSTOMER';
    case INTERMEDIARY = 'INTERMEDIARY';
}
