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
use App\Modules\Fiscal\ISS\MunicipalParameterRecord;
use App\Modules\Fiscal\ISS\MunicipalParameterStatus;
use App\Modules\Fiscal\ISS\ServiceCode;
use JsonException;
use RuntimeException;

final class JsonMunicipalParameterProvider implements MunicipalParameterProvider
{
    /** @var list<MunicipalParameterRecord> */
    private array $records;

    /** @var array<string, MunicipalFiscalParametersResult> */
    private array $responses;

    /**
     * @param  list<MunicipalParameterRecord>  $records
     * @param  array<string, MunicipalFiscalParametersResult>  $responses
     */
    public function __construct(array $records, array $responses = [])
    {
        $this->records = $records;
        $this->responses = $responses;
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("Municipal parameter catalog not found: {$path}");
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid municipal parameter catalog: {$exception->getMessage()}", previous: $exception);
        }
        if (! is_array($data)) {
            throw new RuntimeException('Municipal parameter catalog has an invalid structure.');
        }

        return self::fromArray($data);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (! isset($data['version']) || (! isset($data['parameters']) && ! isset($data['responses']))) {
            throw new RuntimeException('Municipal parameter catalog has an invalid structure.');
        }
        $records = [];
        $parameters = $data['parameters'] ?? [];
        if (! is_array($parameters)) {
            throw new RuntimeException('Municipal parameter rows must be a list.');
        }
        foreach ($parameters as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Municipal parameter row must be an object.');
            }
            $rate = isset($row['rate']) ? DecimalValue::fromString((string) $row['rate']) : null;
            $responsibility = isset($row['responsibility']) ? IssResponsibility::from((string) $row['responsibility']) : null;
            $records[] = new MunicipalParameterRecord(
                (string) ($row['municipality_ibge'] ?? ''),
                (string) ($row['ctribnac_code'] ?? ''),
                LocalDate::fromString((string) ($row['effective_from'] ?? '')),
                isset($row['effective_to']) ? LocalDate::fromString((string) $row['effective_to']) : null,
                $rate,
                $responsibility,
                (string) $data['version'],
                (string) ($data['source_type'] ?? 'UNKNOWN'),
                (string) ($data['catalog_id'] ?? 'UNKNOWN'),
            );
        }

        $responses = [];
        $responseRows = $data['responses'] ?? [];
        if (! is_array($responseRows)) {
            throw new RuntimeException('Municipal snapshot responses must be a list.');
        }
        foreach ($responseRows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Municipal snapshot response must be an object.');
            }
            $municipality = (string) ($row['municipality_ibge'] ?? '');
            $service = (string) ($row['ctribnac_code'] ?? '');
            $competence = (string) ($row['competence_date'] ?? '');
            if (preg_match('/^\d{7}$/', $municipality) !== 1 || preg_match('/^\d{6}$/', $service) !== 1) {
                throw new RuntimeException('Municipal snapshot response has an invalid key.');
            }
            LocalDate::fromString($competence);
            $status = MunicipalParameterStatus::tryFrom((string) ($row['status'] ?? ''));
            if ($status === null) {
                throw new RuntimeException('Municipal snapshot response has an invalid status.');
            }
            $reasonCodes = $row['reason_codes'] ?? [];
            if (! is_array($reasonCodes) || ! array_is_list($reasonCodes)) {
                throw new RuntimeException('Municipal snapshot response reason codes must be a list.');
            }
            $responses[self::responseKey($municipality, $service, $competence)] = new MunicipalFiscalParametersResult(
                $status,
                isset($row['rate']) ? DecimalValue::fromString((string) $row['rate']) : null,
                isset($row['responsibility']) ? IssResponsibility::tryFrom((string) $row['responsibility']) : null,
                isset($row['source_version']) ? (string) $row['source_version'] : null,
                isset($row['source_type']) ? (string) $row['source_type'] : null,
                array_values(array_map('strval', $reasonCodes)),
                isset($row['source_id']) ? (string) $row['source_id'] : null,
                isset($row['legal_basis']) && is_string($row['legal_basis']) ? $row['legal_basis'] : null,
                isset($row['rule_id']) && is_string($row['rule_id']) ? $row['rule_id'] : null,
            );
        }

        return new self($records, $responses);
    }

    public function resolve(LocationIdentifier $location, ServiceCode $service, LocalDate $competence, FiscalDecisionContext $context): MunicipalFiscalParametersResult
    {
        $snapshot = $this->responses[self::responseKey($location->ibgeCode, $service->ctribnac, $competence->value())] ?? null;
        if ($snapshot !== null) {
            return $snapshot;
        }
        if ($this->responses !== [] && $this->records === []) {
            return new MunicipalFiscalParametersResult(MunicipalParameterStatus::NOT_FOUND, null, null, null, 'SNAPSHOT', ['MUNICIPAL_PARAMETERS_NOT_CAPTURED']);
        }

        $matches = array_values(array_filter(
            $this->records,
            fn (MunicipalParameterRecord $record): bool => $record->municipalityIbge === $location->ibgeCode
                && $record->ctribnacCode === $service->ctribnac
                && $record->isEffectiveOn($competence),
        ));

        if ($matches === []) {
            return new MunicipalFiscalParametersResult(MunicipalParameterStatus::NOT_FOUND, null, null, null, null, ['MUNICIPAL_PARAMETERS_NOT_COVERED']);
        }
        if (count($matches) > 1) {
            return new MunicipalFiscalParametersResult(MunicipalParameterStatus::CONFLICT, null, null, null, null, ['MUNICIPAL_PARAMETERS_OVERLAP']);
        }
        $match = $matches[0];
        if ($match->rate === null || $match->responsibility === null) {
            return new MunicipalFiscalParametersResult(MunicipalParameterStatus::NOT_FOUND, $match->rate, $match->responsibility, $match->sourceVersion, $match->sourceType, ['MUNICIPAL_PARAMETERS_INCOMPLETE']);
        }

        return new MunicipalFiscalParametersResult(MunicipalParameterStatus::FOUND, $match->rate, $match->responsibility, $match->sourceVersion, $match->sourceType, ['MUNICIPAL_PARAMETERS_RESOLVED'], $match->sourceId);
    }

    private static function responseKey(string $municipality, string $service, string $competence): string
    {
        return "{$municipality}|{$service}|{$competence}";
    }
}
