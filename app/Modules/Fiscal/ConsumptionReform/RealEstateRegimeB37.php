<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/**
 * B37 (4B.4.10): regime específico de bens imóveis (LC 214/2025, arts. 251-265) for the operations documented by NFS-e:
 * locação, cessão onerosa e arrendamento (and the art. 252, §1º equivalents) and administração/intermediação.
 *
 * Literal law (Planalto compiled text observed 03/11/2026, SHA256 0a0d190b...):
 * - art. 251, §1º: individuals are regular contributors only above the statutory thresholds (proved by the caller);
 * - art. 252, §1º: servidão, cessão de uso ou de espaço, permissão de uso e direito de passagem follow the lease rules;
 * - art. 253: residential lease up to 90 uninterrupted days follows the hotelaria rules (B35), not this regime;
 * - art. 254, III/IV: taxable event at payment; art. 11, II: destination is the property location;
 * - art. 255, II/IV and §2º: base = lease value, excluding taxes/emoluments on the property and condominium expenses;
 * - art. 260 (LC 227/2026): residential lease social reducer per property per month, IPCA-updated; the updated value is
 *   an input with its source, never computed here;
 * - art. 261: rates reduced by 50% (cClassTrib 200046); par. único: lease reduced by 70% (cClassTrib 200027).
 * Sales (redutor de ajuste, art. 257-259), incorporação and construção civil stay outside this class.
 */
