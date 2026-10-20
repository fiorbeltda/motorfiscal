<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use InvalidArgumentException;

final readonly class ServiceCode
{
    public function __construct(public string $ctribnac)
    {
        if (! preg_match('/^\d{6}$/', $ctribnac)) {
            throw new InvalidArgumentException('cTribNac must contain six digits.');
        }
    }
}
