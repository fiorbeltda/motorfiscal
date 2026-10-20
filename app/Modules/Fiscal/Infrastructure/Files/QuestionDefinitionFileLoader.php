<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Files;

use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Questions\QuestionDefinition;
use JsonException;
use RuntimeException;

final class QuestionDefinitionFileLoader
{
    /** @return list<QuestionDefinition> */
    public function load(string $path, ?FactDefinitionRegistry $facts = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Question definition file not found: {$path}");
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid question definition JSON: {$exception->getMessage()}", previous: $exception);
        }
        if (! is_array($data)) {
            throw new RuntimeException('Question definition document must be a JSON list.');
        }

        return $this->fromArray($data, $facts);
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return list<QuestionDefinition>
     */
    public function fromArray(array $data, ?FactDefinitionRegistry $facts = null): array
    {
        if (! array_is_list($data)) {
            throw new RuntimeException('Question definition document must be a JSON list.');
        }
        $questions = [];
        $seen = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Question definition row must be an object.');
            }
            $factKey = (string) ($row['fact_key'] ?? '');
            if ($factKey === '' || isset($seen[$factKey])) {
                throw new RuntimeException("Duplicate or empty question fact key: {$factKey}");
            }
            if ($facts !== null && ! $facts->has($factKey)) {
                throw new RuntimeException("Question references unknown fact: {$factKey}");
            }
            $seen[$factKey] = true;
            $questions[] = new QuestionDefinition(
                $factKey,
                (string) ($row['prompt'] ?? ''),
                (int) ($row['rank'] ?? 0),
                (string) ($row['answer_type'] ?? ''),
                isset($row['options']) && is_array($row['options']) ? array_values(array_map('strval', $row['options'])) : [],
            );
        }

        return $questions;
    }
}
