<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\MunicipalParameters;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\ISS\IssResponsibility;
use App\Modules\Fiscal\ISS\LocationIdentifier;
use App\Modules\Fiscal\ISS\MunicipalFiscalParametersResult;
use App\Modules\Fiscal\ISS\MunicipalParameterProvider;
use App\Modules\Fiscal\ISS\MunicipalParameterStatus;
use App\Modules\Fiscal\ISS\ServiceCode;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseMunicipalParameterProvider implements MunicipalParameterProvider
{
    public function __construct(
        private ConnectionInterface $database,
        private string $sourceKey,
        private string $sourceVersion,
    ) {}

    public function resolve(LocationIdentifier $location, ServiceCode $service, LocalDate $competence, FiscalDecisionContext $context): MunicipalFiscalParametersResult
    {
        $snapshots = $this->database->table('municipal_parameter_snapshots as snapshots')
            ->leftJoin('fiscal_source_versions as versions', 'versions.id', '=', 'snapshots.source_version_id')
            ->leftJoin('fiscal_sources as sources', 'sources.id', '=', 'versions.source_id')
            ->where('snapshots.municipality_ibge', $location->ibgeCode)
            ->where('snapshots.service_code', $service->ctribnac)
            ->where('snapshots.competence_date', $competence->value())
            ->where('sources.source_key', $this->sourceKey)
            ->where('versions.version', $this->sourceVersion)
            ->where(function ($query) use ($context): void {
                $query->whereNull('snapshots.fetched_at')->orWhere('snapshots.fetched_at', '<=', $context->knowledgeAt);
            })
            ->select(['snapshots.*', 'versions.version as source_version', 'sources.source_key as source_id'])
            ->get();
        if ($snapshots->count() > 1) {
            return new MunicipalFiscalParametersResult(MunicipalParameterStatus::CONFLICT, null, null, null, 'SNAPSHOT', ['MUNICIPAL_SNAPSHOT_CONFLICT']);
        }
        if ($snapshots->count() === 1) {
            $snapshot = $snapshots->first();
            if (! $snapshot instanceof \stdClass) {
                return new MunicipalFiscalParametersResult(MunicipalParameterStatus::CONFLICT, null, null, null, 'SNAPSHOT', ['MUNICIPAL_SNAPSHOT_CORRUPT']);
            }
            $payload = json_decode((string) $snapshot->payload, true);
            if (! is_array($payload)) {
                return new MunicipalFiscalParametersResult(MunicipalParameterStatus::CONFLICT, null, null, null, 'SNAPSHOT', ['MUNICIPAL_SNAPSHOT_CORRUPT']);
            }

            return $this->fromPayload(
                $payload,
                is_string($snapshot->source_version) ? $snapshot->source_version : null,
                (string) $snapshot->source_type,
                is_string($snapshot->source_id) ? $snapshot->source_id : null,
            );
        }

        $rates = $this->database->table('municipal_iss_rates as rates')
            ->join('fiscal_source_versions as versions', 'versions.id', '=', 'rates.source_version_id')
            ->join('fiscal_sources as sources', 'sources.id', '=', 'versions.source_id')
            ->where('rates.municipality_ibge', $location->ibgeCode)
            ->where('rates.ctribnac_code', $service->ctribnac)
            ->where('rates.effective_from', '<=', $competence->value())
            ->where(function ($query) use ($competence): void {
                $query->whereNull('rates.effective_to')->orWhere('rates.effective_to', '>=', $competence->value());
            })
            ->where('sources.source_key', $this->sourceKey)
            ->where('versions.version', $this->sourceVersion)
            ->where('versions.obtained_at', '<=', $context->knowledgeAt)
            ->select(['rates.rate', 'versions.version as source_version', 'sources.source_key as source_id'])
            ->get();
        if ($rates->count() > 1) {
            return new MunicipalFiscalParametersResult(MunicipalParameterStatus::CONFLICT, null, null, null, 'OFFICIAL_FILE', ['MUNICIPAL_PARAMETERS_OVERLAP']);
        }
        if ($rates->count() === 0) {
            return new MunicipalFiscalParametersResult(MunicipalParameterStatus::NOT_FOUND, null, null, null, 'OFFICIAL_FILE', ['MUNICIPAL_PARAMETERS_NOT_COVERED']);
        }
        $rate = $rates->first();
        if (! $rate instanceof \stdClass) {
            return new MunicipalFiscalParametersResult(MunicipalParameterStatus::CONFLICT, null, null, null, 'OFFICIAL_FILE', ['MUNICIPAL_PARAMETERS_CORRUPT']);
        }

        return new MunicipalFiscalParametersResult(
            $rate->rate === null ? MunicipalParameterStatus::NOT_FOUND : MunicipalParameterStatus::FOUND,
            $rate->rate === null ? null : DecimalValue::fromString((string) $rate->rate)->rounded(6),
            null,
            (string) $rate->source_version,
            'OFFICIAL_FILE',
            $rate->rate === null
                ? ['MUNICIPAL_RATE_UNKNOWN']
                : ['MUNICIPAL_RATE_RESOLVED', 'MUNICIPAL_RESPONSIBILITY_NOT_COVERED'],
            (string) $rate->source_id,
        );
    }

    /** @param array<string, mixed> $payload */
    private function fromPayload(array $payload, ?string $sourceVersion, string $sourceType, ?string $sourceId): MunicipalFiscalParametersResult
    {
        $rate = isset($payload['rate']) ? DecimalValue::fromString((string) $payload['rate']) : null;
        $responsibility = isset($payload['responsibility']) ? IssResponsibility::tryFrom((string) $payload['responsibility']) : null;
        if ($rate === null) {
            return new MunicipalFiscalParametersResult(
                MunicipalParameterStatus::NOT_FOUND,
                null,
                $responsibility,
                $sourceVersion,
                $sourceType,
                ['MUNICIPAL_RATE_UNKNOWN'],
                $sourceId,
                isset($payload['legal_basis']) && is_string($payload['legal_basis']) ? $payload['legal_basis'] : null,
                isset($payload['rule_id']) && is_string($payload['rule_id']) ? $payload['rule_id'] : null,
            );
        }

        return new MunicipalFiscalParametersResult(
            MunicipalParameterStatus::FOUND,
            $rate,
            $responsibility,
            $sourceVersion,
            $sourceType,
            $responsibility === null
                ? ['MUNICIPAL_RATE_RESOLVED', 'MUNICIPAL_RESPONSIBILITY_NOT_COVERED']
                : ['MUNICIPAL_PARAMETERS_RESOLVED'],
            $sourceId,
            isset($payload['legal_basis']) && is_string($payload['legal_basis']) ? $payload['legal_basis'] : null,
            isset($payload['rule_id']) && is_string($payload['rule_id']) ? $payload['rule_id'] : null,
        );
    }
}
