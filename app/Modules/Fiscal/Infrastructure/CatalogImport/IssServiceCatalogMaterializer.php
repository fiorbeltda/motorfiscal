<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use App\Modules\Fiscal\Classification\MappingStatus;
use App\Modules\Fiscal\Classification\ServiceFamily;
use App\Modules\Fiscal\ISS\IssLocationStrategy;
use RuntimeException;

final class IssServiceCatalogMaterializer
{
    /** @return array{catalog_id: string, version: string, services: list<array<string, mixed>>, coverage: array<string, int>} */
    public function materialize(
        string $ctribnacPath,
        string $issLocationPath,
        string $catalogId,
        string $version,
    ): array {
        $national = (new CtribNacCatalogImporter)->import($ctribnacPath);
        $territorial = (new IssLocationCatalogImporter)->import($issLocationPath);

        $locationByCode = [];
        foreach ($territorial->rows as $row) {
            $locationByCode[$row->ctribnacCode] = $row;
        }

        $services = [];
        $confirmed = 0;
        $reviewRequired = 0;
        $unsupported = 0;
        foreach ($national->entries as $entry) {
            $location = $locationByCode[$entry->code] ?? null;
            $portMaritimeFallback = $entry->code === '200102'
                && $entry->lc116Code === '20.01'
                && $location === null;
            [$id, $family] = $this->identity($entry->code, $location?->requiredDetailGroup);
            $mappingStatus = match (true) {
                $entry->specialNoIss => MappingStatus::UNSUPPORTED,
                $portMaritimeFallback => MappingStatus::CONFIRMED,
                $entry->lc116Code === null, $location === null, $location->strategy === null => MappingStatus::REVIEW_REQUIRED,
                default => MappingStatus::CONFIRMED,
            };
            match ($mappingStatus) {
                MappingStatus::CONFIRMED => $confirmed++,
                MappingStatus::REVIEW_REQUIRED => $reviewRequired++,
                MappingStatus::UNSUPPORTED => $unsupported++,
            };

            $services[] = [
                'id' => $id,
                'family' => $family->value,
                'lc116_code' => $entry->lc116Code ?? '99.01',
                'ctribnac_code' => $entry->code,
                'description' => $entry->officialDescription,
                'location_strategy' => $this->strategy($portMaritimeFallback ? 'LP' : $location?->strategy, $location?->requiredDetailGroup)->value,
                'dps_detail_group' => $location?->requiredDetailGroup,
                'nbs_candidates' => [],
                'mapping_status' => $mappingStatus->value,
                'source_id' => $portMaritimeFallback
                    ? 'SOURCE.NFSE.CTRIBNAC.ANEXO_B+SOURCE.NFSE.ISS_LOCATION.ANEXO_I:MARITIME_NOTE'
                    : 'SOURCE.NFSE.CTRIBNAC.ANEXO_B+SOURCE.NFSE.ISS_LOCATION.ANEXO_I',
                'import_location_override' => $location->importOverride ?? ($portMaritimeFallback ? 'EDEmit' : null),
            ];
        }

        if (count($services) !== 338) {
            throw new RuntimeException('The materialized ISS service catalog must contain exactly 338 cTribNac entries.');
        }

        return [
            'catalog_id' => $catalogId,
            'version' => $version,
            'services' => $services,
            'coverage' => [
                'total' => count($services),
                'confirmed' => $confirmed,
                'review_required' => $reviewRequired,
                'unsupported' => $unsupported,
            ],
        ];
    }

    /** @return array{string, ServiceFamily} */
    private function identity(string $code, ?string $detailGroup): array
    {
        return match ($code) {
            '010101' => ['SOFTWARE_DEVELOPMENT', ServiceFamily::SOFTWARE],
            '120801' => ['TRADE_FAIR_EVENT', ServiceFamily::EVENT],
            '070202' => ['CONSTRUCTION_CONTRACT', ServiceFamily::CONSTRUCTION],
            default => [
                'CTRIBNAC_'.$code,
                match ($detailGroup) {
                    'atvEvento' => ServiceFamily::EVENT,
                    'obra' => ServiceFamily::CONSTRUCTION,
                    default => ServiceFamily::GENERAL,
                },
            ],
        };
    }

    private function strategy(?string $strategy, ?string $detailGroup): IssLocationStrategy
    {
        return match ($strategy) {
            'EP' => IssLocationStrategy::PROVIDER_ESTABLISHMENT,
            'ET' => IssLocationStrategy::CUSTOMER_ESTABLISHMENT,
            'LP' => match ($detailGroup) {
                'atvEvento' => IssLocationStrategy::EVENT_LOCATION,
                'obra' => IssLocationStrategy::CONSTRUCTION_WORK_LOCATION,
                default => IssLocationStrategy::SERVICE_EXECUTION_LOCATION,
            },
            default => IssLocationStrategy::PROVIDER_ESTABLISHMENT,
        };
    }
}
