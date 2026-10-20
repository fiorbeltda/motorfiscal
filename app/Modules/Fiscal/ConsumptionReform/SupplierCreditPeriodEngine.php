<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SupplierCreditPeriodEngine
{
    /** @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_ART51_52_SUPPLIER_PERIOD' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ($rule['input_contract'] ?? null) !== 'SINGLE_PERIOD_ARRAY'
            || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])
            || ! isset($rule['valid_from'], $rule['published_at']) || $knownAt < new DateTimeImmutable($rule['published_at'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_RULE_NOT_AVAILABLE');
        }
        if (! $facts->has('rtc.supplier_credit_period')) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_MISSING');
        }
        $periods = $facts->value('rtc.supplier_credit_period');
        if (! is_array($periods) || ! array_is_list($periods) || count($periods) !== 1 || ! is_array($periods[0])) {
            return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_INVALID');
        }
        $period = $periods[0];
        if (! is_string($period['month'] ?? null) || ! preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $period['month'])) {
            return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_INVALID');
        }
        $start = $period['month'].'-01';
        $end = (new DateTimeImmutable($start))->format('Y-m-t');
        if ($start < $rule['valid_from'] || (isset($rule['valid_to']) && $end > $rule['valid_to'])) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_RULE_NOT_AVAILABLE');
        }
        if ($end > $on->value() || $end > $knownAt->format('Y-m-d')) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_NOT_CLOSED');
        }
        if (($period['supplier_id'] ?? null) !== $facts->value('rtc.supplier_id') || ($period['currency'] ?? null) !== 'BRL') {
            return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_OWNER_OR_CURRENCY_CONFLICT');
        }
        if ($facts->value('rtc.supplier_regime') !== 'REGULAR') {
            return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_REGIME_NOT_QUALIFIED');
        }
        foreach (['operations_ledger_evidence_id', 'credit_pool_evidence_id'] as $key) {
            if (! $this->proof($period[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_LEDGER_PROOF_MISSING');
            }
        }
        if (($period['all_operations_included'] ?? null) !== true || ($period['credit_pool_complete'] ?? null) !== true) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_LEDGER_INCOMPLETE');
        }
        if (! is_bool($period['annulment_already_recorded'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_PRIOR_POSTING_UNPROVEN');
        }
        if ($period['annulment_already_recorded']) {
            return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_ALREADY_ANNULLED');
        }
        foreach (['outputs', 'credit_pool', 'credit_adjustments'] as $key) {
            if (! is_array($period[$key] ?? null) || ! array_is_list($period[$key])) {
                return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_LEDGER_PROOF_MISSING');
            }
        }
        $zero = DecimalValue::fromString('0.00');
        $total = $zero;
        $numerators = ['IBS' => $zero, 'CBS' => $zero];
        $outputTrace = [];
        $seen = [];
        $seenDocuments = [];
        foreach ($period['outputs'] as $output) {
            if (! is_array($output) || ! $this->proof($output['id'] ?? null) || isset($seen[$output['id']])
                || ! $this->proof($output['document_id'] ?? null) || isset($seenDocuments[$output['document_id']])
                || ! $this->proof($output['eligibility_evidence_id'] ?? null)) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_OUTPUT_INVALID');
            }
            $seen[$output['id']] = true;
            $seenDocuments[$output['document_id']] = true;
            try {
                $date = LocalDate::fromString((string) ($output['date'] ?? ''))->value();
                $value = $this->money($output['value_brl'] ?? null);
            } catch (InvalidArgumentException) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_OUTPUT_INVALID');
            }
            if ($date < $start || $date > $end) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_OUTPUT_DATE_CONFLICT');
            }
            $total = $total->plus($value);
            $policies = [];
            foreach (['IBS', 'CBS'] as $tax) {
                $treatment = $output[strtolower($tax).'_treatment'] ?? null;
                if (! is_string($treatment) || ! isset($rule['output_policies'][$treatment])) {
                    return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_OUTPUT_POLICY_NOT_PROVEN:'.$tax);
                }
                $policy = $rule['output_policies'][$treatment];
                if (($policy['action'] ?? null) === 'ANNUL_PROPORTIONALLY') {
                    $numerators[$tax] = $numerators[$tax]->plus($value);
                } elseif (($policy['action'] ?? null) !== 'PRESERVE') {
                    return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_POLICY_CORRUPT');
                }
                $policies[$tax] = ['treatment' => $treatment, ...$policy];
            }
            $outputTrace[] = [...$output, 'credit_policies' => $policies];
        }
        try {
            $declared = $this->money($period['total_output_value_brl'] ?? null);
        } catch (InvalidArgumentException) {
            return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_TOTAL_INVALID');
        }
        if ($declared->compareTo($total) !== 0) {
            return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_OUTPUT_TOTAL_CONFLICT');
        }
        if ($total->compareTo($zero) === 0) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_PERIOD_ZERO_TURNOVER_POLICY_REQUIRED');
        }
        $pool = [];
        $entryDates = [];
        $seenDocuments = [];
        $opening = ['IBS' => $zero, 'CBS' => $zero];
        foreach ($period['credit_pool'] as $entry) {
            if (! is_array($entry) || ! $this->proof($entry['id'] ?? null) || isset($pool[$entry['id']])
                || ! $this->proof($entry['document_id'] ?? null) || isset($seenDocuments[$entry['document_id']])
                || ! $this->proof($entry['appropriation_evidence_id'] ?? null)
                || ! $this->proof($entry['extinction_evidence_id'] ?? null) || ($entry['acquirer_id'] ?? null) !== $period['supplier_id']) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_POOL_ENTRY_INVALID');
            }
            if (($entry['credit_kind'] ?? null) !== 'ORDINARY_PREVIOUS_OPERATION') {
                return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_CREDIT_POOL_SPECIAL_RULE_NOT_PROVEN');
            }
            try {
                $date = LocalDate::fromString((string) ($entry['appropriation_date'] ?? ''))->value();
                $amounts = ['IBS' => $this->money($entry['ibs_amount'] ?? null), 'CBS' => $this->money($entry['cbs_amount'] ?? null)];
            } catch (InvalidArgumentException) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_POOL_ENTRY_INVALID');
            }
            if ($date > $end || $date < '2026-01-01') {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_APPROPRIATION_DATE_CONFLICT');
            }
            $expiry = (new DateTimeImmutable(substr($date, 0, 7).'-01'))->modify('+1 month')->modify('+5 years')->format('Y-m-d');
            if ($end >= $expiry) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_EXPIRED_POOL_ENTRY');
            }
            $pool[$entry['id']] = $amounts;
            $entryDates[$entry['id']] = $date;
            $seenDocuments[$entry['document_id']] = true;
            foreach ($amounts as $tax => $amount) {
                $opening[$tax] = $opening[$tax]->plus($amount);
            }
        }
        $seen = [];
        $adjusted = ['IBS' => $zero, 'CBS' => $zero];
        foreach ($period['credit_adjustments'] as $adjustment) {
            if (! is_array($adjustment) || ! $this->proof($adjustment['id'] ?? null) || isset($seen[$adjustment['id']])
                || ! $this->proof($adjustment['evidence_id'] ?? null) || ! is_string($adjustment['credit_entry_id'] ?? null)
                || ! isset($pool[$adjustment['credit_entry_id']]) || ! in_array($adjustment['tax'] ?? null, ['IBS', 'CBS'], true)
                || ! in_array($adjustment['kind'] ?? null, ['REVERSAL', 'DISALLOWANCE'], true)) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_ADJUSTMENT_INVALID');
            }
            $seen[$adjustment['id']] = true;
            try {
                $amount = $this->money($adjustment['amount'] ?? null);
                $date = LocalDate::fromString((string) ($adjustment['date'] ?? ''))->value();
            } catch (InvalidArgumentException) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_ADJUSTMENT_INVALID');
            }
            if ($date > $end || $date < $entryDates[$adjustment['credit_entry_id']]) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_ADJUSTMENT_DATE_CONFLICT');
            }
            $tax = $adjustment['tax'];
            $remaining = $pool[$adjustment['credit_entry_id']][$tax]->minus($amount);
            if ($remaining->isNegative()) {
                return $this->stop('INVALID', 'RTC_SUPPLIER_CREDIT_PERIOD_ADJUSTMENT_EXCEEDS_ENTRY');
            }
            $pool[$adjustment['credit_entry_id']][$tax] = $remaining;
            $adjusted[$tax] = $adjusted[$tax]->plus($amount);
        }
        $taxes = [];
        foreach (['IBS', 'CBS'] as $tax) {
            $net = $opening[$tax]->minus($adjusted[$tax]);
            // Divide the full monetary numerator once; a rounded displayed ratio never drives money.
            $annulled = $net->multipliedBy($numerators[$tax])->dividedBy($total, 2);
            $taxes[$tax] = ['opening_credit' => $opening[$tax]->value(), 'previous_adjustments' => $adjusted[$tax]->value(),
                'credit_pool' => $net->value(), 'annulment_numerator' => $numerators[$tax]->value(), 'total_output_value' => $total->value(),
                'annulment_ratio' => ['numerator' => $numerators[$tax]->value(), 'denominator' => $total->value()],
                'annulled_credit' => $annulled->value(), 'maintained_credit' => $net->minus($annulled)->value()];
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_SUPPLIER_CREDIT_PERIOD_CALCULATED', 'missing_facts' => [],
            'scope' => 'SUPPLIER_PERIOD_PREVIOUS_CREDITS_NO_POSTING', 'month' => $period['month'], 'supplier_id' => $period['supplier_id'],
            'source_hash' => $rule['source_hash'], 'source_article' => 'LC214 arts. 43; 47, §10; 51; 52 e 54',
            'rounding' => 'EXACT_MULTIPLICATION_THEN_SINGLE_HALF_UP_DIVISION_2', 'taxes' => $taxes,
            'outputs' => $outputTrace, 'credit_pool_evidence' => $period['credit_pool'], 'credit_adjustments' => $period['credit_adjustments'],
            'operations_ledger_evidence_id' => $period['operations_ledger_evidence_id'], 'credit_pool_evidence_id' => $period['credit_pool_evidence_id']];
    }

    private function proof(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function money(mixed $value): DecimalValue
    {
        if (! is_string($value) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('A nonnegative BRL amount with at most two decimals is required.');
        }

        return DecimalValue::fromString($value)->rounded(2);
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $status === 'ACTION_REQUIRED' ? ['rtc.supplier_credit_period'] : []];
    }
}
