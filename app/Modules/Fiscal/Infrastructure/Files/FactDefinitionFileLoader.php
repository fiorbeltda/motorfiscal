<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Files;

use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use JsonException;
use RuntimeException;

final class FactDefinitionFileLoader
{
    public function load(string $path): FactDefinitionRegistry
    {
        if (! is_file($path)) {
            throw new RuntimeException("Fact definition file not found: {$path}");
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid fact definition JSON: {$exception->getMessage()}", previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Fact definition document must be a JSON list.');
        }

        return $this->fromArray($decoded);
    }

    /** @param array<int|string, mixed> $decoded */
    public function fromArray(array $decoded): FactDefinitionRegistry
    {
        if (! array_is_list($decoded)) {
            throw new RuntimeException('Fact definition document must be a JSON list.');
        }

        $registry = new FactDefinitionRegistry;

        foreach ($decoded as $index => $item) {
            if (! is_array($item) || ! isset($item['key'], $item['type'], $item['material'])) {
                throw new RuntimeException("Invalid fact definition at index {$index}.");
            }

            $allowed = isset($item['allowed_values']) && is_array($item['allowed_values'])
                ? array_values(array_map('strval', $item['allowed_values']))
                : [];

            $registry->register(new FactDefinition(
                key: (string) $item['key'],
                type: FactValueType::from((string) $item['type']),
                material: (bool) $item['material'],
                allowedValues: $allowed,
                pattern: isset($item['pattern']) ? (string) $item['pattern'] : null,
                minLength: isset($item['min_length']) ? (int) $item['min_length'] : null,
            ));
        }

        return $registry;
    }
}
