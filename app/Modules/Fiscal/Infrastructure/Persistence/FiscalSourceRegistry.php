<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Persistence;

use App\Modules\Fiscal\Core\Evidence\SourceArtifact;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class FiscalSourceRegistry
{
    public function __construct(private ConnectionInterface $database) {}

    public function register(SourceArtifact $artifact, string $authorityType, ?string $publishedOn, ?string $effectiveFrom): string
    {
        $source = $this->database->table('fiscal_sources')->where('source_key', $artifact->sourceKey)->first();
        $sourceId = is_string($source?->id) ? $source->id : Str::uuid7()->toString();
        if ($source === null) {
            $this->database->table('fiscal_sources')->insert([
                'id' => $sourceId,
                'source_key' => $artifact->sourceKey,
                'name' => $artifact->sourceKey,
                'authority_type' => $authorityType,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif ($source->authority_type !== $authorityType) {
            throw new RuntimeException("Source authority cannot be changed: {$artifact->sourceKey}");
        }

        $existing = $this->database->table('fiscal_source_versions')
            ->where('source_id', $sourceId)
            ->where('version', $artifact->version)
            ->first();
        if ($existing !== null) {
            if ($existing->sha256 !== $artifact->sha256) {
                throw new RuntimeException("Source version cannot be overwritten: {$artifact->sourceKey}@{$artifact->version}");
            }
            $existingPublishedOn = is_string($existing->published_on) ? $existing->published_on : null;
            $existingEffectiveFrom = is_string($existing->effective_from) ? $existing->effective_from : null;
            if ($existingPublishedOn !== $publishedOn || $existingEffectiveFrom !== $effectiveFrom) {
                throw new RuntimeException("Source version metadata cannot be changed: {$artifact->sourceKey}@{$artifact->version}");
            }

            return (string) $existing->id;
        }
        $sameContent = $this->database->table('fiscal_source_versions')
            ->where('source_id', $sourceId)
            ->where('sha256', $artifact->sha256)
            ->first();
        if ($sameContent !== null) {
            throw new RuntimeException("Identical source content is already registered as version {$sameContent->version}.");
        }

        $id = Str::uuid7()->toString();
        $this->database->table('fiscal_source_versions')->insert([
            'id' => $id,
            'source_id' => $sourceId,
            'version' => $artifact->version,
            'sha256' => $artifact->sha256,
            'original_name' => basename($artifact->path),
            'storage_path' => $artifact->path,
            'published_on' => $publishedOn,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'obtained_at' => now(),
            'metadata' => CanonicalJson::encode($artifact->manifest()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