final readonly class RealEstateRegimeB37
{
    public const KEY = 'real_estate_regime_b37';

    public const TREATMENT = 'SPECIFIC_REGIME';

    public const SPECIFIC_REGIME = 'REAL_ESTATE';

    public const FACT = 'rtc.real_estate_settlement';

    public const CONTRACT = 'RTC-REAL-ESTATE-LEASE-B37-V1';

    public const LEASE_FORMS = ['LEASE', 'ONEROUS_ASSIGNMENT', 'ARRENDAMENTO', 'SERVITUDE', 'USE_OR_SPACE_ASSIGNMENT', 'USE_PERMIT', 'RIGHT_OF_WAY'];

    public const POLICY = ['version' => 'B37', 'strategy' => 'LC214_ARTS_251_265', 'rule_id' => 'RTC.TREATMENT.LC214.ART251-265.B37-1',
        'contract' => self::CONTRACT, 'source_file' => HospitalityRegimeB35::SOURCE_FILE, 'source_hash' => HospitalityRegimeB35::SOURCE_SHA256,
        'valid_from' => '2026-01-01', 'cst' => '200',
        'activities' => ['LEASE' => ['cclasstrib' => '200027', 'rate_reduction_percent' => '70.00', 'article' => 'LC214 art. 261, parágrafo único'],
            'ADMINISTRATION' => ['cclasstrib' => '200046', 'rate_reduction_percent' => '50.00', 'article' => 'LC214 art. 261'],
            'INTERMEDIATION' => ['cclasstrib' => '200046', 'rate_reduction_percent' => '50.00', 'article' => 'LC214 art. 261']],
        'social_reducer' => 'LC214 art. 260: input value with IPCA source; never estimated', 'status' => 'ACTIVE'];

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

    /** @param array<string, mixed> $codes */
    public static function claims(array $codes, FactBag $facts): bool
    {
        return array_key_exists(self::KEY, $codes) && $facts->value('rtc.tax_treatment') === self::TREATMENT
            && $facts->value('rtc.specific_regime') === self::SPECIFIC_REGIME;
    }

    /**
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $codes): array
    {
        if (! self::valid($codes, $knownAt) || $on->value() < self::POLICY['valid_from']) {
            return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_RULE_NOT_AVAILABLE');
        }
        $missing = $facts->missing(['rtc.treatment_rule_id', 'rtc.treatment_evidence_id', 'rtc.real_estate_activity',
            'rtc.real_estate_contributor_basis', 'rtc.real_estate_contributor_evidence_id', 'rtc.property_registry_id',
            'rtc.property_state', 'rtc.property_municipality_ibge', 'rtc.real_estate_payment_date']);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_PROOF_MISSING', $missing);
        }
        if ($facts->value('rtc.treatment_rule_id') !== self::POLICY['rule_id']) {
            return $this->stop('INVALID', 'RTC_REAL_ESTATE_RULE_CONFLICT');
        }
        $activity = $facts->value('rtc.real_estate_activity');
        if (! is_string($activity) || ! isset(self::POLICY['activities'][$activity])) {
            return $this->stop('INVALID', 'RTC_REAL_ESTATE_ACTIVITY_INVALID');
        }
        if ($facts->value('rtc.real_estate_contributor_basis') === 'INDIVIDUAL_NOT_CONTRIBUTOR') {
            return $this->stop('INVALID', 'RTC_REAL_ESTATE_INDIVIDUAL_NOT_REGULAR_CONTRIBUTOR');
        }
        if ($facts->value('rtc.real_estate_payment_date') !== $facts->value('rtc.operation_date')) {
            // Art. 254, III/IV: the taxable event of lease, administration and intermediation is the payment.
            return $this->stop('INVALID', 'RTC_REAL_ESTATE_TAXABLE_EVENT_IS_PAYMENT');
        }
        if ($facts->value('rtc.destination_basis') !== 'PROPERTY'
            || $facts->value('rtc.property_state') !== $facts->value('rtc.destination_state')
            || $facts->value('rtc.property_municipality_ibge') !== $facts->value('rtc.destination_municipality_ibge')) {
            return $this->stop('INVALID', 'RTC_REAL_ESTATE_DESTINATION_NOT_PROPERTY_LOCATION');
        }
        $leaseForm = null;
        if ($activity === 'LEASE') {
            $missing = $facts->missing(['rtc.real_estate_lease_form', 'rtc.property_use', 'rtc.lease_term_class', self::FACT]);
            if ($missing !== []) {
                return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_PROOF_MISSING', $missing);
            }
            $leaseForm = $facts->value('rtc.real_estate_lease_form');
            if (! in_array($leaseForm, self::LEASE_FORMS, true)) {
                return $this->stop('INVALID', 'RTC_REAL_ESTATE_LEASE_FORM_INVALID');
            }
            if ($facts->value('rtc.property_use') === 'RESIDENTIAL' && $facts->value('rtc.lease_term_class') === 'UP_TO_90_DAYS_UNINTERRUPTED') {
                return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_SHORT_RESIDENTIAL_LEASE_FOLLOWS_HOSPITALITY');
            }
        }
        $row = self::POLICY['activities'][$activity];

        return ['status' => 'RESOLVED', 'reason' => 'RTC_REAL_ESTATE_REGIME_ELIGIBLE', 'missing_facts' => [],
            'rule_id' => self::POLICY['rule_id'], 'regime' => 'REAL_ESTATE_SPECIFIC', 'activity' => $activity, 'lease_form' => $leaseForm,
            'source_article' => 'LC214 arts. 251-261; '.$row['article'], 'source_hash' => self::POLICY['source_hash'],
            'rate_reduction_percent' => $row['rate_reduction_percent'], 'cclasstrib' => $row['cclasstrib'], 'cst' => '200',
            'contributor_basis' => $facts->value('rtc.real_estate_contributor_basis'),
            'contributor_evidence_id' => $facts->value('rtc.real_estate_contributor_evidence_id'),
            'property_registry_id' => $facts->value('rtc.property_registry_id'), 'taxable_event' => 'PAYMENT_ART254',
            'treatment_evidence_id' => $facts->value('rtc.treatment_evidence_id')];
    }

    /**
     * Lease base (art. 255, II and §2º; art. 260). Administration and intermediation keep the operation value (art. 255, IV).
     *
     * @return array<string, mixed>
     */
    public function settle(FactBag $facts, string $gross): array
    {
        $records = $facts->value(self::FACT);
        if (! is_array($records) || ! array_is_list($records) || count($records) !== 1 || ! is_array($records[0])) {
            return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_SETTLEMENT_REQUIRED', [self::FACT]);
        }
        $record = $records[0];
        if (($record['contract'] ?? null) !== self::CONTRACT || ($record['currency'] ?? null) !== 'BRL'
            || ($record['document_id'] ?? null) !== $facts->value('rtc.document_id')
            || ($record['property_registry_id'] ?? null) !== $facts->value('rtc.property_registry_id')) {
            return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_SETTLEMENT_BINDING_CONFLICT', [self::FACT]);
        }
        $charged = $this->money($record['amount_charged'] ?? null);
        if ($charged === null || DecimalValue::fromString($charged)->compareTo(DecimalValue::fromString($gross)) !== 0) {
            return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_CHARGED_AMOUNT_CONFLICT', [self::FACT]);
        }
        $base = DecimalValue::fromString($charged);
        $lines = [];
        $ids = [];
        foreach (['property_taxes_and_fees' => 'LC214 art. 255, §2º, I', 'condominium_expenses' => 'LC214 art. 255, §2º, II'] as $key => $article) {
            $items = $record[$key] ?? null;
            if (! is_array($items) || ! array_is_list($items)) {
                return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_SETTLEMENT_LIST_REQUIRED:'.$key, [self::FACT]);
            }
            foreach ($items as $item) {
                $amount = is_array($item) ? $this->money($item['amount'] ?? null) : null;
                if ($amount === null || ! $this->identifier($item['id'] ?? null) || ! $this->identifier($item['evidence_id'] ?? null)
                    || isset($ids[$item['id']])) {
                    return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_LINE_INVALID:'.$key, [self::FACT]);
                }
                $ids[$item['id']] = true;
                $base = $base->minus(DecimalValue::fromString($amount));
                $lines[] = ['id' => $item['id'], 'effect' => 'MINUS', 'amount' => $amount, 'source_article' => $article];
            }
        }
        if ($base->compareTo(DecimalValue::fromString('0.00')) < 0) {
            return $this->stop('INVALID', 'RTC_REAL_ESTATE_NEGATIVE_BASE', [self::FACT]);
        }
        $social = $record['social_reducer'] ?? null;
        $socialApplied = null;
        if ($social !== null) {
            if ($facts->value('rtc.property_use') !== 'RESIDENTIAL') {
                return $this->stop('INVALID', 'RTC_REAL_ESTATE_SOCIAL_REDUCER_ONLY_RESIDENTIAL', [self::FACT]);
            }
            $monthly = is_array($social) ? $this->money($social['monthly_value_per_property'] ?? null) : null;
            $properties = is_array($social) ? ($social['properties'] ?? null) : null;
            $months = is_array($social) ? ($social['months'] ?? null) : null;
            if ($monthly === null || ! is_int($properties) || $properties < 1 || ! is_int($months) || $months < 1
                || ! $this->identifier($social['value_source_id'] ?? null)
                || ! is_string($social['value_source_hash'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $social['value_source_hash']) !== 1) {
                // Art. 260, par. único: the R$ 600,00 is IPCA-updated monthly; the updated value must come with its source.
                return $this->stop('ACTION_REQUIRED', 'RTC_REAL_ESTATE_SOCIAL_REDUCER_VALUE_SOURCE_REQUIRED', [self::FACT]);
            }
            $reducer = DecimalValue::fromString($monthly)->multipliedBy(DecimalValue::fromString((string) ($properties * $months)));
            $socialApplied = $reducer->compareTo($base) > 0 ? $base : $reducer;
            $base = $base->minus($socialApplied);
            $lines[] = ['id' => 'SOCIAL_REDUCER', 'effect' => 'MINUS', 'amount' => $socialApplied->rounded(2)->value(),
                'source_article' => 'LC214 art. 260', 'properties' => $properties, 'months' => $months,
                'monthly_value_per_property' => $monthly, 'value_source_id' => $social['value_source_id'],
                'capped_at_base' => $reducer->compareTo($socialApplied) !== 0];
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_REAL_ESTATE_LEASE_BASE_SETTLED', 'missing_facts' => [],
            'amount_charged' => $charged, 'lines' => $lines, 'taxable_base' => $base->rounded(2)->value()];
    }

    private function money(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1 ? $value : null;
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && $value !== '' && trim($value) === $value && preg_match('/[\x00-\x1f\x7f]/', $value) !== 1;
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
