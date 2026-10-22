<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/** Versioned structural consistency,not a new source observation or credit entitlement. */
final readonly class CreditInputPolicy122
{
    public const VERSION = '2026.10.21-122-draft1';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => 'CREDIT-INPUT-122-V1', 'available_at' => MaterialInputPolicy119::AVAILABLE_AT,
            'source' => MaterialInputPolicy119::policy()['source'], 'source_articles' => 'LC214 arts.47-57;art.64 §5º VII',
            'amount_types' => ['DECIMAL_STRING', 'JSON_INTEGER'],
            'line_identity' => 'DOCUMENT_TAX_KIND_EVIDENCE_WITH_DISTINCT_EXPLICIT_LINE_REFERENCES',
            'scope' => 'ORDINARY_INPUT_CREDIT_STRUCTURE_NOT_DOCUMENT_AUTHENTICITY_OR_APPROPRIATION'];
    }

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        return in_array($codes['version'] ?? null, [self::VERSION, CIndOpReference126::VERSION, DpsLayoutPolicy127::VERSION, DpsForeignPolicy128::VERSION, ImportResponsibility129::VERSION, DocumentSchedule131::VERSION, DocumentSchedule131::PRIOR_VERSION, DocumentIssIntegration134::VERSION], true)
            || array_key_exists('credit_input_policy122', $codes);
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function qualify(array $codes, DateTimeImmutable $knownAt): array
    {
        $actual = $codes['credit_input_policy122'] ?? null;
        $reason = ! is_array($actual) || CanonicalJson::hash($actual) !== CanonicalJson::hash(self::policy())
            ? 'RTC_CREDIT122_SOURCE_POLICY_NOT_BOUND'
            : ($knownAt < new DateTimeImmutable(MaterialInputPolicy119::AVAILABLE_AT) ? 'RTC_CREDIT122_SOURCE_NOT_YET_OBSERVED' : null);

        return ['status' => $reason === null ? 'RESOLVED' : 'ACTION_REQUIRED',
            'reason' => $reason ?? 'RTC_CREDIT122_SOURCE_POLICY_BOUND', 'missing_facts' => [],
            'policy_hash' => CanonicalJson::hash(self::policy()), 'credit_granted' => false,
            'document_authenticated' => false, 'extinction_proven' => false];
    }
}
