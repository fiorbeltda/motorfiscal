<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/** Binds actual observed XSD paths; it does not establish legal deployment. */
final readonly class DpsLayoutPolicy127
{
    public const VERSION = '2026.10.22-127-draft1';

    public const CONTRACT = 'DPS-LOCAL-RESTRICTED-2026.10.22-127';

    public const POLICY_SHA256 = 'd1edd143d819f7dc93f24157978938dfc2f308b719b10c2f26580d9cf03d2c03';

    public const SOURCE_SHA256 = '25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267';

    public const ROOT_SHA256 = 'fe45e5250a48e519aba89fc6a472863b8e602ed957778fb64692804933a00d0c';

    public const OBSERVED_AT = '2026-10-22T11:13:42.341713+00:00';

    public const FIELD_MAPPING = [
        'IBSCBS.finNFSe' => 'finNFSe', 'IBSCBS.indDest' => 'IBSCBS.indDest', 'IBSCBS.cIndOp' => 'IBSCBS.cIndOp',
        'IBSCBS.valores.trib.gIBSCBS.CST' => 'IBSCBS.valores.trib.CST',
        'IBSCBS.valores.trib.gIBSCBS.cClassTrib' => 'IBSCBS.valores.trib.cClassTrib',
    ];

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        return in_array($codes['version'] ?? null, [self::VERSION, DpsForeignPolicy128::VERSION, ImportResponsibility129::VERSION, DocumentSchedule131::VERSION, DocumentSchedule131::PRIOR_VERSION, DocumentIssIntegration134::VERSION], true)
            || array_key_exists('dps_layout_policy127', $codes);
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function qualify(array $codes, DateTimeImmutable $knownAt): array
    {
        $policy = $codes['dps_layout_policy127'] ?? null;
        if (! is_array($policy) || CanonicalJson::hash($policy) !== self::POLICY_SHA256) {
            return $this->stop('SOURCE_POLICY_NOT_BOUND');
        }
        $schema = $codes['dps_xsd'] ?? null;
        $projection = $codes['local_xml_projection'] ?? null;
        $fields = $codes['dps_xsd_fields'] ?? null;
        if (! is_array($schema) || ($schema['source_sha256'] ?? null) !== self::SOURCE_SHA256
            || ($schema['root_schema'] ?? null) !== 'DPS_v1.01.xsd'
            || ! is_array($projection) || ! in_array($projection['contract_version'] ?? null, [self::CONTRACT, DpsForeignPolicy128::CONTRACT], true)
            || ($projection['scope'] ?? null) !== 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION'
            || ($projection['requires_deterministic_snapshot_replay'] ?? null) !== true
            || ($projection['source_sha256'] ?? null) !== self::SOURCE_SHA256
            || ! is_array($projection['schema_selection'] ?? null)
            || CanonicalJson::hash($projection['schema_selection']) !== self::POLICY_SHA256
            || ! is_array($fields) || CanonicalJson::hash($fields) !== $policy['fields_sha256']) {
            return $this->stop('SCHEMA_OR_FIELD_BINDING_CHANGED');
        }
        if ($knownAt < new DateTimeImmutable(self::OBSERVED_AT)) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_DPS127_OBSERVED_SCHEMA_BOUND',
            'policy_hash' => self::POLICY_SHA256, 'source_sha256' => self::SOURCE_SHA256,
            'root_sha256' => self::ROOT_SHA256, 'source_observed_at' => self::OBSERVED_AT,
            'remote_deployment_verified' => false, 'production_ready' => false];
    }

    /** @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function mapIssuerFields(array $fields): array
    {
        $mapped = [];
        foreach ($fields as $path => $value) {
            $prefix = str_starts_with($path, 'DPS.infDPS.') ? 'DPS.infDPS.' : '';
            $relative = $prefix === '' ? $path : substr($path, strlen($prefix));
            $mapped[$prefix.(self::FIELD_MAPPING[$relative] ?? $relative)] = $value;
        }

        return $mapped;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DPS127_'.$reason,
            'remote_deployment_verified' => false, 'production_ready' => false];
    }
}
