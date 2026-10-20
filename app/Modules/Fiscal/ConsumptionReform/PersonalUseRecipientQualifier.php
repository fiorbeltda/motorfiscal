<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;

final readonly class PersonalUseRecipientQualifier
{
    private const DIRECT_ROLES = ['PARTNER', 'SHAREHOLDER', 'ADMINISTRATOR', 'BOARD_OR_COMMITTEE_MEMBER', 'EMPLOYEE'];

    private const FAMILY_RELATIONS = ['SPOUSE_OR_PARTNER', 'RELATIVE_FIRST_DEGREE',
        'RELATIVE_SECOND_DEGREE', 'RELATIVE_THIRD_DEGREE'];

    /** @return array{status: string, reason: string, missing_facts: list<string>, source_article?: string, relationship?: string, anchor_role?: string|null} */
    public function resolve(FactBag $facts, bool $allowAcquired = false): array
    {
        $basis = $facts->value('rtc.non_onerous_legal_basis');
        $relationship = $facts->value('rtc.recipient_relationship');
        if (! is_string($relationship)) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_PERSONAL_USE_RELATIONSHIP_MISSING',
                'missing_facts' => ['rtc.recipient_relationship']];
        }
        $acquired = $allowAcquired && in_array($basis, ['LC214_ART5_I_A_1', 'LC214_ART5_I_A_2',
            'LC214_ART5_I_A_3', 'LC214_ART5_I_A_4'], true);
        $direct = ($basis === 'LC214_ART5_I_B_1' && in_array($relationship, self::DIRECT_ROLES, true))
            || ($basis === 'LC214_ART5_I_A_1' && $allowAcquired && $relationship === 'SELF')
            || ($basis === 'LC214_ART5_I_A_2' && $allowAcquired
                && in_array($relationship, ['PARTNER', 'SHAREHOLDER', 'ADMINISTRATOR', 'BOARD_OR_COMMITTEE_MEMBER'], true))
            || ($basis === 'LC214_ART5_I_A_3' && $allowAcquired && $relationship === 'EMPLOYEE');
        $family = in_array($relationship, self::FAMILY_RELATIONS, true)
            && ($basis === 'LC214_ART5_I_B_2' || ($basis === 'LC214_ART5_I_A_4' && $allowAcquired));
        if (! $direct && ! $family) {
            $recognized = in_array($basis, ['LC214_ART5_I_B_1', 'LC214_ART5_I_B_2'], true) || $acquired;

            return ['status' => $recognized ? 'INVALID' : 'ACTION_REQUIRED',
                'reason' => $recognized
                    ? 'RTC_PERSONAL_USE_LEGAL_BASIS_RELATION_CONFLICT' : 'RTC_NON_ONEROUS_STRATEGY_NOT_MATERIALIZED',
                'missing_facts' => []];
        }
        $missingPerson = $facts->missing(['rtc.buyer_relation', 'customer.person_type']);
        if ($missingPerson !== []) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_PERSONAL_USE_NATURAL_PERSON_UNPROVEN',
                'missing_facts' => $missingPerson];
        }
        if ($facts->value('rtc.buyer_relation') !== 'B2C' || $facts->value('customer.person_type') !== 'PF') {
            return ['status' => 'INVALID', 'reason' => 'RTC_PERSONAL_USE_NATURAL_PERSON_REQUIRED', 'missing_facts' => []];
        }
        if ($basis === 'LC214_ART5_I_A_1'
            && ($facts->value('provider.person_type') !== 'INDIVIDUAL'
                || $facts->value('rtc.supplier_id') !== $facts->value('rtc.recipient_id'))) {
            return ['status' => 'INVALID', 'reason' => 'RTC_PERSONAL_USE_TAXPAYER_SELF_CONFLICT', 'missing_facts' => []];
        }
        $missing = $facts->missing(['rtc.recipient_relationship_evidence_id',
            'rtc.business_use_predominant', 'rtc.business_use_evidence_id',
            'rtc.market_value_brl', 'rtc.market_value_evidence_id']);
        if ($family) {
            array_push($missing, ...$facts->missing(['rtc.recipient_anchor_role', 'rtc.recipient_anchor_evidence_id']));
        }
        if ($missing !== []) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_NON_ONEROUS_EVIDENCE_MISSING',
                'missing_facts' => array_values(array_unique($missing))];
        }
        $anchorRole = $family ? $facts->value('rtc.recipient_anchor_role') : null;
        $allowedAnchors = $basis === 'LC214_ART5_I_A_4'
            ? array_merge(self::DIRECT_ROLES, ['SELF']) : self::DIRECT_ROLES;
        if ($family && ! in_array($anchorRole, $allowedAnchors, true)) {
            return ['status' => 'INVALID', 'reason' => 'RTC_PERSONAL_USE_ANCHOR_ROLE_CONFLICT', 'missing_facts' => []];
        }
        if ($family && $anchorRole === 'SELF' && $facts->value('provider.person_type') !== 'INDIVIDUAL') {
            return ['status' => 'INVALID', 'reason' => 'RTC_PERSONAL_USE_TAXPAYER_SELF_CONFLICT', 'missing_facts' => []];
        }
        if ($facts->value('rtc.business_use_predominant') === true) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_NON_ONEROUS_BUSINESS_USE_EXCEPTION', 'missing_facts' => []];
        }

        return ['status' => 'VALID', 'reason' => 'RTC_PERSONAL_USE_RECIPIENT_QUALIFIED', 'missing_facts' => [],
            'source_article' => $acquired ? 'LC214 art. 5º, I, a, §§8º-9º'
                : ($direct ? 'LC214 art. 5º, I, b, 1, §§8º-9º' : 'LC214 art. 5º, I, b, 2, §§8º-9º'),
            'relationship' => $relationship, 'anchor_role' => $anchorRole];
    }
}
