<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Schema;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

final readonly class JsonSchemaValidator
{
    public function __construct(private Validator $validator = new Validator) {}

    public function validateFile(string $documentPath, string $schemaPath): JsonSchemaValidationResult
    {
        if (! is_file($documentPath) || ! is_file($schemaPath)) {
            throw new RuntimeException('Document or schema file does not exist.');
        }

        try {
            $document = json_decode((string) file_get_contents($documentPath), false, 512, JSON_THROW_ON_ERROR);
            $schema = json_decode((string) file_get_contents($schemaPath), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return new JsonSchemaValidationResult(false, ['$' => [$exception->getMessage()]]);
        }

        $result = $this->validator->validate($document, $schema);
        if ($result->isValid()) {
            return new JsonSchemaValidationResult(true);
        }

        $error = $result->error();

        return new JsonSchemaValidationResult(
            false,
            $error === null ? ['$' => ['Unknown schema validation error.']] : (new ErrorFormatter)->format($error),
        );
    }

    /**
     * @param  array<string|int, mixed>  $document
     * @param  array<string|int, mixed>  $schema
     */
    public function validateArray(array $document, array $schema): JsonSchemaValidationResult
    {
        try {
            $documentObject = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            $schemaObject = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return new JsonSchemaValidationResult(false, ['$' => [$exception->getMessage()]]);
        }

        $result = $this->validator->validate($documentObject, $schemaObject);
        if ($result->isValid()) {
            return new JsonSchemaValidationResult(true);
        }
        $error = $result->error();

        return new JsonSchemaValidationResult(
            false,
            $error === null ? ['$' => ['Unknown schema validation error.']] : (new ErrorFormatter)->format($error),
        );
    }
}
