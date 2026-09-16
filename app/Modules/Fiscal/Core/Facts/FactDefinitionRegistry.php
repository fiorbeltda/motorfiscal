<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

use InvalidArgumentException;

final class FactDefinitionRegistry
{
    /** @var array<string, FactDefinition> */
    private array $definitions = [];

    /** @param iterable<FactDefinition> $definitions */
    public function __construct(iterable $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public function register(FactDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new InvalidArgumentException("Duplicate fact definition: {$definition->key}");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): FactDefinition
    {
        return $this->definitions[$key]
            ?? throw new InvalidArgumentException("Unknown fact definition: {$key}");
    }

    /** @return list<FactDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
