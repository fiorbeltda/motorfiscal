<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\MunicipalParameters;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\ISS\LocationIdentifier;
use App\Modules\Fiscal\ISS\MunicipalFiscalParametersResult;
use App\Modules\Fiscal\ISS\MunicipalParameterProvider;
use App\Modules\Fiscal\ISS\ServiceCode;

final class CapturingMunicipalParameterProvider implements MunicipalParameterProvider
{
    /** @var array<string, array<string, mixed>> */
    private array $responses = [];

    public function __construct(private readonly MunicipalParameterProvider $delegate) {}

    public function resolve(LocationIdentifier $location, ServiceCode $service, LocalDate $competence, FiscalDecisionContext $context): MunicipalFiscalParametersResult
    {
        $result = $this->delegate->resolve($location, $service, $competence, $context);
        $this->responses[$this->key($location->ibgeCode, $service->ctribnac, $competence->value())] = [
            'municipality_ibge' => $location->ibgeCode,
            'ctribnac_code' => $service->ctribnac,
            'competence_date' => $competence->value(),
            ...$result->toArray(),
        ];

        return $result;
    }

    /** @return array<string, mixed> */
    public function snapshotCatalog(string $version, string $baseCatalogHash): array
    {
        ksort($this->responses, SORT_STRING);

        return [
            'catalog_id' => 'MUNICIPAL_PARAMETERS_EXECUTION_SNAPSHOT',
            'version' => $version,
            'source_type' => 'SNAPSHOT',
            'base_catalog_hash' => $baseCatalogHash,
            'notice' => 'Respostas municipais capturadas para replay determinístico; não constituem catálogo completo.',
            'responses' => array_values($this->responses),
        ];
    }

    private function key(string $municipality, string $service, string $competence): string
    {
        return "{$municipality}|{$service}|{$competence}";
    }
}
