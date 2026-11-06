<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/**
 * B29: consequences of LC 214 art. 22 (wording of LC 227/2026) after PlatformResponsibilityEngine qualified the
 * platform: §3 registration waiver, §7 exemption from differences, §10 debt rules, §§12-13 options with the
 * supplier's consent, §14 30-day cure and §15 reference rates. Declarations are qualified inputs; no official
 * notice, consent or payment is authenticated, and reference rates are never inferred.
 */
final readonly class PlatformConsequencesB29
{
    public const KEY = 'platform_consequences_b29';

    public const POLICY = ['version' => 'B29', 'source' => 'LC214 art. 22 (LC 227/2026)',
        'options' => ['NONE', 'ISSUE_AND_PAY_ON_BEHALF', 'SUBSTITUTE'], 'reference_rates_inferred' => false];

    /** @param array<string, mixed> $codes */
    public static function enabled(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null)
            && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY);
    }

    /**
     * @param  array<string, mixed>  $platform  result of PlatformResponsibilityEngine
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, array $platform, bool $splitAvailable): array
    {
        $responsibility = $platform['responsibility'] ?? null;
        if (! in_array($responsibility, ['FOREIGN_SUPPLIER_SUBSTITUTIVE_SOLIDARY', 'DOMESTIC_SUPPLIER_SOLIDARY', 'NOT_TRIGGERED'], true)) {
            return ['status' => 'RESOLVED', 'reason' => 'RTC_PLATFORM_B29_NOT_APPLICABLE', 'missing_facts' => []];
        }
        if ($responsibility === 'FOREIGN_SUPPLIER_SUBSTITUTIVE_SOLIDARY') {
            $missing = $facts->missing(['rtc.supplier_exclusively_via_platform', 'rtc.platform_registered_regular']);
            if ($missing !== []) {
                return $this->stop('ACTION_REQUIRED', 'RTC_PLATFORM_FOREIGN_REGISTRATION_FACTS_MISSING', $missing);
            }

            return $this->resolved([
                'responsibility' => $responsibility, 'source_articles' => ['LC214 art. 22, I', 'art. 22, §3º', 'art. 22, §10, II'],
                'debt_calculation_rule' => 'REGULAR_REGIME_RULES',
                'supplier_registration_waived' => $facts->value('rtc.supplier_exclusively_via_platform') === true
                    && $facts->value('rtc.platform_registered_regular') === true,
                'option' => 'NOT_APPLICABLE', 'document_issuer' => 'PLATFORM_OR_SUPPLIER_PER_REGULATION',
                'differences_liability' => 'PLATFORM_SOLIDARY_WITH_ACQUIRER_OR_RECIPIENT',
            ]);
        }
        $missing = $facts->missing(['rtc.platform_option', 'rtc.supplier_registered_regime']);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_PLATFORM_OPTION_FACTS_MISSING', $missing);
        }
        $option = $facts->value('rtc.platform_option');
        if (! in_array($option, self::POLICY['options'], true)
            || ! in_array($facts->value('rtc.supplier_registered_regime'), ['REGULAR', 'FAVORED', 'NOT_REGISTERED'], true)) {
            return $this->stop('INVALID', 'RTC_PLATFORM_OPTION_FACTS_INVALID', []);
        }
        if ($option !== 'NONE' && $this->blank($facts->value('rtc.platform_option_supplier_consent_evidence_id'))) {
            return $this->stop('ACTION_REQUIRED', 'RTC_PLATFORM_OPTION_CONSENT_REQUIRED', ['rtc.platform_option_supplier_consent_evidence_id']);
        }
        // §10: registered domestic taxpayer (regular or favoured) keeps its own rules; otherwise regular-regime rules.
        $debtRule = in_array($facts->value('rtc.supplier_registered_regime'), ['REGULAR', 'FAVORED'], true)
            ? 'SUPPLIER_REGIME_RULES' : 'REGULAR_REGIME_RULES';
        $referenceRates = null;
        if ($option !== 'NONE' || in_array('SUPPLIER_DOCUMENT_MISSING_OR_UNDERVALUED', $platform['triggers'] ?? [], true)) {
            if (! is_bool($facts->value('rtc.platform_supplier_rules_available'))) {
                return $this->stop('ACTION_REQUIRED', 'RTC_PLATFORM_SUPPLIER_RULES_AVAILABILITY_UNPROVEN', ['rtc.platform_supplier_rules_available']);
            }
            if ($facts->value('rtc.platform_supplier_rules_available') === false) {
                // §15 allows reference rates; they are never inferred and must come from a published act.
                return $this->stop('ACTION_REQUIRED', 'RTC_PLATFORM_REFERENCE_RATES_NOT_PUBLISHED', []);
            }
            $referenceRates = 'NOT_USED_SUPPLIER_RULES_AVAILABLE';
        }
        $cure = null;
        if (in_array('SUPPLIER_DOCUMENT_MISSING_OR_UNDERVALUED', $platform['triggers'] ?? [], true)) {
            if (! is_bool($facts->value('rtc.platform_cure_within_30_days'))) {
                return $this->stop('ACTION_REQUIRED', 'RTC_PLATFORM_CURE_FACTS_MISSING', ['rtc.platform_cure_within_30_days']);
            }
            if ($facts->value('rtc.platform_cure_within_30_days') === true
                && ($this->blank($facts->value('rtc.platform_cure_document_id')) || $this->blank($facts->value('rtc.platform_cure_payment_id')))) {
                return $this->stop('ACTION_REQUIRED', 'RTC_PLATFORM_CURE_EVIDENCE_MISSING', ['rtc.platform_cure_document_id', 'rtc.platform_cure_payment_id']);
            }
            $cure = $facts->value('rtc.platform_cure_within_30_days') === true
                ? 'ACCRUALS_AND_PENALTY_ONLY_FROM_SUPPLIER' : 'NOT_CURED';
        }
        $splitInfo = $facts->value('rtc.platform_split_information_provided') === true;
        $reported = $facts->value('rtc.platform_reported_operation') === true;
        $exempt = $splitAvailable && $splitInfo && $reported;
        $differences = match (true) {
            $exempt => 'SUPPLIER_ONLY_PLATFORM_EXEMPT_ART22_PAR7',
            $option !== 'NONE' => 'SUPPLIER_KEEPS_DIFFERENCES',
            $responsibility === 'DOMESTIC_SUPPLIER_SOLIDARY' => 'PLATFORM_SOLIDARY_WITH_SUPPLIER',
            default => 'SUPPLIER',
        };

        return $this->resolved([
            'responsibility' => $option === 'SUBSTITUTE' ? 'PLATFORM_SUBSTITUTE_BY_OPTION' : $responsibility,
            'source_articles' => array_values(array_filter(['LC214 art. 22, II', 'art. 22, §10, '.($debtRule === 'SUPPLIER_REGIME_RULES' ? 'I' : 'II'),
                $option === 'ISSUE_AND_PAY_ON_BEHALF' ? 'art. 22, §12' : null, $option === 'SUBSTITUTE' ? 'art. 22, §13' : null,
                $cure !== null ? 'art. 22, §14' : null, 'art. 22, §7'])),
            'debt_calculation_rule' => $debtRule, 'option' => $option,
            'option_consent_evidence_id' => $option === 'NONE' ? null : $facts->value('rtc.platform_option_supplier_consent_evidence_id'),
            'document_issuer' => $option === 'NONE' ? 'SUPPLIER' : 'PLATFORM_ON_BEHALF_OF_SUPPLIER',
            'payer' => $option === 'NONE' ? 'SUPPLIER' : 'PLATFORM',
            'differences_liability' => $differences, 'split_available' => $splitAvailable,
            'split_information_provided' => $splitInfo, 'late_document_cure' => $cure, 'reference_rates' => $referenceRates,
        ]);
    }

    private function blank(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '' || trim($value) !== $value;
    }

    /** @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function resolved(array $body): array
    {
        return ['status' => 'RESOLVED', 'reason' => 'RTC_PLATFORM_B29_CONSEQUENCES_QUALIFIED', 'missing_facts' => [],
            ...$body, 'authenticated' => false];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $missing): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $missing];
    }
}
