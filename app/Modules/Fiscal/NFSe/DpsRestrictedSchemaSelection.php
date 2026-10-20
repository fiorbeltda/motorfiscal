<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use DateTimeImmutable;

/** Qualifies a local structural probe; observation dates never establish fiscal deployment or obligation. */
final readonly class DpsRestrictedSchemaSelection
{
    public const CONTRACT = 'DPS-LOCAL-RESTRICTED-XSD-2026.10.14-74';

    public const ARCHIVE_SHA256 = '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc';

    public const ROOT_SHA256 = 'c7dab363d8cf7c83fc2b3b21e72cf669a51bd30947a5690685ea96c4b3e39dcd';

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $projection
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function qualify(array $identity, array $projection, array $schema, string $knowledgeAt): array
    {
        $policy = $projection['schema_selection'] ?? null;
        if (! is_array($policy) || ! in_array($projection['contract_version'] ?? null, [self::CONTRACT, DpsForeignAcquirerCandidate::CONTRACT], true)
            || ($projection['scope'] ?? null) !== 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION'
            || ($policy['scope'] ?? null) !== 'LOCAL_STRUCTURAL_VALIDATION_ONLY'
            || ($policy['environment'] ?? null) !== '2'
            || ($policy['remote_deployment_verified'] ?? null) !== false
            || ($projection['source_sha256'] ?? null) !== self::ARCHIVE_SHA256
            || ($schema['source_sha256'] ?? null) !== self::ARCHIVE_SHA256
            || ($schema['root_schema'] ?? null) !== 'DPS_v1.01.xsd'
            || ($policy['root_sha256'] ?? null) !== self::ROOT_SHA256) {
            return $this->stop('ACTION_REQUIRED', 'ARTIFACT_CONTRACT_NOT_PROVEN');
        }
        if (($identity['environment'] ?? null) !== '2') {
            return $this->stop('ACTION_REQUIRED', 'PRODUCTION_ENVIRONMENT_FORBIDDEN');
        }
        $observed = $this->timestamp($policy['source_observed_at'] ?? null);
        $known = $this->timestamp($knowledgeAt);
        $issued = $this->timestamp($identity['issued_at'] ?? null);
        if ($observed === null || $known === null || $issued === null) {
            return $this->stop('INVALID', 'EXACT_TIMESTAMP_REQUIRED');
        }
        if ($known < $observed || $issued < $observed) {
            return $this->stop('ACTION_REQUIRED', 'ARTIFACT_NOT_YET_OBSERVED');
        }
        if ($issued > $known) {
            return $this->stop('INVALID', 'ISSUANCE_AFTER_KNOWLEDGE');
        }
        $competence = $identity['competence_date'] ?? null;
        $date = is_string($competence) ? DateTimeImmutable::createFromFormat('!Y-m-d', $competence) : false;
        if ($date === false || $date->format('Y-m-d') !== $competence
            || $competence > $issued->format('Y-m-d')) {
            return $this->stop('INVALID', 'COMPETENCE_DATE_CONFLICT');
        }
        $series = $identity['series'] ?? null;
        if (! is_string($series) || preg_match('/\A[0-9]{1,5}\z/D', $series) !== 1 || (int) $series > 89999) {
            return $this->stop('INVALID', 'SERIES_OUTSIDE_RESTRICTED_SCHEMA');
        }

        return ['status' => 'VALID', 'reason' => 'RTC_DPS_RESTRICTED_SCHEMA_LOCAL_SELECTION_BOUND',
            'scope' => 'LOCAL_STRUCTURAL_VALIDATION_ONLY', 'environment' => '2',
            'source_observed_at' => $policy['source_observed_at'],
            'schema_sha256' => self::ARCHIVE_SHA256, 'root_schema' => 'DPS_v1.01.xsd',
            'root_sha256' => self::ROOT_SHA256, 'remote_deployment_verified' => false,
            'document_obligation_qualified' => false, 'production_ready' => false];
    }

    private function timestamp(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)
            || preg_match('/\A(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})\z/D', $value, $parts) !== 1) {
            return null;
        }
        $offset = $parts[4] === 'Z' ? '+00:00' : $parts[4];
        $hour = (int) substr($offset, 1, 2);
        $minute = (int) substr($offset, 4, 2);
        if ($hour > 14 || $minute > 59 || ($hour === 14 && $minute !== 0)) {
            return null;
        }
        $normalized = $parts[1].'T'.$parts[2].'.'.str_pad($parts[3], 6, '0').$offset;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $normalized);

        return $date !== false && $date->format('Y-m-d\TH:i:s.uP') === $normalized ? $date : null;
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_DPS_RESTRICTED_SCHEMA_'.$reason, 'production_ready' => false];
    }
}
