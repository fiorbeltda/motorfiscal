<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

interface CatalogMembership
{
    public function contains(string $catalog, string $value): bool;
}
