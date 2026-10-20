<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class MunicipalFiscalParametersResult
{
    /** @param list<string> $reasonCodes */
    public function __construct(
        public MunicipalParameterStatus $status,
        public ?DecimalValue $rate,
        public ?IssResponsibility $responsibility,
        public ?string $sourceVersion,
        public ?string $sourceType,
        public array $reasonCodes = [],
        public ?string $sourceId = null,
        public ?string $legalBasis = null,
        public ?string $ruleId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'rate' => $this->rate?->value(),
            'responsibility' => $this->responsibility?->value,
            'source_version' => $this->sourceVersion,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'legal_basis' => $this->legalBasis,
            'rule_id' => $this->ruleId,
            'reason_codes' => $this->reasonCodes,
        ];
    }
}
