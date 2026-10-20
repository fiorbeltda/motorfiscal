<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Classification;

use App\Modules\Fiscal\ISS\IssLocationStrategy;

final readonly class CanonicalService
{
    /** @param list<string> $nbsCandidates */
    public function __construct(
        public string $id,
        public ServiceFamily $family,
        public string $lc116Code,
        public string $ctribnacCode,
        public string $description,
        public IssLocationStrategy $locationStrategy,
        public ?string $dpsDetailGroup,
        public array $nbsCandidates,
        public MappingStatus $mappingStatus,
        public string $sourceId,
        public ?string $importLocationOverride = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'canonical_id' => $this->id,
            'family' => $this->family->value,
            'lc116_code' => $this->lc116Code,
            'ctribnac_code' => $this->ctribnacCode,
            'description' => $this->description,
            'location_strategy' => $this->locationStrategy->value,
            'dps_detail_group' => $this->dpsDetailGroup,
            'nbs_candidates' => $this->nbsCandidates,
            'mapping_status' => $this->mappingStatus->value,
            'source_id' => $this->sourceId,
            'import_location_override' => $this->importLocationOverride,
        ];
    }
}
