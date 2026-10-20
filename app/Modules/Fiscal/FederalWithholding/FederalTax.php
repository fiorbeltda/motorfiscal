<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

enum FederalTax: string
{
    case IRRF = 'IRRF';
    case PIS = 'PIS';
    case COFINS = 'COFINS';
    case CSLL = 'CSLL';
    case INSS = 'INSS';
}
