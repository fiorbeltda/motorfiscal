<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;

it('validates the canonical synthetic rule against JSON Schema', function (): void {
    $result = (new JsonSchemaValidator)->validateFile(
        resource_path('fiscal/rules/synthetic/provider-simples.json'),
        resource_path('fiscal/schemas/rule.schema.json'),
    );

    expect($result->valid)->toBeTrue()->and($result->errors)->toBe([]);
});
