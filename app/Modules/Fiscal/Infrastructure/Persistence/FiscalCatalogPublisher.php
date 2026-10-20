<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Persistence;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\CatalogImport\FiscalCatalogEntry;
use App\Modules\Fiscal\Infrastructure\CatalogImport\IssLocationRuleRow;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class FiscalCatalogPublisher
{
    public function __construct(private ConnectionInterface $database) {}

    /**
     * @param  list<FiscalCatalogEntry>  $entries
     * @return array<string, mixed>
     */
    public function publish(string $catalogKey, string $name, string $version, string $sourceVersionId, ?string $effectiveFrom, array $entries): array
    {
        $payloads = [];
        foreach ($entries as $entry) {
            $payload = $entry->toCatalogPayload();
            if (($payload['entry_key'] ?? null) !== $entry->entryKey()) {
                throw new RuntimeException('Catalog entry payload key does not match entryKey().');
            }
            $payloads[] = $payload;
        }
        usort($payloads, static fn (array $left, array $right): int => $left['entry_key'] <=> $right['entry_key']);
        $contentHash = CanonicalJson::hash(['entries' => $payloads]);

        return $this->database->transaction(function () use ($catalogKey, $name, $version, $sourceVersionId, $effectiveFrom, $entries, $payloads, $contentHash): array {
            $catalog = $this->database->table('fiscal_catalogs')->where('catalog_key', $catalogKey)->first();
            $catalogId = is_string($catalog?->id) ? $catalog->id : Str::uuid7()->toString();
            if ($catalog === null) {
                $this->database->table('fiscal_catalogs')->insert([
                    'id' => $catalogId,
                    'catalog_key' => $catalogKey,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $existing = $this->database->table('fiscal_catalog_versions')
                ->where('catalog_id', $catalogId)
                ->where('version', $version)
                ->first();
            if ($existing !== null && $existing->content_hash !== $contentHash) {
                throw new RuntimeException("Catalog version cannot be overwritten: {$catalogKey}@{$version}");
            }
            $status = $effectiveFrom === null ? 'CANDIDATE' : 'PUBLISHED';
            if ($existing !== null) {
                $existingEffectiveFrom = is_string($existing->effective_from) ? $existing->effective_from : null;
                if ((string) $existing->source_version_id !== $sourceVersionId || $existingEffectiveFrom !== $effectiveFrom || (string) $existing->status !== $status) {
                    throw new RuntimeException("Catalog version provenance cannot be changed: {$catalogKey}@{$version}");
                }

                return ['status' => $status, 'published' => $status === 'PUBLISHED', 'idempotent' => true, 'catalog_version_id' => (string) $existing->id, 'content_hash' => $contentHash, 'entries' => count($entries)];
            }

            $catalogVersionId = Str::uuid7()->toString();
            $this->database->table('fiscal_catalog_versions')->insert([
                'id' => $catalogVersionId,
                'catalog_id' => $catalogId,
                'source_version_id' => $sourceVersionId,
                'version' => $version,
                'content_hash' => $contentHash,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'recorded_at' => now(),
                'superseded_at' => null,
                'status' => $status,
            ]);
            foreach (array_chunk($payloads, 1000) as $chunk) {
                $rows = [];
                foreach ($chunk as $payload) {
                    $rows[] = [
                        'catalog_version_id' => $catalogVersionId,
                        'entry_key' => (string) $payload['entry_key'],
                        'payload' => CanonicalJson::encode($payload),
                        'entry_hash' => CanonicalJson::hash($payload),
                    ];
                }
                $this->database->table('fiscal_catalog_entries')->insert($rows);
            }
            if ($entries !== [] && $entries[0] instanceof IssLocationRuleRow) {
                $rows = [];
                foreach ($entries as $entry) {
                    if (! $entry instanceof IssLocationRuleRow) {
                        continue;
                    }
                    $payload = [
                        'ctribnac_code' => $entry->ctribnacCode,
                        'description' => $entry->description,
                        'strategy' => $entry->strategy,
                        'import_override' => $entry->importOverride,
                        'required_detail_group' => $entry->requiredDetailGroup,
                    ];
                    $rows[] = [
                        'catalog_version_id' => $catalogVersionId,
                        'ctribnac_code' => $entry->ctribnacCode,
                        'strategy' => $entry->strategy,
                        'import_override' => $entry->importOverride,
                        'required_detail_group' => $entry->requiredDetailGroup,
                        'payload' => CanonicalJson::encode($payload),
                    ];
                }
                foreach (array_chunk($rows, 1000) as $chunk) {
                    $this->database->table('iss_location_rules')->insert($chunk);
                }
            }

            return ['status' => $status, 'published' => $status === 'PUBLISHED', 'idempotent' => false, 'catalog_version_id' => $catalogVersionId, 'content_hash' => $contentHash, 'entries' => count($entries)];
        });
    }
}
