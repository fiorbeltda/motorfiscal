<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/**
 * B28: remaining non-onerous species of LC 214 art. 5 within the NFS-e scope.
 *
 * - art. 5, II: gifts and bonuses of services, taxed on market value (art. 12 §4, I/II); a bonus that appears in
 *   the fiscal document and does not depend on a later event is not a separate operation (§1, I; cClassTrib 410001).
 * - art. 5, IV: non-onerous or below-market supplies to a related party (§§2-6), taxed on market value
 *   (art. 12 §4, IV); employees/partners and their relatives use the specific art. 5, I, a/b route.
 * - art. 5, III (transmission of goods to partners) is goods/NF-e: outside the NFS-e product scope.
 *
 * Market value must be the value of comparable operations between unrelated parties, declared with evidence.
 * The NFS-e at market value with regular taxation is a candidate (the NT009 layout states it for donations
 * with counter-benefit only): review_status SANDBOX_REVIEW_REQUIRED, never an automatic approval.
 */
final readonly class NonOnerousSpeciesB28
{
    public const KEY = 'non_onerous_species_b28';

    public const POLICY = ['version' => 'B28', 'species' => ['LC214_ART5_II', 'LC214_ART5_IV'],
        'market_value_method' => 'COMPARABLE_UNRELATED_OPERATIONS', 'document_candidate' => 'MARKET_VALUE_REGULAR_000001',
        'review_status' => 'SANDBOX_REVIEW_REQUIRED'];

    private const RELATED_PARTY_HYPOTHESES = ['ART5_PAR2_INFLUENCE', 'ART5_PAR3_I', 'ART5_PAR3_II', 'ART5_PAR3_III',
        'ART5_PAR3_IV', 'ART5_PAR3_V', 'ART5_PAR3_VI', 'ART5_PAR3_VII'];

    private const PERSONAL_RELATIONSHIPS = ['EMPLOYEE', 'PARTNER', 'SHAREHOLDER', 'ADMINISTRATOR', 'BOARD_MEMBER', 'RELATIVE', 'OWNER'];

    /** @param array<string, mixed> $codes */
    public static function enabled(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null)
            && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY);
    }

    public static function covers(mixed $basis): bool
    {
        return in_array($basis, self::POLICY['species'], true);
    }

    /** @return array<string, mixed> */
    public function resolve(FactBag $facts, string $consideration, string $market, string $regime): array
    {
        $basis = $facts->value('rtc.non_onerous_legal_basis');
        if ($market !== 'DOMESTIC' || $regime !== 'REGULAR') {
            return $this->stop('ACTION_REQUIRED', 'RTC_NON_ONEROUS_B28_OTHER_REGIME_NOT_MATERIALIZED', []);
        }
        if (in_array($facts->value('rtc.recipient_relationship'), self::PERSONAL_RELATIONSHIPS, true)) {
            // Art. 5, I, a/b is the specific route for these natural persons; II/IV must not bypass it.
            return $this->stop('INVALID', 'RTC_NON_ONEROUS_B28_PERSONAL_ROUTE_REQUIRED', []);
        }
        $missing = $facts->missing(['rtc.market_value_brl', 'rtc.market_value_evidence_id', 'rtc.market_value_method']);
        if ($basis === 'LC214_ART5_II') {
            if ($consideration !== 'NON_ONEROUS') {
                return $this->stop('INVALID', 'RTC_GIFT_BONUS_CONSIDERATION_CONFLICT', []);
            }
            $missing = [...$facts->missing(['rtc.bonus_in_document_without_later_event', 'rtc.gift_bonus_evidence_id']), ...$missing];
            if ($facts->value('rtc.bonus_in_document_without_later_event') === true) {
                return $this->stop('INVALID', 'RTC_BONUS_IN_DOCUMENT_IS_NOT_SEPARATE_OPERATION_410001', []);
            }
            $article = 'LC214 art. 5º, II e §1º; art. 12, §4º, I-II';
            $qualification = ['kind' => 'GIFT_OR_BONUS', 'evidence_id' => $facts->value('rtc.gift_bonus_evidence_id')];
        } else {
            if (! in_array($consideration, ['NON_ONEROUS', 'ONEROUS'], true)) {
                return $this->stop('INVALID', 'RTC_RELATED_PARTY_CONSIDERATION_CONFLICT', []);
            }
            $missing = [...$facts->missing(['rtc.related_party_hypothesis', 'rtc.related_party_evidence_id']), ...$missing];
            if ($missing === [] && ! in_array($facts->value('rtc.related_party_hypothesis'), self::RELATED_PARTY_HYPOTHESES, true)) {
                return $this->stop('INVALID', 'RTC_RELATED_PARTY_HYPOTHESIS_UNKNOWN', []);
            }
            $article = 'LC214 art. 5º, IV e §§2º-6º; art. 12, §4º, IV';
            $qualification = ['kind' => 'RELATED_PARTY', 'hypothesis' => $facts->value('rtc.related_party_hypothesis'),
                'evidence_id' => $facts->value('rtc.related_party_evidence_id')];
        }
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_NON_ONEROUS_B28_EVIDENCE_MISSING', array_values(array_unique($missing)));
        }
        if ($facts->value('rtc.market_value_method') !== self::POLICY['market_value_method']) {
            return $this->stop('INVALID', 'RTC_MARKET_VALUE_METHOD_NOT_ART12_PAR4', []);
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_NON_ONEROUS_B28_QUALIFIED', 'missing_facts' => [],
            'species' => $basis, 'source_article' => $article, 'qualification' => $qualification,
            'market_value_method' => self::POLICY['market_value_method'],
            'document_candidate' => self::POLICY['document_candidate'], 'review_status' => self::POLICY['review_status']];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $missing): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $missing];
    }
}
