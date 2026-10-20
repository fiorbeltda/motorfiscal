<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\RulesValidatorService;
use App\Modules\Fiscal\Infrastructure\Persistence\RulePublisher;
use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PublishFiscalRulesCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rules:publish
        {bundle : Immutable bundle key}
        {version : Immutable bundle version}
        {path? : Rule JSON file or directory; defaults to the MVP rules only}
        {--schema= : Rule JSON Schema path}
        {--facts= : Fact definitions path}';

    protected $description = 'Validate and publish an immutable fiscal rule bundle';

    public function handle(): int
    {
        try {
            $path = (string) ($this->argument('path') ?: resource_path('fiscal/rules/mvp'));
            $schema = (string) ($this->option('schema') ?: resource_path('fiscal/schemas/rule.schema.json'));
            $facts = (string) ($this->option('facts') ?: resource_path('fiscal/facts/core.json'));
            $publisher = new RulePublisher(
                new RulesValidatorService(new JsonSchemaValidator, new FactDefinitionFileLoader),
                DB::connection(),
            );
            $report = $publisher->publish(
                $path,
                $schema,
                $facts,
                (string) $this->argument('bundle'),
                (string) $this->argument('version'),
            );
            $this->outputJson($report);

            return ($report['published'] ?? false) === true ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
