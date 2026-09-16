<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

use InvalidArgumentException;

final class FactBag
{
    /** @var array<string, FactValue> */
    private array $facts = [];

    public function __construct(private readonly FactDefinitionRegistry $definitions) {}

    public function put(FactValue $fact): void
    {
        $definition = $this->definitions->get($fact->key);

        if (! $definition->accepts($fact->value)) {
            throw new InvalidArgumentException("Invalid value for fact {$fact->key} ({$definition->type->value}).");
        }

        $this->facts[$fact->key] = $fact;
    }

    public function has(string $key): bool
    {
        return isset($this->facts[$key]);
    }

    public function get(string $key): ?FactValue
    {
        return $this->facts[$key] ?? null;
    }

    public function value(string $key): mixed
    {
        return $this->get($key)?->value;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function missing(array $keys): array
    {
        return array_values(array_filter($keys, fn (string $key): bool => ! $this->has($key)));
    }

    /** @return array<string, mixed> */
    public function canonicalValues(): array
    {
        $values = [];

        foreach ($this->facts as $key => $fact) {
            $values[$key] = $fact->value;
        }

        ksort($values, SORT_STRING);

        return $values;
    }

    /** @return list<FactValue> */
    public function all(): array
    {
        return array_values($this->facts);
    }
}
