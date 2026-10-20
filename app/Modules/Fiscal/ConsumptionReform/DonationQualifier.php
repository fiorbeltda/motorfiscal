<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class DonationQualifier
{
    /** @return array{status: string, reason: string, missing_facts: list<string>, treatment?: string, source_article?: string} */
    public function resolve(FactBag $facts): array
    {
        $missing = $facts->missing(['rtc.donation_no_counterbenefit', 'rtc.donation_counterbenefit_evidence_id',
            'rtc.donation_input_credit_eligible', 'rtc.donation_input_credit_evidence_id']);
        if ($missing !== []) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DONATION_LEGAL_FACTS_MISSING', 'missing_facts' => $missing];
        }
        if ($facts->value('rtc.donation_no_counterbenefit') !== true) {
            return ['status' => 'INVALID', 'reason' => 'RTC_DONATION_COUNTERBENEFIT_CONFLICT', 'missing_facts' => []];
        }
        if (DecimalValue::fromString((string) $facts->value('rtc.gross_amount'))->compareTo(DecimalValue::fromString('0')) !== 0) {
            return ['status' => 'INVALID', 'reason' => 'RTC_DONATION_CONSIDERATION_AMOUNT_CONFLICT', 'missing_facts' => []];
        }
        if ($facts->value('rtc.donation_input_credit_eligible') === false) {
            return ['status' => 'RESOLVED', 'reason' => 'RTC_DONATION_NON_INCIDENCE_QUALIFIED',
                'missing_facts' => [], 'treatment' => 'NON_INCIDENCE', 'source_article' => 'LC214 art. 6º, VIII e §2º'];
        }
        $missing = $facts->missing(['rtc.donation_credit_option']);
        if ($missing !== []) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DONATION_CREDIT_OPTION_MISSING', 'missing_facts' => $missing];
        }
        if ($facts->value('rtc.donation_credit_option') === 'ANNUL_CREDITS') {
            $missing = $facts->missing(['rtc.donation_credit_annulment_evidence_id']);
            if ($missing !== []) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DONATION_CREDIT_ANNULMENT_UNPROVEN', 'missing_facts' => $missing];
            }

            return ['status' => 'RESOLVED', 'reason' => 'RTC_DONATION_CREDITS_ANNULLED',
                'missing_facts' => [], 'treatment' => 'CREDITS_ANNULLED', 'source_article' => 'LC214 art. 6º, §2º, II'];
        }
        $missing = $facts->missing(['rtc.market_value_brl', 'rtc.market_value_evidence_id']);
        if ($missing !== []) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DONATION_MARKET_VALUE_UNPROVEN', 'missing_facts' => $missing];
        }
        if (DecimalValue::fromString((string) $facts->value('rtc.market_value_brl'))->compareTo(DecimalValue::fromString('0')) <= 0) {
            return ['status' => 'INVALID', 'reason' => 'RTC_DONATION_MARKET_VALUE_INVALID', 'missing_facts' => []];
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_DONATION_MARKET_TAXATION_QUALIFIED',
            'missing_facts' => [], 'treatment' => 'MARKET_VALUE_TAXATION', 'source_article' => 'LC214 art. 6º, §2º, I'];
    }
}
