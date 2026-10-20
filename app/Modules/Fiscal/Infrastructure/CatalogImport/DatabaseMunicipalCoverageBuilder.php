<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseMunicipalCoverageBuilder
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return array{municipalities: int, municipality_services: int, incomplete_services: int} */
    public function rebuild(string $sourceVersionId): array
    {
        return $this->database->transaction(function () use ($sourceVersionId): array {
            $this->database->table('municipal_service_coverages')->where('source_version_id', $sourceVersionId)->delete();
            $batch = [];
            $municipalities = [];
            $total = 0;
            $incomplete = 0;
            $timestamp = gmdate('Y-m-d H:i:sP');
            $groups = $this->database->table('municipal_iss_rates')
                ->where('source_version_id', $sourceVersionId)
                ->select(['municipality_ibge', 'ctribnac_code'])
                ->selectRaw('COUNT(*) as row_count')
                ->selectRaw('SUM(CASE WHEN rate IS NULL THEN 1 ELSE 0 END) as unknown_rate_count')
                ->selectRaw('MIN(effective_from) as earliest_effective_from')
                ->selectRaw('MAX(effective_to) as latest_effective_to')
                ->whereNotNull('ctribnac_code')
                ->groupBy(['municipality_ibge', 'ctribnac_code'])
                ->orderBy('municipality_ibge')
                ->orderBy('ctribnac_code')
                ->cursor();

            foreach ($groups as $group) {
                $unknown = (int) $group->unknown_rate_count;
                $rowCount = (int) $group->row_count;
                $municipalities[(string) $group->municipality_ibge] = true;
                $total++;
                if ($unknown > 0) {
                    $incomplete++;
                }
                $batch[] = [
                    'source_version_id' => $sourceVersionId,
                    'municipality_ibge' => (string) $group->municipality_ibge,
                    'service_code' => (string) $group->ctribnac_code,
                    'row_count' => $rowCount,
                    'unknown_rate_count' => $unknown,
                    'earliest_effective_from' => (string) $group->earliest_effective_from,
                    'latest_effective_to' => $group->latest_effective_to === null ? null : (string) $group->latest_effective_to,
                    'status' => $unknown === 0 ? 'COVERED' : 'PARTIAL_UNKNOWN_RATE',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                if (count($batch) >= 1000) {
                    $this->database->table('municipal_service_coverages')->insert($batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $this->database->table('municipal_service_coverages')->insert($batch);
            }

            foreach (array_keys($municipalities) as $municipality) {
                $this->database->table('municipal_coverage_statuses')->updateOrInsert(
                    ['municipality_ibge' => $municipality],
                    [
                        'rates_available' => true,
                        'status' => 'PARTIAL_SOURCE_COVERAGE',
                        'last_checked_at' => $timestamp,
                        'notes' => '["Coverage reflects only the imported source; national completeness is not assumed."]',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                );
            }

            return ['municipalities' => count($municipalities), 'municipality_services' => $total, 'incomplete_services' => $incomplete];
        });
    }
}
