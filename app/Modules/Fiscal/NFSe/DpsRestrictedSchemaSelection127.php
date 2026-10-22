<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use DateTimeImmutable;

final readonly class DpsRestrictedSchemaSelection127
{
    /** @param array<string, mixed> $identity
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    public function qualify(array $identity, array $codes, string $knowledgeAt): array
    {
        if (($identity['environment'] ?? null) !== '2') {
            return $this->stop('ACTION_REQUIRED', 'PRODUCTION_ENVIRONMENT_FORBIDDEN');
        }
        $known = $this->timestamp($knowledgeAt);
        $issued = $this->timestamp($identity['issued_at'] ?? null);
        if ($known === null || $issued === null) {
            return $this->stop('INVALID', 'EXACT_TIMESTAMP_REQUIRED');
        }
        $policy = (new DpsLayoutPolicy127)->qualify($codes, $known);
        if ($policy['status'] !== 'RESOLVED') {
            return $policy;
        }
        if ($issued < new DateTimeImmutable(DpsLayoutPolicy127::OBSERVED_AT)) {
            return $this->stop('ACTION_REQUIRED', 'ARTIFACT_NOT_YET_OBSERVED');
        }
        if ($issued > $known) {
            return $this->stop('INVALID', 'ISSUANCE_AFTER_KNOWLEDGE');
        }
        $competence = $identity['competence_date'] ?? null;
        $date = is_string($competence) ? DateTimeImmutable::createFromFormat('!Y-m-d', $competence) : false;
        if ($date === false || $date->format('Y-m-d') !== $competence || $competence > $issued->format('Y-m-d')) {
            return $this->stop('INVALID', 'COMPETENCE_DATE_CONFLICT');
        }
        $series = $identity['series'] ?? null;
        if (! is_string($series) || preg_match('/\A(?:[0-9]{1,4}|[0-8][0-9]{4})\z/D', $series) !== 1) {
            return $this->stop('INVALID', 'SERIES_OUTSIDE_RESTRICTED_SCHEMA');
        }

        return ['status' => 'VALID', 'reason' => 'RTC_DPS127_LOCAL_SCHEMA_SELECTION_BOUND',
            'scope' => 'LOCAL_STRUCTURAL_VALIDATION_ONLY', 'environment' => '2',
            'schema_sha256' => DpsLayoutPolicy127::SOURCE_SHA256,
            'root_sha256' => DpsLayoutPolicy127::ROOT_SHA256, 'root_schema' => 'DPS_v1.01.xsd',
            'source_observed_at' => DpsLayoutPolicy127::OBSERVED_AT,
            'remote_deployment_verified' => false, 'document_obligation_qualified' => false, 'production_ready' => false];
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
        return ['status' => $status, 'reason' => 'RTC_DPS127_'.$reason, 'production_ready' => false];
    }
}
