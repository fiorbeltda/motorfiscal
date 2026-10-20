<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

final class DecisionDiffService
{
    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return list<array<string, mixed>>
     */
    public function diff(array $left, array $right): array
    {
        $changes = [];
        $this->walk($left, $right, '$', $changes);

        return $changes;
    }

    /** @param list<array<string, mixed>> $changes */
    private function walk(mixed $left, mixed $right, string $path, array &$changes): void
    {
        if (is_array($left) && is_array($right)) {
            $keys = array_values(array_unique([...array_keys($left), ...array_keys($right)]));
            sort($keys, SORT_STRING);
            foreach ($keys as $key) {
                $child = $path.'.'.$key;
                if (! array_key_exists($key, $left)) {
                    $changes[] = ['path' => $child, 'change' => 'ADDED', 'right' => $right[$key]];
                } elseif (! array_key_exists($key, $right)) {
                    $changes[] = ['path' => $child, 'change' => 'REMOVED', 'left' => $left[$key]];
                } else {
                    $this->walk($left[$key], $right[$key], $child, $changes);
                }
            }
        } elseif ($left !== $right) {
            $changes[] = ['path' => $path, 'change' => 'CHANGED', 'left' => $left, 'right' => $right];
        }
    }
}
