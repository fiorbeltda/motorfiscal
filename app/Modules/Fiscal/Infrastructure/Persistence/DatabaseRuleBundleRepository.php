<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Persistence;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use JsonException;
use RuntimeException;

final readonly class DatabaseRuleBundleRepository
{
    public function __construct(private ConnectionInterface $database) {}

    public function load(string $key, string $version): PublishedRuleBundle
    {
        if ($key === '' || $version === '') {
            throw new RuntimeException('Database rule bundle key and version must be non-empty.');
        }

        $bundle = $this->database->table('fiscal_rule_bundles')
            ->where('bundle_key', $key)
            ->where('version', $version)
            ->first();
        if ($bundle === null) {
            throw new RuntimeException("Published database rule bundle not found: {$key}@{$version}");
        }
        if (($bundle->status ?? null) !== 'PUBLISHED') {
            throw new RuntimeException("Database rule bundle is not published: {$key}@{$version}");
        }

        $databaseId = $bundle->id ?? null;
        $declaredHash = $bundle->content_hash ?? null;
        if (! is_string($databaseId) || $databaseId === ''
            || ! is_string($declaredHash) || preg_match('/^[a-f0-9]{64}$/', $declaredHash) !== 1) {
            throw new RuntimeException("Database rule bundle metadata is invalid: {$key}@{$version}");
        }
        $publishedAt = $this->dateTime($bundle->published_at ?? null, "Database rule bundle publication time is invalid: {$key}@{$version}");

        $rows = $this->database->table('fiscal_rule_bundle_items as items')
            ->join('fiscal_rule_versions as versions', 'versions.id', '=', 'items.rule_version_id')
            ->join('fiscal_rules as rules', 'rules.id', '=', 'versions.rule_id')
            ->leftJoin('fiscal_source_versions as source_versions', 'source_versions.id', '=', 'versions.source_version_id')
            ->leftJoin('fiscal_sources as sources', 'sources.id', '=', 'source_versions.source_id')
            ->where('items.bundle_id', $databaseId)
            ->orderBy('rules.rule_key')
            ->orderBy('versions.version')
            ->get([
                'versions.id as rule_version_id',
                'versions.version as stored_version',
                'versions.effective_from as stored_effective_from',
                'versions.effective_to as stored_effective_to',
                'versions.recorded_at',
                'versions.status as version_status',
                'versions.content_hash as version_content_hash',
                'versions.payload',
                'rules.rule_key',
                'rules.kind as stored_kind',
                'sources.source_key',
                'source_versions.version as source_version',
            ]);
        if ($rows->isEmpty()) {
            throw new RuntimeException("Published database rule bundle has no rules: {$key}@{$version}");
        }

        $rules = [];
        $selectedRuleIds = [];
        foreach ($rows as $row) {
            if (($row->version_status ?? null) !== 'PUBLISHED') {
                throw new RuntimeException("Database rule bundle contains a non-published rule version: {$key}@{$version}");
            }
            $recordedAt = $this->dateTime(
                $row->recorded_at ?? null,
                "Persisted rule knowledge time is invalid: {$key}@{$version}",
            );
            if ($recordedAt > $publishedAt) {
                throw new RuntimeException("Database rule bundle was published before one of its rule versions was recorded: {$key}@{$version}");
            }
            $payload = $this->payload($row->payload ?? null);
            $ruleId = $payload['rule_id'] ?? null;
            $ruleVersion = $payload['version'] ?? null;
            $ruleKind = $payload['kind'] ?? null;
            $sourceId = $payload['source_id'] ?? null;
            $sourceVersion = $payload['source_version'] ?? null;
            if (! is_string($ruleId) || $ruleId === '' || isset($selectedRuleIds[$ruleId])) {
                throw new RuntimeException("Database rule bundle contains a duplicate or invalid rule id: {$key}@{$version}");
            }
            if ($ruleId !== ($row->rule_key ?? null)
                || $ruleVersion !== ($row->stored_version ?? null)
                || $ruleKind !== ($row->stored_kind ?? null)
                || $sourceId !== ($row->source_key ?? null)
                || $sourceVersion !== ($row->source_version ?? null)) {
                throw new RuntimeException("Persisted rule metadata does not match its immutable payload: {$ruleId}@{$ruleVersion}");
            }
            if (($payload['effective_from'] ?? null) !== $this->dateValue($row->stored_effective_from ?? null)
                || ($payload['effective_to'] ?? null) !== $this->nullableDateValue($row->stored_effective_to ?? null)) {
                throw new RuntimeException("Persisted rule validity does not match its immutable payload: {$ruleId}@{$ruleVersion}");
            }

            $actualHash = CanonicalJson::hash($payload);
            $storedHash = $row->version_content_hash ?? null;
            if (! is_string($storedHash) || ! hash_equals($storedHash, $actualHash)) {
                throw new RuntimeException("Persisted rule content hash mismatch: {$ruleId}@{$ruleVersion}");
            }
            $selectedRuleIds[$ruleId] = true;
            $rules[] = $payload;
        }

        usort($rules, static fn (array $left, array $right): int => [
            (string) ($left['rule_id'] ?? ''),
            (string) ($left['version'] ?? ''),
        ] <=> [
            (string) ($right['rule_id'] ?? ''),
            (string) ($right['version'] ?? ''),
        ]);
        $actualBundleHash = CanonicalJson::hash($rules);
        if (! hash_equals($declaredHash, $actualBundleHash)) {
            throw new RuntimeException("Database rule bundle content hash mismatch: {$key}@{$version}");
        }

        return new PublishedRuleBundle(
            $databaseId,
            $key,
            $version,
            $declaredHash,
            $publishedAt,
            $rules,
        );
    }

    /** @return array<string, mixed> */
    private function payload(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Persisted rule payload is not valid JSON.', previous: $exception);
            }
        } elseif (is_object($value)) {
            try {
                $value = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Persisted rule payload cannot be decoded.', previous: $exception);
            }
        }
        if (! is_array($value) || array_is_list($value)) {
            throw new RuntimeException('Persisted rule payload must be a JSON object.');
        }

        $payload = [];
        foreach ($value as $field => $fieldValue) {
            if (! is_string($field)) {
                throw new RuntimeException('Persisted rule payload keys must be strings.');
            }
            $payload[$field] = $fieldValue;
        }

        return $payload;
    }

    private function dateTime(mixed $value, string $error): DateTimeImmutable
    {
        try {
            if ($value instanceof DateTimeInterface) {
                return new DateTimeImmutable($value->format(DATE_ATOM));
            }
            if (! is_string($value) || $value === '') {
                throw new \InvalidArgumentException;
            }

            return new DateTimeImmutable($value);
        } catch (\Throwable $exception) {
            throw new RuntimeException($error, previous: $exception);
        }
    }

    private function dateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $match) !== 1) {
            return '';
        }

        return $match[0];
    }

    private function nullableDateValue(mixed $value): ?string
    {
        return $value === null ? null : $this->dateValue($value);
    }
}
