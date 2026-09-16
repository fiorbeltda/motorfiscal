<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Files;

use App\Modules\Fiscal\Core\Rules\RawRule;
use App\Modules\Fiscal\Core\Rules\RuleCompilationException;
use App\Modules\Fiscal\Core\Rules\RuleCompiler;
use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class RulesValidatorService
{
    public function __construct(
        private JsonSchemaValidator $schemas,
        private FactDefinitionFileLoader $factLoader,
    ) {}

    public function validate(string $path, string $schemaPath, string $factDefinitionsPath): RulesValidationReport
    {
        $files = $this->jsonFiles($path);
        $compiler = new RuleCompiler($this->factLoader->load($factDefinitionsPath));
        $results = [];
        $allValid = true;

        foreach ($files as $file) {
            $schema = $this->schemas->validateFile($file, $schemaPath);
            $entry = ['path' => $file, 'schema_valid' => $schema->valid, 'schema_errors' => $schema->errors];

            if (! $schema->valid) {
                $entry['semantic_valid'] = false;
                $entry['semantic_errors'] = ['Schema validation failed.'];
                $allValid = false;
                $results[] = $entry;

                continue;
            }

            try {
                $payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new RuntimeException('Rule document must be a JSON object.');
                }
                $compiled = $compiler->compile(new RawRule($payload));
                $entry['semantic_valid'] = true;
                $entry['semantic_errors'] = [];
                $entry['rule_id'] = $compiled->id;
                $entry['version'] = $compiled->version;
            } catch (JsonException|RuleCompilationException|RuntimeException $exception) {
                $entry['semantic_valid'] = false;
                $entry['semantic_errors'] = $exception instanceof RuleCompilationException ? $exception->errors : [$exception->getMessage()];
                $allValid = false;
            }

            $results[] = $entry;
        }

        return new RulesValidationReport($allValid && $files !== [], $results);
    }

    /** @return list<string> */
    private function jsonFiles(string $path): array
    {
        if (is_file($path)) {
            return str_ends_with(strtolower($path), '.json') ? [$path] : [];
        }

        if (! is_dir($path)) {
            throw new RuntimeException("Rule path not found: {$path}");
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'json') {
                $files[] = $file->getPathname();
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }
}
