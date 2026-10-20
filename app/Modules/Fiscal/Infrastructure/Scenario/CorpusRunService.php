<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class CorpusRunService
{
    public function __construct(
        private ScenarioRunService $runner,
        private JsonSchemaValidator $schemas,
        private string $expectedSchemaPath,
    ) {}

    /** @return array{valid: bool, total: int, passed: int, failed: int, scenarios: list<array<string, mixed>>} */
    public function run(string $scenarioRoot, string $expectedRoot): array
    {
        $files = $this->jsonFiles($scenarioRoot);
        if ($files === []) {
            throw new RuntimeException('Fiscal corpus cannot be empty.');
        }
        $scenarioRelatives = array_map(
            static fn (string $path): string => str_replace('\\', '/', substr($path, strlen(rtrim($scenarioRoot, '\\/')) + 1)),
            $files,
        );
        $expectedRelatives = array_map(
            static fn (string $path): string => str_replace('\\', '/', substr($path, strlen(rtrim($expectedRoot, '\\/')) + 1)),
            $this->jsonFiles($expectedRoot),
        );
        sort($scenarioRelatives, SORT_STRING);
        sort($expectedRelatives, SORT_STRING);
        if ($scenarioRelatives !== $expectedRelatives) {
            throw new RuntimeException('Scenario and expected-result file sets differ.');
        }
        $reports = [];
        $passed = 0;

        foreach ($files as $scenarioPath) {
            $relative = str_replace('\\', '/', substr($scenarioPath, strlen(rtrim($scenarioRoot, '\\/')) + 1));
            $expectedPath = rtrim($expectedRoot, '\\/').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $schema = $this->schemas->validateFile($expectedPath, $this->expectedSchemaPath);
            if (! $schema->valid) {
                throw new RuntimeException("Expected result schema validation failed: {$relative}");
            }
            $result = $this->runner->run($scenarioPath)->toArray();
            $expected = $this->decode($expectedPath);
            $errors = $this->compare($expected, $result);
            $ok = $errors === [];
            if ($ok) {
                $passed++;
            }
            $reports[] = [
                'scenario' => $relative,
                'passed' => $ok,
                'errors' => $errors,
                'decision_hash' => $result['decision_hash'] ?? null,
            ];
        }

        return [
            'valid' => $passed === count($files),
            'total' => count($files),
            'passed' => $passed,
            'failed' => count($files) - $passed,
            'scenarios' => $reports,
        ];
    }

    /** @return list<string> */
    private function jsonFiles(string $root): array
    {
        if (! is_dir($root)) {
            throw new RuntimeException("Scenario directory not found: {$root}");
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'json') {
                $files[] = $file->getPathname();
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    /** @return array<string, mixed> */
    private function decode(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Expected result not found: {$path}");
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid expected result: {$exception->getMessage()}", previous: $exception);
        }
        if (! is_array($data)) {
            throw new RuntimeException('Expected result must be an object.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return list<string>
     */
    private function compare(array $expected, array $actual): array
    {
        $errors = [];
        if (($expected['status'] ?? null) !== ($actual['status'] ?? null)) {
            $errors[] = 'STATUS_MISMATCH';
        }
        foreach (($expected['reason_codes'] ?? []) as $reason) {
            if (! in_array($reason, is_array($actual['reason_codes'] ?? null) ? $actual['reason_codes'] : [], true)) {
                $errors[] = "REASON_CODE_MISSING:{$reason}";
            }
        }
        if (isset($expected['missing_facts']) && is_array($expected['missing_facts'])) {
            $actualMissing = is_array($actual['missing_facts'] ?? null) ? $actual['missing_facts'] : [];
            foreach ($expected['missing_facts'] as $fact) {
                if (! in_array($fact, $actualMissing, true)) {
                    $errors[] = "MISSING_FACT_NOT_REQUESTED:{$fact}";
                }
            }
        }
        if (isset($expected['decision']) && is_array($expected['decision'])) {
            $this->compareSubset($expected['decision'], is_array($actual['decision'] ?? null) ? $actual['decision'] : [], 'decision', $errors);
        }
        if (isset($expected['decision_hash']) && $expected['decision_hash'] !== ($actual['decision_hash'] ?? null)) {
            $errors[] = 'DECISION_HASH_MISMATCH';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @param  list<string>  $errors
     */
    private function compareSubset(array $expected, array $actual, string $path, array &$errors): void
    {
        foreach ($expected as $key => $value) {
            $childPath = "{$path}.{$key}";
            if (! array_key_exists($key, $actual)) {
                $errors[] = "EXPECTED_PATH_MISSING:{$childPath}";

                continue;
            }
            if (is_array($value)) {
                if (! is_array($actual[$key])) {
                    $errors[] = "VALUE_MISMATCH:{$childPath}";
                } else {
                    $this->compareSubset($value, $actual[$key], $childPath, $errors);
                }
            } elseif ($actual[$key] !== $value) {
                $errors[] = "VALUE_MISMATCH:{$childPath}";
            }
        }
    }
}
