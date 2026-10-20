<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Files\CompiledRuleFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\Persistence\DatabaseRuleBundleRepository;
use DateTimeImmutable;
use RuntimeException;

final readonly class DatabaseRuleBundleKnowledgeLoader
{
    public function __construct(
        private DatabaseRuleBundleRepository $bundles,
        private string $key,
        private string $version,
    ) {}

    /**
     * Replace only the rule document and the manifest metadata that identifies it.
     * All local fact definitions, catalogs, questions, invariants and schemas remain bound
     * to their original manifest hashes.
     *
     * @param  array<string, mixed>  $knowledge
     * @return array<string, mixed>
     */
    public function loadInto(array $knowledge): array
    {
        $documents = $knowledge['documents'] ?? null;
        $hashes = $knowledge['content_hashes'] ?? null;
        if (! is_array($documents) || ! is_array($hashes)
            || ! isset($documents['bundle_manifest'], $documents['rules'], $documents['fact_definitions'], $documents['service_catalog'])
            || ! is_array($documents['bundle_manifest']) || ! is_array($documents['rules'])
            || ! is_array($documents['fact_definitions']) || ! is_array($documents['service_catalog'])) {
            throw new RuntimeException('Local knowledge cannot receive a database rule bundle.');
        }

        $manifest = $documents['bundle_manifest'];
        $declaredHashes = $manifest['content_hashes'] ?? null;
        if (! is_array($declaredHashes)) {
            throw new RuntimeException('Local bundle manifest content hashes are missing.');
        }
        $localRulesHash = CanonicalJson::hash($documents['rules']);
        if (! isset($hashes['rules'], $declaredHashes['rules'])
            || ! is_string($hashes['rules']) || ! is_string($declaredHashes['rules'])
            || ! hash_equals($localRulesHash, $hashes['rules'])
            || ! hash_equals($localRulesHash, $declaredHashes['rules'])) {
            throw new RuntimeException('Local rule document is not bound to its immutable manifest.');
        }

        $bundle = $this->bundles->load($this->key, $this->version);
        $sameIdentity = ($manifest['bundle_key'] ?? null) === $bundle->key
            && ($manifest['version'] ?? null) === $bundle->version;
        if ($sameIdentity && ! hash_equals($localRulesHash, $bundle->contentHash)) {
            throw new RuntimeException('Database rule bundle conflicts with the homonymous local immutable manifest.');
        }

        $facts = (new FactDefinitionFileLoader)->fromArray($documents['fact_definitions']);
        (new CompiledRuleFileLoader)->fromPayloads($bundle->rules, $facts);
        $this->assertCatalogCompatibility($bundle->rules, $documents['service_catalog']);

        $documents['rules'] = $bundle->rules;
        $manifest['bundle_id'] = $bundle->executionId();
        $manifest['bundle_key'] = $bundle->key;
        $manifest['version'] = $bundle->version;
        $manifest['content_hashes']['rules'] = $bundle->contentHash;
        if (! $sameIdentity) {
            $manifest['status'] = 'SANDBOX_CANDIDATE';
        }

        $localAvailableAt = $manifest['knowledge_available_at'] ?? null;
        if (! is_string($localAvailableAt) || $localAvailableAt === '') {
            throw new RuntimeException('Local bundle knowledge availability is invalid.');
        }
        try {
            $localAvailable = new DateTimeImmutable($localAvailableAt);
        } catch (\Exception $exception) {
            throw new RuntimeException('Local bundle knowledge availability is invalid.', previous: $exception);
        }
        if ($bundle->publishedAt > $localAvailable) {
            $manifest['knowledge_available_at'] = $bundle->publishedAt->format(DATE_ATOM);
        }

        $documents['bundle_manifest'] = $manifest;
        $hashes['rules'] = $bundle->contentHash;
        $hashes['bundle_manifest'] = CanonicalJson::hash($manifest);
        $knowledge['rule_bundle_id'] = $bundle->executionId();
        $knowledge['content_hashes'] = $hashes;
        $knowledge['bundle_status'] = $manifest['status'];
        $knowledge['knowledge_available_at'] = $manifest['knowledge_available_at'];
        $knowledge['documents'] = $documents;
        $knowledge['rule_bundle_source'] = [
            'type' => 'DATABASE',
            'database_id' => $bundle->databaseId,
            'bundle_key' => $bundle->key,
            'version' => $bundle->version,
            'content_hash' => $bundle->contentHash,
            'published_at' => $bundle->publishedAt->format(DATE_ATOM),
        ];

        return $knowledge;
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @param  array<string, mixed>  $serviceCatalog
     */
    private function assertCatalogCompatibility(array $rules, array $serviceCatalog): void
    {
        $catalogId = $serviceCatalog['catalog_id'] ?? null;
        if (! is_string($catalogId) || $catalogId === '') {
            throw new RuntimeException('Local service catalog id is invalid.');
        }
        $available = array_fill_keys([$catalogId, $catalogId.'.LC116', $catalogId.'.CTRIBNAC'], true);
        foreach ($rules as $rule) {
            $condition = $rule['when'] ?? null;
            if (! is_array($condition)) {
                continue;
            }
            foreach ($this->catalogReferences($condition) as $referencedCatalog) {
                if (! isset($available[$referencedCatalog])) {
                    throw new RuntimeException("Database rule bundle references an unavailable local catalog: {$referencedCatalog}");
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $condition
     * @return list<string>
     */
    private function catalogReferences(array $condition): array
    {
        $references = [];
        $catalog = $condition['catalog'] ?? null;
        if (is_string($catalog) && $catalog !== '') {
            $references[] = $catalog;
        }
        foreach (['all', 'any'] as $logical) {
            $children = $condition[$logical] ?? null;
            if (! is_array($children)) {
                continue;
            }
            foreach ($children as $child) {
                if (is_array($child)) {
                    $references = [...$references, ...$this->catalogReferences($child)];
                }
            }
        }
        $negated = $condition['not'] ?? null;
        if (is_array($negated)) {
            $references = [...$references, ...$this->catalogReferences($negated)];
        }

        return array_values(array_unique($references));
    }
}
