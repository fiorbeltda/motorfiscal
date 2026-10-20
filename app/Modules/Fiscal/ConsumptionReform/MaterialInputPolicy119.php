<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/** A source-bound local input contract, not proof of historical applicability or payment. */
final readonly class MaterialInputPolicy119
{
    public const VERSION = '2026.10.20-119-draft1';

    public const AVAILABLE_AT = '2026-10-20T12:49:04.592626+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => 'MATERIAL-INPUT-119-V1',
            'available_at' => self::AVAILABLE_AT,
            'source' => ['url' => 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214compilado.htm',
                'sha256' => '3ccd180f88a872ff118fc3108fb119837fd2c09101f16d1e032662c22b7140f5',
                'observed_at_raw' => '2026-10-20T12:49:02.0276095+00:00',
                'capture_completed_at_raw' => '2026-10-20T12:49:04.5926255+00:00'],
            'adjustments' => ['source_article' => 'LC214 art. 12',
                'amount_types' => ['DECIMAL_STRING', 'JSON_INTEGER'],
                'repeated_evidence_context' => 'DISTINCT_LINE_REFERENCES_REQUIRED'],
            'collection_references' => CollectionBasisReference119::references(),
            'scope' => 'LOCAL_INPUT_CONSISTENCY_NOT_LEGAL_APPROVAL_OR_PAYMENT'];
    }

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        return ($codes['version'] ?? null) === self::VERSION
            || array_key_exists('material_input_policy119', $codes);
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function qualify(array $codes, DateTimeImmutable $knownAt): array
    {
        $policy = $codes['material_input_policy119'] ?? null;
        $reason = ! is_array($policy) || CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())
            ? 'RTC_INPUT119_SOURCE_POLICY_NOT_BOUND'
            : ($knownAt < new DateTimeImmutable(self::AVAILABLE_AT) ? 'RTC_INPUT119_SOURCE_NOT_YET_OBSERVED' : null);

        return ['status' => $reason === null ? 'RESOLVED' : 'ACTION_REQUIRED',
            'reason' => $reason ?? 'RTC_INPUT119_SOURCE_POLICY_BOUND',
            'contract' => 'MATERIAL-INPUT-119-V1', 'policy_hash' => CanonicalJson::hash(self::policy()),
            'legal_applicability_approved' => false, 'payment_proven' => false, 'production_ready' => false];
    }
}
