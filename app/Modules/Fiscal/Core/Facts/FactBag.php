<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

final class FactBag
{
    /** @var array<string, FactValue> */
    private array $facts = [];

    private bool $sealed = false;

    public function __construct(private readonly FactDefinitionRegistry $definitions) {}

    public function put(FactValue $fact): void
    {
        if ($this->sealed) {
            throw new InvalidArgumentException('Fact bag is immutable after being attached to a decision context.');
        }
        $definition = $this->definitions->get($fact->key);

        if (! $definition->accepts($fact->value)) {
            throw new InvalidArgumentException("Invalid value for fact {$fact->key} ({$definition->type->value}).");
        }
        if (isset($this->facts[$fact->key])) {
            throw new InvalidArgumentException("Conflicting duplicate fact: {$fact->key}");
        }

        $this->facts[$fact->key] = $fact;
    }

    public function has(string $key): bool
    {
        return isset($this->facts[$key]);
    }

    public function hasDefinition(string $key): bool
    {
        return $this->definitions->has($key);
    }

    public function get(string $key): ?FactValue
    {
        return $this->facts[$key] ?? null;
    }

    public function value(string $key): mixed
    {
        return $this->get($key)?->value;
    }

    public function definition(string $key): FactDefinition
    {
        return $this->definitions->get($key);
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
        $facts = $this->facts;
        ksort($facts, SORT_STRING);

        return array_values($facts);
    }

    /** @return list<FactDefinition> */
    public function definitions(): array
    {
        return $this->definitions->all();
    }

    /** @param list<FactValue> $additionalFacts */
    public function withAdditional(array $additionalFacts): self
    {
        $copy = new self($this->definitions);
        foreach ($this->all() as $fact) {
            $copy->put($fact);
        }
        foreach ($additionalFacts as $fact) {
            $copy->put($fact);
        }

        return $copy;
    }

    public function withReplaced(FactValue $replacement): self
    {
        if (! $this->has($replacement->key)) {
            throw new InvalidArgumentException("Cannot replace absent fact: {$replacement->key}");
        }
        $copy = new self($this->definitions);
        foreach ($this->all() as $fact) {
            $copy->put($fact->key === $replacement->key ? $replacement : $fact);
        }

        return $copy;
    }

    public function effectiveAt(LocalDate $date, DateTimeImmutable $knownAt): self
    {
        $selected = new self($this->definitions);
        foreach ($this->facts as $fact) {
            if ($fact->wasAvailableAt($date, $knownAt)) {
                $selected->put($fact);
            }
        }

        return $selected;
    }

    public function seal(): void
    {
        $this->sealed = true;
    }
}
