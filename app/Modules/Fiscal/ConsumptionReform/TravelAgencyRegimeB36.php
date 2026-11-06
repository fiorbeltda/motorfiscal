<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/**
 * B36 (4B.4.10): regime específico das agências de turismo (LC 214/2025, arts. 288-291), intermediation only.
 *
 * Literal law (Planalto compiled text observed 03/11/2026, SHA256 0a0d190b...):
 * - art. 289, I: base = valor da operação minus the amounts passed on to the intermediated suppliers, based on the
 *   document that supports the agency operation;
 * - art. 289, §1º: valor da operação = total charged to the user (all goods/services enjoyed through the agency, its
 *   margin and other additions); §2º: commissions and incentives paid by third parties also integrate the base;
 * - art. 289, II: same rate as hotelaria (40% reduction, art. 281); official cClassTrib 200051 (CST 200, indNFSe = 1);
 * - art. 290: the acquirer may appropriate credits on the intermediation; art. 291: the agency keeps credits on its own
 *   acquisitions, except on the amounts deducted from the base.
 */
final readonly class TravelAgencyRegimeB36
{
    public const KEY = 'travel_agency_regime_b36';

    public const TREATMENT = 'SPECIFIC_REGIME';

    public const SPECIFIC_REGIME = 'TRAVEL_AGENCY';

    public const FACT = 'rtc.travel_agency_settlement';

    public const CONTRACT = 'RTC-TRAVEL-AGENCY-B36-V1';

    public const POLICY = ['version' => 'B36', 'strategy' => 'LC214_ARTS_288_291', 'rule_id' => 'RTC.TREATMENT.LC214.ART288-291.B36-1',
        'contract' => self::CONTRACT, 'source_file' => HospitalityRegimeB35::SOURCE_FILE, 'source_hash' => HospitalityRegimeB35::SOURCE_SHA256,
        'valid_from' => '2026-01-01', 'cst' => '200', 'cclasstrib' => '200051', 'rate_reduction_percent' => '40.00',
        'acquirer_credit' => 'ALLOWED_ART290_ARTS_47_56', 'deducted_amounts_credit' => 'PROHIBITED_ART291', 'status' => 'ACTIVE'];

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
     * Qualifies the regime (rate side). The base is computed separately by settle(), once the engine knows the gross amount.
     *
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $codes): array
    {
        if (! self::valid($codes, $knownAt) || $on->value() < self::POLICY['valid_from']) {
            return $this->stop('ACTION_REQUIRED', 'RTC_TRAVEL_AGENCY_RULE_NOT_AVAILABLE');
        }
        $missing = $facts->missing(['rtc.specific_regime', 'rtc.treatment_rule_id', 'rtc.treatment_evidence_id',
            'rtc.travel_agency_intermediation', 'rtc.travel_agency_scope_evidence_id', self::FACT]);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_TRAVEL_AGENCY_PROOF_MISSING', $missing);
        }
        if ($facts->value('rtc.specific_regime') !== self::SPECIFIC_REGIME || $facts->value('rtc.treatment_rule_id') !== self::POLICY['rule_id']) {
            return $this->stop('INVALID', 'RTC_TRAVEL_AGENCY_RULE_CONFLICT');
        }
        if ($facts->value('rtc.travel_agency_intermediation') !== true) {
            // Art. 289 governs the intermediation of tourist services; own supplies by the agency are not settled here.
            return $this->stop('ACTION_REQUIRED', 'RTC_TRAVEL_AGENCY_ONLY_INTERMEDIATION_MATERIALIZED', ['rtc.travel_agency_intermediation']);
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_TRAVEL_AGENCY_REGIME_ELIGIBLE', 'missing_facts' => [],
            'rule_id' => self::POLICY['rule_id'], 'regime' => 'TRAVEL_AGENCY_SPECIFIC', 'source_article' => 'LC214 arts. 288-291',
            'source_hash' => self::POLICY['source_hash'], 'rate_reduction_percent' => '40.00', 'cclasstrib' => '200051', 'cst' => '200',
            'rate_rule' => 'LC214 art. 289, II (mesma alíquota da hotelaria, art. 281)',
            'acquirer_credit' => self::POLICY['acquirer_credit'], 'deducted_amounts_credit' => self::POLICY['deducted_amounts_credit'],
            'scope_evidence_id' => $facts->value('rtc.travel_agency_scope_evidence_id'),
            'treatment_evidence_id' => $facts->value('rtc.treatment_evidence_id')];
    }

    /**
     * Art. 289, I and §§1º-2º: base = total charged to the user + third-party commissions/incentives - documented pass-through.
     *
     * @return array<string, mixed>
     */
    public function settle(FactBag $facts, string $gross): array
    {
        $records = $facts->value(self::FACT);
        if (! is_array($records) || ! array_is_list($records) || count($records) !== 1 || ! is_array($records[0])) {
            return $this->stop('ACTION_REQUIRED', 'RTC_TRAVEL_AGENCY_SETTLEMENT_REQUIRED', [self::FACT]);
        }
        $record = $records[0];
        if (($record['contract'] ?? null) !== self::CONTRACT || ($record['currency'] ?? null) !== 'BRL'
            || ($record['document_id'] ?? null) !== $facts->value('rtc.document_id')
            || ! $this->identifier($record['agency_operation_document_id'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'RTC_TRAVEL_AGENCY_SETTLEMENT_BINDING_CONFLICT', [self::FACT]);
        }
        $total = $this->money($record['total_charged_to_user'] ?? null);
        if ($total === null || DecimalValue::fromString($total)->compareTo(DecimalValue::fromString($gross)) !== 0) {
            // Art. 289, §1º: the operation value is the total charged to the user, which must be the declared gross amount.
            return $this->stop('ACTION_REQUIRED', 'RTC_TRAVEL_AGENCY_TOTAL_CHARGED_CONFLICT', [self::FACT]);
        }
        $base = DecimalValue::fromString($total);
        $lines = [];
        $ids = [];
        foreach (['third_party_commissions' => 'PLUS', 'supplier_pass_through' => 'MINUS'] as $key => $effect) {
            $items = $record[$key] ?? null;
            if (! is_array($items) || ! array_is_list($items)) {
                return $this->stop('ACTION_REQUIRED', 'RTC_TRAVEL_AGENCY_SETTLEMENT_LIST_REQUIRED:'.$key, [self::FACT]);
            }
            foreach ($items as $item) {
                $amount = is_array($item) ? $this->money($item['amount'] ?? null) : null;
                $required = $effect === 'PLUS' ? ['id', 'payer_id', 'evidence_id'] : ['id', 'supplier_id', 'supporting_document_id', 'evidence_id'];
                foreach ($required as $field) {
                    if (! is_array($item) || ! $this->identifier($item[$field] ?? null)) {
                        return $this->stop('ACTION_REQUIRED', 'RTC_TRAVEL_AGENCY_LINE_PROOF_MISSING:'.$key, [self::FACT]);
                    }
                }
                if ($amount === null || isset($ids[$item['id']])) {
                    return $this->stop('ACTION_REQUIRED', 'RTC_TRAVEL_AGENCY_LINE_INVALID:'.$key, [self::FACT]);
                }
                $ids[$item['id']] = true;
                $base = $effect === 'PLUS' ? $base->plus(DecimalValue::fromString($amount)) : $base->minus(DecimalValue::fromString($amount));
                $lines[] = ['id' => $item['id'], 'effect' => $effect, 'amount' => $amount,
                    'source_article' => $effect === 'PLUS' ? 'LC214 art. 289, §2º' : 'LC214 art. 289, I',
                    'credit_by_agency' => $effect === 'MINUS' ? 'PROHIBITED_ART291' : null];
            }
        }
        if ($base->compareTo(DecimalValue::fromString('0.00')) < 0) {
            return $this->stop('INVALID', 'RTC_TRAVEL_AGENCY_NEGATIVE_BASE', [self::FACT]);
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_TRAVEL_AGENCY_BASE_SETTLED', 'missing_facts' => [],
            'total_charged_to_user' => $total, 'lines' => $lines, 'taxable_base' => $base->rounded(2)->value(),
            'agency_operation_document_id' => $record['agency_operation_document_id']];
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
