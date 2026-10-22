<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

final readonly class DpsForeignPolicy128
{
    public const VERSION = '2026.10.22-128-draft1';

    public const CONTRACT = 'DPS-LOCAL-FOREIGN-ACQUIRER-2026.10.22-128';

    public const POLICY_SHA256 = 'ec67de6d5bfeb9201a70e3cb2dc78d315b790a705e134af1e3e862dafbd6e75b';

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        return in_array($codes['version'] ?? null, [self::VERSION, ImportResponsibility129::VERSION, DocumentSchedule131::VERSION, DocumentSchedule131::PRIOR_VERSION, DocumentIssIntegration134::VERSION], true) || array_key_exists('dps_foreign_policy128', $codes)
            || ($codes['local_xml_projection']['contract_version'] ?? null) === self::CONTRACT;
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function qualify(array $codes, DateTimeImmutable $knownAt): array
    {
        $policy = $codes['dps_foreign_policy128'] ?? null;
        $candidate = $codes['foreign_acquirer_candidate'] ?? null;
        $reason = ! is_array($policy) || CanonicalJson::hash($policy) !== self::POLICY_SHA256
            ? 'SOURCE_POLICY_NOT_BOUND'
            : (! is_array($candidate) || CanonicalJson::hash($candidate) !== $policy['foreign_candidate_sha256']
                ? 'FOREIGN_CANDIDATE_CHANGED'
                : ($knownAt < new DateTimeImmutable($policy['available_at']) ? 'SOURCE_NOT_YET_OBSERVED' : null));

        return ['status' => $reason === null ? 'RESOLVED' : 'ACTION_REQUIRED',
            'reason' => 'RTC_DPS128_'.($reason ?? 'FOREIGN_SOURCE_POLICY_BOUND'),
            'policy_hash' => self::POLICY_SHA256, 'document_obligation_complete' => false,
            'remote_deployment_verified' => false, 'production_ready' => false];
    }
}
