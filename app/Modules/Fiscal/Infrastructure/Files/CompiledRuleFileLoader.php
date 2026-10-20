<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Files;

use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Rules\CompiledRule;
use App\Modules\Fiscal\Core\Rules\RawRule;
use App\Modules\Fiscal\Core\Rules\RuleCompiler;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class CompiledRuleFileLoader
{
    /** @return list<CompiledRule> */
    public function load(string $path, FactDefinitionRegistry $facts): array
    {
        if (is_file($path)) {
            $paths = str_ends_with(strtolower($path), '.json') ? [$path] : [];
        } elseif (is_dir($path)) {
            $paths = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'json') {
                    $paths[] = $file->getPathname();
                }
            }
        } else {
            throw new RuntimeException("Rule path not found: {$path}");
        }
        sort($paths, SORT_STRING);
        if ($paths === []) {
            throw new RuntimeException("Rule path contains no JSON documents: {$path}");
        }
        $payloads = [];
        foreach ($paths as $rulePath) {
            try {
                $payload = json_decode((string) file_get_contents($rulePath), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("Invalid rule JSON {$rulePath}: {$exception->getMessage()}", previous: $exception);
            }
            if (! is_array($payload) || array_is_list($payload)) {
                throw new RuntimeException("Rule must be a JSON object: {$rulePath}");
            }
            $payloads[] = $payload;
        }

        return $this->fromPayloads($payloads, $facts);
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return list<CompiledRule>
     */
    public function fromPayloads(array $payloads, FactDefinitionRegistry $facts): array
    {
        $rules = [];
        $versions = [];
        $selectedRuleIds = [];
        $compiler = new RuleCompiler($facts);
        foreach ($payloads as $payload) {
            $rule = $compiler->compile(new RawRule($payload));
            $key = $rule->id.'@'.$rule->version;
            if (isset($versions[$key])) {
                throw new RuntimeException("Duplicate rule version: {$key}");
            }
            if (isset($selectedRuleIds[$rule->id])) {
                throw new RuntimeException("A bundle can select only one version of rule: {$rule->id}");
            }
            $versions[$key] = true;
            $selectedRuleIds[$rule->id] = true;
            $rules[] = $rule;
        }

        $ruleIds = array_fill_keys(array_map(static fn (CompiledRule $rule): string => $rule->id, $rules), true);
        foreach ($rules as $rule) {
            foreach ($rule->relations as $relation) {
                if (! isset($ruleIds[$relation->subordinateRuleId])) {
                    throw new RuntimeException("Rule precedence target not found: {$relation->subordinateRuleId}");
                }
            }
        }

        return $rules;
    }
}
