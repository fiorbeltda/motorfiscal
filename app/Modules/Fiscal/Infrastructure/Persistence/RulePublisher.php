<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Persistence;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Files\CompiledRuleFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\RulesValidatorService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final readonly class RulePublisher
{
    public function __construct(
        private RulesValidatorService $validator,
        private ConnectionInterface $database,
    ) {}

    /** @return array<string, mixed> */
    public function publish(
        string $path,
        string $schemaPath,
        string $factDefinitionsPath,
        string $bundleKey,
        string $bundleVersion,
    ): array {
        $validation = $this->validator->validate($path, $schemaPath, $factDefinitionsPath);
        if (! $validation->valid) {
            return ['published' => false, 'validation' => $validation->toArray()];
        }
        $compiledRules = (new CompiledRuleFileLoader)->load(
            $path,
            (new FactDefinitionFileLoader)->load($factDefinitionsPath),
        );

        return $this->database->transaction(function () use ($validation, $bundleKey, $bundleVersion, $compiledRules): array {
            $ruleVersions = [];
            $ruleVersionIdsByRuleKey = [];
            $bundlePayloads = [];
            foreach ($validation->files as $file) {
                $filePath = (string) ($file['path'] ?? '');
                $payload = $this->decode($filePath);
                $ruleKey = (string) $payload['rule_id'];
                $version = (string) $payload['version'];
                $contentHash = CanonicalJson::hash($payload);
                $bundlePayloads[] = $payload;

                $rule = $this->database->table('fiscal_rules')->where('rule_key', $ruleKey)->first();
                $sourceVersionId = $this->database->table('fiscal_source_versions as versions')
                    ->join('fiscal_sources as sources', 'sources.id', '=', 'versions.source_id')
                    ->where('sources.source_key', (string) $payload['source_id'])
                    ->where('versions.version', (string) $payload['source_version'])
                    ->value('versions.id');
                if (! is_string($sourceVersionId)) {
                    throw new RuntimeException("Rule source version is not registered: {$payload['source_id']}@{$payload['source_version']}");
                }
                $ruleId = $rule?->id;
                if (! is_string($ruleId)) {
                    $ruleId = Str::uuid7()->toString();
                    $this->database->table('fiscal_rules')->insert([
                        'id' => $ruleId,
                        'rule_key' => $ruleKey,
                        'kind' => (string) $payload['kind'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $existing = $this->database->table('fiscal_rule_versions')
                    ->where('rule_id', $ruleId)
                    ->where('version', $version)
                    ->first();
                if ($existing !== null && $existing->content_hash !== $contentHash) {
                    throw new RuntimeException("Published rule version cannot be overwritten: {$ruleKey}@{$version}");
                }
                if ($existing !== null) {
                    $existingId = (string) $existing->id;
                    $ruleVersions[] = $existingId;
                    $ruleVersionIdsByRuleKey[$ruleKey] = $existingId;

                    continue;
                }

                $ruleVersionId = Str::uuid7()->toString();
                $this->database->table('fiscal_rule_versions')->insert([
                    'id' => $ruleVersionId,
                    'rule_id' => $ruleId,
                    'source_version_id' => $sourceVersionId,
                    'version' => $version,
                    'effective_from' => (string) $payload['effective_from'],
                    'effective_to' => $payload['effective_to'] ?? null,
                    'recorded_at' => now(),
                    'superseded_at' => null,
                    'status' => 'PUBLISHED',
                    'content_hash' => $contentHash,
                    'payload' => CanonicalJson::encode($payload),
                ]);
                $ruleVersions[] = $ruleVersionId;
                $ruleVersionIdsByRuleKey[$ruleKey] = $ruleVersionId;
            }

            foreach ($compiledRules as $rule) {
                foreach ($rule->relations as $relation) {
                    $sourceVersionId = $this->database->table('fiscal_source_versions as versions')
                        ->join('fiscal_sources as sources', 'sources.id', '=', 'versions.source_id')
                        ->where('sources.source_key', $relation->sourceId)
                        ->where('versions.version', $relation->sourceVersion)
                        ->value('versions.id');
                    if (! is_string($sourceVersionId)) {
                        throw new RuntimeException("Rule relation source version is not registered: {$relation->sourceId}@{$relation->sourceVersion}");
                    }
                    $dominantId = $ruleVersionIdsByRuleKey[$relation->dominantRuleId] ?? null;
                    $subordinateId = $ruleVersionIdsByRuleKey[$relation->subordinateRuleId] ?? null;
                    if (! is_string($dominantId) || ! is_string($subordinateId)) {
                        throw new RuntimeException('Rule relation references a version outside the bundle.');
                    }
                    $this->database->table('fiscal_rule_relations')->insertOrIgnore([
                        'dominant_rule_version_id' => $dominantId,
                        'subordinate_rule_version_id' => $subordinateId,
                        'relation_type' => $relation->type->value,
                        'source_version_id' => $sourceVersionId,
                    ]);
                }
            }

            usort($bundlePayloads, static fn (array $left, array $right): int => [
                (string) ($left['rule_id'] ?? ''),
                (string) ($left['version'] ?? ''),
            ] <=> [
                (string) ($right['rule_id'] ?? ''),
                (string) ($right['version'] ?? ''),
            ]);
            $bundleHash = CanonicalJson::hash($bundlePayloads);
            $bundle = $this->database->table('fiscal_rule_bundles')
                ->where('bundle_key', $bundleKey)
                ->where('version', $bundleVersion)
                ->first();
            if ($bundle !== null && $bundle->content_hash !== $bundleHash) {
                throw new RuntimeException("Published rule bundle cannot be overwritten: {$bundleKey}@{$bundleVersion}");
            }
            $bundleId = is_string($bundle?->id) ? $bundle->id : Str::uuid7()->toString();
            if ($bundle === null) {
                $this->database->table('fiscal_rule_bundles')->insert([
                    'id' => $bundleId,
                    'bundle_key' => $bundleKey,
                    'version' => $bundleVersion,
                    'content_hash' => $bundleHash,
                    'status' => 'PUBLISHED',
                    'published_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                foreach ($ruleVersions as $ruleVersionId) {
                    $this->database->table('fiscal_rule_bundle_items')->insert([
                        'bundle_id' => $bundleId,
                        'rule_version_id' => $ruleVersionId,
                    ]);
                }
            }

            return [
                'published' => true,
                'idempotent' => $bundle !== null,
                'bundle_key' => $bundleKey,
                'bundle_version' => $bundleVersion,
                'bundle_hash' => $bundleHash,
                'rule_count' => count($ruleVersions),
            ];
        });
    }

    /** @return array<string, mixed> */
    private function decode(string $path): array
    {
        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid rule JSON: {$exception->getMessage()}", previous: $exception);
        }
        if (! is_array($payload)) {
            throw new RuntimeException("Rule must be a JSON object: {$path}");
        }

        return $payload;
    }
}
