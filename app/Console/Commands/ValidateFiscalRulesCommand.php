<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\RulesValidatorService;
use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;

final class ValidateFiscalRulesCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rules:validate
        {path? : Rule JSON file or directory}
        {--schema= : Rule JSON Schema path}
        {--facts= : Fact definitions path}';

    protected $description = 'Validate fiscal rule JSON files structurally and semantically';

    public function handle(): int
    {
        $path = (string) ($this->argument('path') ?: resource_path('fiscal/rules'));
        $schema = (string) ($this->option('schema') ?: resource_path('fiscal/schemas/rule.schema.json'));
        $facts = (string) ($this->option('facts') ?: resource_path('fiscal/facts/core.json'));

        $report = (new RulesValidatorService(
            new JsonSchemaValidator,
            new FactDefinitionFileLoader,
        ))->validate($path, $schema, $facts);

        $this->outputJson($report->toArray());

        return $report->valid ? self::SUCCESS : self::FAILURE;
    }
}
