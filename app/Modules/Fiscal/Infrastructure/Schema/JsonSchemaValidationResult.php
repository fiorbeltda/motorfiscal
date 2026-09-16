<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Schema;

final readonly class JsonSchemaValidationResult
{
    /** @param array<string, mixed> $errors */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}
}
