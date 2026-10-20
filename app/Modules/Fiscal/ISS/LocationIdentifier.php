<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use InvalidArgumentException;

final readonly class LocationIdentifier
{
    public function __construct(public string $ibgeCode)
    {
        if (! preg_match('/^\d{7}$/', $ibgeCode)) {
            throw new InvalidArgumentException('IBGE municipality code must contain seven digits.');
        }
    }
}
