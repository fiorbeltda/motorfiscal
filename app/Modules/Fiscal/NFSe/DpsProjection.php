<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Classification\CanonicalService;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\ISS\IssCalculationResult;
use App\Modules\Fiscal\ISS\IssLocationDecision;
use App\Modules\Fiscal\ISS\IssResponsibilityDecision;

final readonly class DpsProjection
{
    public function __construct(
        private CanonicalService $service,
        private IssLocationDecision $location,
        private IssResponsibilityDecision $responsibility,
        private IssCalculationResult $calculation,
        private FactBag $facts,
        /** @var array<string, mixed>|null */
        private ?array $consumptionReform = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $detail = match ($this->service->dpsDetailGroup) {
            'atvEvento' => [
                'atvEvento' => [
                    'required' => true,
                    'municipality_ibge' => $this->location->location?->ibgeCode,
                    'activity_confirmed' => $this->facts->value('operation.event_activity_confirmed'),
                ],
            ],
            'obra' => [
                'obra' => [
                    'required' => true,
                    'work_id' => $this->facts->value('operation.construction_work_id'),
                    'municipality_ibge' => $this->location->location?->ibgeCode,
                    'object_type' => $this->facts->value('operation.construction_object_type'),
                    'contract_type' => $this->facts->value('operation.construction_contract_type'),
                ],
            ],
            default => [],
        };

        $projection = [
            'projection_scope' => 'PARTIAL_SANDBOX_NO_EMISSION',
            'service' => [
                'cTribNac' => $this->service->ctribnacCode,
                'xDescServ' => $this->service->description,
                'detail' => $detail,
            ],
            'values' => [
                'vServ' => $this->calculation->grossAmount,
            ],
            'iss' => [
                'pAliq' => $this->calculation->rate,
                'tpRetISSQN' => $this->responsibility->tpRetIssqn,
            ],
            'expected_sefin_enrichment' => [
                'cLocIncid' => $this->location->location?->ibgeCode,
                'vBC' => $this->calculation->taxableBase,
                'pAliqAplic' => $this->calculation->rate,
                'vISSQN' => $this->calculation->taxAmount,
            ],
        ];
        if ($this->consumptionReform !== null) {
            $document = $this->consumptionReform['decision']['document'] ?? null;
            if (is_string($document['service_nbs'] ?? null)) {
                $projection['service']['cNBS'] = $document['service_nbs'];
            }
            $projection['rtc'] = [
                'schema_version' => $document['schema_version'] ?? 'NT004-RTC-1.01.03',
                'projection_scope' => 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION',
                'issuer_supplied' => $document['issuer_fields'] ?? [],
                'expected_national_enrichment' => $document['national_enrichment'] ?? [],
                'status' => $this->consumptionReform['status'] ?? 'NOT_REQUESTED',
            ];
        }

        return $projection;
    }
}
