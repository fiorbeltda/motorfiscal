<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

use InvalidArgumentException;

final readonly class InMemoryCatalogMembership implements CatalogMembership
{
    /** @param array<string, list<string>> $catalogs */
    public function __construct(private array $catalogs = []) {}

    public function contains(string $catalog, string $value): bool
    {
        if (! array_key_exists($catalog, $this->catalogs)) {
            throw new InvalidArgumentException("Unknown rule catalog: {$catalog}");
        }

        return in_array($value, $this->catalogs[$catalog], true);
    }
}
