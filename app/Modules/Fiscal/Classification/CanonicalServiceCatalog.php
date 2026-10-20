<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Classification;

use App\Modules\Fiscal\ISS\IssLocationStrategy;
use JsonException;
use RuntimeException;

final class CanonicalServiceCatalog
{
    /** @var array<string, CanonicalService> */
    private array $services = [];

    /** @param iterable<CanonicalService> $services */
    public function __construct(
        public readonly string $catalogId,
        public readonly string $version,
        iterable $services,
    ) {
        foreach ($services as $service) {
            if (isset($this->services[$service->id])) {
                throw new RuntimeException("Duplicate canonical service: {$service->id}");
            }
            $this->services[$service->id] = $service;
        }
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("Canonical service catalog not found: {$path}");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid canonical service catalog: {$exception->getMessage()}", previous: $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException('Canonical service catalog has an invalid structure.');
        }

        return self::fromArray($data);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (! isset($data['catalog_id'], $data['version'], $data['services']) || ! is_array($data['services'])) {
            throw new RuntimeException('Canonical service catalog has an invalid structure.');
        }

        $services = [];
        foreach ($data['services'] as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Canonical service row must be an object.');
            }
            $services[] = new CanonicalService(
                id: (string) ($row['id'] ?? ''),
                family: ServiceFamily::from((string) ($row['family'] ?? '')),
                lc116Code: (string) ($row['lc116_code'] ?? ''),
                ctribnacCode: (string) ($row['ctribnac_code'] ?? ''),
                description: (string) ($row['description'] ?? ''),
                locationStrategy: IssLocationStrategy::from((string) ($row['location_strategy'] ?? '')),
                dpsDetailGroup: isset($row['dps_detail_group']) ? (string) $row['dps_detail_group'] : null,
                nbsCandidates: isset($row['nbs_candidates']) && is_array($row['nbs_candidates']) ? array_values(array_map('strval', $row['nbs_candidates'])) : [],
                mappingStatus: MappingStatus::from((string) ($row['mapping_status'] ?? 'REVIEW_REQUIRED')),
                sourceId: (string) ($row['source_id'] ?? ''),
                importLocationOverride: isset($row['import_location_override']) ? (string) $row['import_location_override'] : null,
            );
        }

        return new self((string) $data['catalog_id'], (string) $data['version'], $services);
    }

    public function find(string $id): ?CanonicalService
    {
        return $this->services[$id] ?? null;
    }

    public function findByCtribnac(string $ctribnacCode): ?CanonicalService
    {
        foreach ($this->services as $service) {
            if ($service->ctribnacCode === $ctribnacCode) {
                return $service;
            }
        }

        return null;
    }

    /** @return list<CanonicalService> */
    public function all(): array
    {
        return array_values($this->services);
    }
}
