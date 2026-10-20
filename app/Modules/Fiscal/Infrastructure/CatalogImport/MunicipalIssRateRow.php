<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;

final readonly class MunicipalIssRateRow
{
    public function __construct(
        public string $municipalityIbge,
        public string $uf,
        public string $municipalityName,
        public string $serviceCode,
        public string $ctribnacCode,
        public string $municipalServiceCode,
        public ?string $incidenceRaw,
        public ?string $rate,
        public LocalDate $effectiveFrom,
        public ?LocalDate $effectiveTo,
        public string $rawRowHash,
    ) {}

    public function validityKey(): string
    {
        return CanonicalJson::hash([
            'municipality_ibge' => $this->municipalityIbge,
            'service_code' => $this->serviceCode,
            'ctribnac_code' => $this->ctribnacCode,
            'municipal_service_code' => $this->municipalServiceCode,
            'effective_from' => $this->effectiveFrom->value(),
            'effective_to' => $this->effectiveTo?->value(),
        ]);
    }

    /** @return array<string, mixed> */
    public function toDatabaseRow(string $sourceVersionId, string $timestamp): array
    {
        return [
            'source_version_id' => $sourceVersionId,
            'municipality_ibge' => $this->municipalityIbge,
            'uf' => $this->uf,
            'municipality_name' => $this->municipalityName,
            'service_code' => $this->serviceCode,
            'ctribnac_code' => $this->ctribnacCode,
            'municipal_service_code' => $this->municipalServiceCode,
            'incidence_raw' => $this->incidenceRaw,
            'rate' => $this->rate,
            'effective_from' => $this->effectiveFrom->value(),
            'effective_to' => $this->effectiveTo?->value(),
            'validity_key' => $this->validityKey(),
            'raw_row_hash' => $this->rawRowHash,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    public function logicalHash(): string
    {
        return CanonicalJson::hash([
            'municipality_ibge' => $this->municipalityIbge,
            'uf' => $this->uf,
            'municipality_name' => $this->municipalityName,
            'service_code' => $this->serviceCode,
            'ctribnac_code' => $this->ctribnacCode,
            'municipal_service_code' => $this->municipalServiceCode,
            'incidence_raw' => $this->incidenceRaw,
            'rate' => $this->rate,
            'effective_from' => $this->effectiveFrom->value(),
            'effective_to' => $this->effectiveTo?->value(),
        ]);
    }
}
