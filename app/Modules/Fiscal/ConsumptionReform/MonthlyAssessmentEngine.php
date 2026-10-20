<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MonthlyAssessmentEngine
{
    /** @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_ART42_45_53_MONTHLY' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! $this->hash($rule['source_hash'] ?? null) || ! isset($rule['published_at'], $rule['valid_from'])
            || $knownAt < new DateTimeImmutable($rule['published_at'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))) {
            return $this->stop('ACTION_REQUIRED', 'RULE_NOT_AVAILABLE');
        }
        $input = $facts->value('rtc.monthly_assessment');
        if ($input === null) {
            return $this->stop('ACTION_REQUIRED', 'MISSING');
        }
        if (! is_array($input) || ! array_is_list($input) || count($input) !== 1 || ! is_array($input[0])) {
            return $this->stop('INVALID', 'INVALID');
        }
        $ledger = $input[0];
        if (! is_string($ledger['month'] ?? null) || ! preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $ledger['month'])) {
            return $this->stop('INVALID', 'PERIOD_INVALID');
        }
        $start = $ledger['month'].'-01';
        $end = (new DateTimeImmutable($start))->format('Y-m-t');
        if ($start < $rule['valid_from'] || (isset($rule['valid_to']) && $end > $rule['valid_to'])) {
            return $this->stop('ACTION_REQUIRED', 'RULE_NOT_AVAILABLE');
        }
        if ($end > $on->value() || $end > $knownAt->format('Y-m-d')) {
            return $this->stop('ACTION_REQUIRED', 'PERIOD_NOT_CLOSED');
        }
        if (($ledger['supplier_id'] ?? null) !== $facts->value('rtc.supplier_id') || ($ledger['currency'] ?? null) !== 'BRL') {
            return $this->stop('INVALID', 'OWNER_OR_CURRENCY_CONFLICT');
        }
        if ($facts->value('rtc.supplier_regime') !== 'REGULAR') {
            return $this->stop('ACTION_REQUIRED', 'REGIME_NOT_QUALIFIED');
        }
        foreach (['all_establishments_included', 'all_regimes_included', 'ledger_complete', 'credits_net_of_annulments', 'prior_debts_complete'] as $key) {
            if (($ledger[$key] ?? null) !== true) {
                return $this->stop('ACTION_REQUIRED', 'LEDGER_INCOMPLETE:'.$key);
            }
        }
        foreach (['ledger_evidence_id', 'establishment_registry_evidence_id', 'credit_qualification_evidence_id', 'prior_debts_evidence_id'] as $key) {
            if (! $this->proof($ledger[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'PROOF_MISSING:'.$key);
            }
        }
        $establishments = $ledger['establishments'] ?? null;
        if (! is_array($establishments) || ! array_is_list($establishments) || $establishments === []
            || count(array_unique($establishments, SORT_REGULAR)) !== count($establishments)
            || ! in_array($ledger['central_establishment_id'] ?? null, $establishments, true)) {
            return $this->stop('INVALID', 'ESTABLISHMENTS_INVALID');
        }
        foreach ($establishments as $establishment) {
            if (! $this->proof($establishment)) {
                return $this->stop('INVALID', 'ESTABLISHMENTS_INVALID');
            }
        }
        foreach (['debits', 'credits', 'carryforward', 'adjustments', 'extinctions', 'prior_debts'] as $key) {
            if (! is_array($ledger[$key] ?? null) || ! array_is_list($ledger[$key])) {
                return $this->stop('ACTION_REQUIRED', 'COMPONENT_MISSING:'.$key);
            }
        }
        $zero = DecimalValue::fromString('0.00');
        $totals = [];
        foreach (['IBS', 'CBS'] as $tax) {
            $totals[$tax] = array_fill_keys(['debits', 'credits', 'carryforward', 'positive_adjustments', 'negative_adjustments', 'extinctions', 'prior_debts'], $zero);
        }
        $debits = [];
        $seenDocuments = [];
        $seenExtinctionProofs = [];
        $seen = [];
        try {
            foreach (['debits', 'credits', 'carryforward', 'adjustments', 'extinctions', 'prior_debts'] as $component) {
                foreach ($ledger[$component] as $entry) {
                    if (! is_array($entry) || ! $this->proof($entry['id'] ?? null) || isset($seen[$entry['id']])
                        || ! in_array($entry['tax'] ?? null, ['IBS', 'CBS'], true)
                        || ! in_array($entry['establishment_id'] ?? null, $establishments, true)) {
                        return $this->stop('INVALID', 'ENTRY_INVALID:'.$component);
                    }
                    $seen[$entry['id']] = true;
                    if (($entry['qualification_status'] ?? null) !== 'RESOLVED'
                        || ! $this->proof($entry['qualification_evidence_id'] ?? null) || ! $this->hash($entry['qualification_hash'] ?? null)) {
                        return $this->stop('ACTION_REQUIRED', 'ENTRY_NOT_QUALIFIED:'.$component);
                    }
                    $date = LocalDate::fromString((string) ($entry['date'] ?? ''))->value();
                    $observed = LocalDate::fromString((string) ($entry['observed_on'] ?? ''))->value();
                    $cutoff = $component === 'extinctions' ? $on->value() : $end;
                    if ($observed > $knownAt->format('Y-m-d') || $observed < $date
                        || $date > $cutoff || $date < '2026-01-01'
                        || (in_array($component, ['debits', 'credits', 'adjustments'], true) && $date < $start)
                        || (in_array($component, ['carryforward', 'prior_debts'], true) && $date >= $start)) {
                        return $this->stop('INVALID', 'ENTRY_DATE_CONFLICT:'.$component);
                    }
                    $tax = $entry['tax'];
                    $amount = $this->money($entry['amount'] ?? null);
                    if (in_array($component, ['debits', 'credits', 'carryforward'], true)) {
                        if (! $this->proof($entry['document_id'] ?? null)) {
                            return $this->stop('INVALID', 'DOCUMENT_MISSING:'.$component);
                        }
                        $documentKey = ($component === 'debits' ? 'OUTPUT' : 'CREDIT').':'.$entry['document_id'].':'.$tax;
                        if (isset($seenDocuments[$documentKey])) {
                            return $this->stop('INVALID', 'DUPLICATE_DOCUMENT_COMPONENT');
                        }
                        $seenDocuments[$documentKey] = true;
                    }
                    if ($component === 'debits') {
                        if (! in_array($entry['due_status'] ?? null, ['DUE', 'NOT_DUE'], true)
                            || ($entry['due_status'] === 'NOT_DUE' && $amount->compareTo($zero) !== 0)) {
                            return $this->stop('INVALID', 'DEBIT_DUE_STATUS_CONFLICT');
                        }
                        $debits[$entry['id']] = ['tax' => $tax, 'date' => $date, 'amount' => $amount, 'extinguished' => $zero];
                    }
                    if (in_array($component, ['credits', 'carryforward'], true)) {
                        if (! in_array($entry['credit_kind'] ?? null, ['ORDINARY', 'PRESUMED'], true)
                            || ! $this->proof($entry['appropriation_evidence_id'] ?? null)) {
                            return $this->stop('ACTION_REQUIRED', 'CREDIT_KIND_NOT_QUALIFIED');
                        }
                        if ($entry['credit_kind'] === 'PRESUMED'
                            && (! $this->proof($entry['rule_id'] ?? null) || ! $this->hash($entry['rule_source_hash'] ?? null))) {
                            return $this->stop('ACTION_REQUIRED', 'PRESUMED_CREDIT_RULE_NOT_QUALIFIED');
                        }
                        if ($entry['credit_kind'] === 'ORDINARY' && ! $this->proof($entry['extinction_evidence_id'] ?? null)) {
                            return $this->stop('ACTION_REQUIRED', 'ORDINARY_CREDIT_EXTINCTION_UNPROVEN');
                        }
                        $appropriation = LocalDate::fromString((string) ($entry['appropriation_date'] ?? ''))->value();
                        if ($appropriation > $date || $appropriation < '2026-01-01'
                            || ($component === 'credits' && $appropriation < $start)) {
                            return $this->stop('INVALID', 'APPROPRIATION_DATE_CONFLICT');
                        }
                        $expiry = (new DateTimeImmutable(substr($appropriation, 0, 7).'-01'))->modify('+1 month')->modify('+5 years')->format('Y-m-d');
                        if ($end >= $expiry) {
                            return $this->stop('INVALID', 'CREDIT_EXPIRED');
                        }
                        if ($component === 'credits' && ($entry['appropriated'] ?? null) !== true) {
                            return $this->stop('ACTION_REQUIRED', 'CREDIT_NOT_APPROPRIATED');
                        }
                        if ($component === 'carryforward') {
                            // The three dispositions are distinct, previously reconciled buckets.
                            $amount = $amount->minus($this->money($entry['used_for_compensation'] ?? null))
                                ->minus($this->money($entry['refunded'] ?? null))->minus($this->money($entry['reserved_for_refund'] ?? null));
                            if ($amount->isNegative()) {
                                return $this->stop('INVALID', 'CARRYFORWARD_DISPOSITIONS_EXCEED_BALANCE');
                            }
                        }
                    }
                    if ($component === 'prior_debts') {
                        if (($entry['overdue_unextinguished_not_enrolled'] ?? null) !== true
                            || ! $this->proof($entry['legal_additions_evidence_id'] ?? null)) {
                            return $this->stop('ACTION_REQUIRED', 'PRIOR_DEBT_NOT_QUALIFIED');
                        }
                        $due = LocalDate::fromString((string) ($entry['due_date'] ?? ''))->value();
                        if ($due < $date || $due >= $on->value()) {
                            return $this->stop('INVALID', 'PRIOR_DEBT_DUE_DATE_CONFLICT');
                        }
                    }
                    if ($component === 'extinctions') {
                        if (! in_array($entry['method'] ?? null, ['SPLIT_PAYMENT', 'ACQUIRER_PAYMENT', 'RESPONSIBLE_PAYMENT'], true)) {
                            return $this->stop('INVALID', 'EXTINCTION_NOT_ART27_III_V');
                        }
                        $debitId = $entry['debit_id'] ?? null;
                        if (! is_string($debitId) || ! isset($debits[$debitId]) || $debits[$debitId]['tax'] !== $tax
                            || $date < $debits[$debitId]['date'] || ! $this->proof($entry['extinction_evidence_id'] ?? null)) {
                            return $this->stop('INVALID', 'EXTINCTION_LINK_CONFLICT');
                        }
                        $proofKey = $entry['extinction_evidence_id'].':'.$tax;
                        if (isset($seenExtinctionProofs[$proofKey])) {
                            return $this->stop('INVALID', 'DUPLICATE_EXTINCTION_PROOF');
                        }
                        $seenExtinctionProofs[$proofKey] = true;
                        $extinguished = $debits[$debitId]['extinguished']->plus($amount);
                        if ($extinguished->compareTo($debits[$debitId]['amount']) > 0) {
                            return $this->stop('INVALID', 'EXTINCTION_EXCEEDS_DEBIT');
                        }
                        $debits[$debitId]['extinguished'] = $extinguished;
                    }
                    if ($component === 'adjustments') {
                        if (! in_array($entry['direction'] ?? null, ['PLUS', 'MINUS'], true)
                            || ! $this->proof($entry['rule_id'] ?? null) || ! $this->hash($entry['rule_source_hash'] ?? null)) {
                            return $this->stop('ACTION_REQUIRED', 'ADJUSTMENT_RULE_NOT_QUALIFIED');
                        }
                    }
                    $target = match ($component) {
                        'debits' => 'debits',
                        'credits' => 'credits',
                        'carryforward' => 'carryforward',
                        'extinctions' => 'extinctions',
                        'prior_debts' => 'prior_debts',
                        'adjustments' => $entry['direction'] === 'PLUS' ? 'positive_adjustments' : 'negative_adjustments',
                    };
                    $totals[$tax][$target] = $totals[$tax][$target]->plus($amount);
                }
            }
        } catch (InvalidArgumentException) {
            return $this->stop('INVALID', 'AMOUNT_OR_DATE_INVALID');
        }
        $taxes = [];
        foreach ($totals as $tax => $values) {
            $credit = $values['credits']->plus($values['carryforward']);
            $priorAllocation = $credit->compareTo($values['prior_debts']) < 0 ? $credit : $values['prior_debts'];
            $available = $credit->minus($priorAllocation);
            $balance = $values['debits']->minus($available)->plus($values['positive_adjustments'])->minus($values['negative_adjustments']);
            $final = $balance->minus($values['extinctions']);
            $taxes[$tax] = array_map(static fn (DecimalValue $value): string => $value->value(), $values);
            $taxes[$tax] += ['credit_allocated_to_prior_debts' => $priorAllocation->value(),
                'prior_debt_remaining' => $values['prior_debts']->minus($priorAllocation)->value(),
                'credit_available_for_current_period' => $available->value(), 'balance_before_extinctions' => $balance->value(),
                'final_balance' => $final->value(), 'payable' => ($final->isNegative() ? $zero : $final)->value(),
                'recoverable' => ($final->isNegative() ? $zero->minus($final) : $zero)->value()];
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_MONTHLY_ASSESSMENT_CALCULATED', 'missing_facts' => [],
            'scope' => 'LOCAL_MONTHLY_CALCULATION_NO_FILING_OR_POSTING', 'supplier_id' => $ledger['supplier_id'],
            'month' => $ledger['month'], 'source_hash' => $rule['source_hash'], 'source_article' => 'LC214 arts.42-45,47,53-54',
            'central_establishment_id' => $ledger['central_establishment_id'], 'taxes' => $taxes,
            'ledger' => $ledger, 'payment_due_date' => null, 'filing_due_date' => null,
            'debt_confession' => false, 'filing_performed' => false, 'payment_performed' => false];
    }

    private function proof(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    private function money(mixed $value): DecimalValue
    {
        if (! is_string($value) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Nonnegative BRL amount required.');
        }

        return DecimalValue::fromString($value)->rounded(2);
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_MONTHLY_ASSESSMENT_'.$reason,
            'missing_facts' => $status === 'ACTION_REQUIRED' ? ['rtc.monthly_assessment'] : []];
    }
}
