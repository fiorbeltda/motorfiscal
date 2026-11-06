<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/**
 * B35 (4B.4.10): regime específico de hotelaria, parques de diversão e parques temáticos (LC 214/2025, arts. 277-283).
 *
 * Everything below is literal law (Planalto compiled text observed 03/11/2026, SHA256 0a0d190b...):
 * - art. 278: hotelaria = alojamento temporário (and other services included in the lodging price) in units exclusive to
 *   guests by an establishment for that purpose, or in a furnished residential property;
 * - art. 279: parque de diversão = permanent or itinerant establishment whose essential activity is attractions enjoyed
 *   in person; parque temático = amusement park inspired by a historical, cultural, ethnographic, ludic or environmental theme;
 * - art. 280: base = valor da operação; food and drinks follow the bars-and-restaurants regime (par. único, LC 227/2026),
 *   which is documented outside NFS-e, so they must be priced apart and are never folded into this base;
 * - art. 281: IBS and CBS rates reduced by 40%; official cClassTrib 200048 (CST 200, indNFSe = 1);
 * - art. 282: supplier keeps credits under arts. 47-56; art. 283: the acquirer may not appropriate credits.
 */
final readonly class HospitalityRegimeB35
{
    public const KEY = 'hospitality_regime_b35';

    public const TREATMENT = 'SPECIFIC_REGIME';

    public const SPECIFIC_REGIME = 'HOSPITALITY';

    public const SOURCE_FILE = 'lc214-planalto-observed-20261103.htm';

    public const SOURCE_SHA256 = '0a0d190bc39689f50b199646b51dd84d01f6d7392c179287b46909c3d4f4673c';

    public const ACTIVITIES = ['HOTEL', 'AMUSEMENT_PARK', 'THEME_PARK'];

    public const HOTEL_UNITS = ['EXCLUSIVE_GUEST_UNITS_ESTABLISHMENT', 'FURNISHED_RESIDENTIAL_PROPERTY'];

    public const THEMES = ['HISTORICAL', 'CULTURAL', 'ETHNOGRAPHIC', 'LUDIC', 'ENVIRONMENTAL'];

    public const POLICY = ['version' => 'B35', 'strategy' => 'LC214_ARTS_277_283', 'rule_id' => 'RTC.TREATMENT.LC214.ART277-283.B35-1',
        'source_file' => self::SOURCE_FILE, 'source_hash' => self::SOURCE_SHA256, 'valid_from' => '2026-01-01',
        'cst' => '200', 'cclasstrib' => '200048', 'rate_reduction_percent' => '40.00', 'acquirer_credit' => 'PROHIBITED_ART283',
        'supplier_credit' => 'ARTS_47_56', 'food_and_beverage' => 'BARS_RESTAURANTS_REGIME_OUTSIDE_NFSE', 'status' => 'ACTIVE'];

    /** @param array<string, mixed> $codes */
    public static function valid(array $codes, DateTimeImmutable $knownAt): bool
    {
        $spec = $codes[self::KEY] ?? null;
        if (! is_array($spec) || ! is_string($spec['published_at'] ?? null)) {
            return false;
        }
        $policy = $spec;
        unset($policy['published_at']);

        return CanonicalJson::hash($policy) === CanonicalJson::hash(self::POLICY)
            && $knownAt >= new DateTimeImmutable($spec['published_at']);
    }

    /**
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $codes): array
    {
        if (! self::valid($codes, $knownAt) || $on->value() < self::POLICY['valid_from']) {
            return $this->stop('ACTION_REQUIRED', 'RTC_HOSPITALITY_RULE_NOT_AVAILABLE');
        }
        $missing = $facts->missing(['rtc.specific_regime', 'rtc.treatment_rule_id', 'rtc.treatment_evidence_id', 'rtc.hospitality_activity',
            'rtc.hospitality_scope_evidence_id', 'rtc.hospitality_food_beverage_in_price']);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_HOSPITALITY_PROOF_MISSING', $missing);
        }
        if ($facts->value('rtc.specific_regime') !== self::SPECIFIC_REGIME || $facts->value('rtc.treatment_rule_id') !== self::POLICY['rule_id']) {
            return $this->stop('INVALID', 'RTC_HOSPITALITY_RULE_CONFLICT');
        }
        $activity = $facts->value('rtc.hospitality_activity');
        if (! in_array($activity, self::ACTIVITIES, true)) {
            return $this->stop('INVALID', 'RTC_HOSPITALITY_ACTIVITY_INVALID');
        }
        if ($facts->value('rtc.hospitality_food_beverage_in_price') !== false) {
            // Art. 280, par. único: food and drinks follow the bars-and-restaurants regime; never fold them into this base.
            return $this->stop('ACTION_REQUIRED', 'RTC_HOSPITALITY_FOOD_FOLLOWS_RESTAURANT_REGIME', ['rtc.hospitality_food_beverage_in_price']);
        }
        $qualification = [];
        if ($activity === 'HOTEL') {
            $missing = $facts->missing(['rtc.hotel_unit_kind', 'rtc.hotel_temporary_lodging']);
            if ($missing !== []) {
                return $this->stop('ACTION_REQUIRED', 'RTC_HOSPITALITY_PROOF_MISSING', $missing);
            }
            if (! in_array($facts->value('rtc.hotel_unit_kind'), self::HOTEL_UNITS, true)
                || $facts->value('rtc.hotel_temporary_lodging') !== true) {
                return $this->stop('INVALID', 'RTC_HOTEL_DEFINITION_NOT_MET');
            }
            $qualification = ['article' => 'LC214 art. 278', 'unit_kind' => $facts->value('rtc.hotel_unit_kind')];
        } else {
            $missing = $facts->missing($activity === 'THEME_PARK'
                ? ['rtc.park_presential_attractions_essential', 'rtc.theme_park_theme']
                : ['rtc.park_presential_attractions_essential']);
            if ($missing !== []) {
                return $this->stop('ACTION_REQUIRED', 'RTC_HOSPITALITY_PROOF_MISSING', $missing);
            }
            if ($facts->value('rtc.park_presential_attractions_essential') !== true
                || ($activity === 'THEME_PARK' && ! in_array($facts->value('rtc.theme_park_theme'), self::THEMES, true))) {
                return $this->stop('INVALID', 'RTC_PARK_DEFINITION_NOT_MET');
            }
            $qualification = ['article' => 'LC214 art. 279, '.($activity === 'THEME_PARK' ? 'II' : 'I'),
                'theme' => $activity === 'THEME_PARK' ? $facts->value('rtc.theme_park_theme') : null];
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_HOSPITALITY_REGIME_ELIGIBLE', 'missing_facts' => [],
            'rule_id' => self::POLICY['rule_id'], 'regime' => 'HOSPITALITY_SPECIFIC', 'activity' => $activity,
            'qualification' => $qualification, 'source_article' => 'LC214 arts. 277-283',
            'source_hash' => self::SOURCE_SHA256, 'rate_reduction_percent' => '40.00', 'cclasstrib' => '200048', 'cst' => '200',
            'base_rule' => 'LC214 art. 280: valor da operação', 'acquirer_credit' => 'PROHIBITED_ART283',
            'acquirer_credit_catalogue_entry' => 'HOTEL_AMUSEMENT_PARK', 'supplier_credit' => 'ARTS_47_56',
            'scope_evidence_id' => $facts->value('rtc.hospitality_scope_evidence_id'),
            'treatment_evidence_id' => $facts->value('rtc.treatment_evidence_id')];
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $missing = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $missing];
    }
}
