<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Checks local analytical trial balance and links every financial entry to a qualified PGCC account. */
final readonly class DereTrialBalanceQualifier
{
    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $book
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function resolve(array $operation, array $book, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'DERE_ANALYTICAL_TRIAL_BALANCE_1_2_0' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! isset($rule['published_at']) || new DateTimeImmutable($rule['published_at']) > $knownAt
            || ! $this->hash($rule['source_hash'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'RULE_UNAVAILABLE');
        }
        if (($operation['status'] ?? null) !== 'RESOLVED' || ($operation['base']['status'] ?? null) !== 'RESOLVED'
            || ($book['supplier_id'] ?? null) !== ($operation['qualification']['supplier_id'] ?? null)
            || ($book['document_id'] ?? null) !== ($operation['qualification']['document_id'] ?? null)
            || ($book['period_end'] ?? null) !== ($operation['base']['period_end'] ?? null)) {
            return $this->stop('INVALID', 'SCOPE_CONFLICT');
        }
        foreach (['analytical_book_complete', 'pgcc_complete', 'pgcc_account_mapping_qualified', 'opening_balances_qualified'] as $flag) {
            if (($book[$flag] ?? null) !== true) {
                return $this->stop('ACTION_REQUIRED', 'QUALIFICATION_MISSING:'.$flag);
            }
        }
        if (! $this->hash($book['book_source_hash'] ?? null) || ! $this->hash($book['pgcc_source_hash'] ?? null)
            || ! is_string($book['book_evidence_id'] ?? null) || $book['book_evidence_id'] === ''
            || ! is_string($book['pgcc_evidence_id'] ?? null) || $book['pgcc_evidence_id'] === ''
            || ! is_bool($book['first_reporting_period'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'BOOK_OR_PGCC_EVIDENCE_UNPROVEN');
        }
        if (($operation['base']['art188_reversal'] ?? '0.00') !== '0.00') {
            return $this->stop('ACTION_REQUIRED', 'COOPERATIVE_REVERSAL_ALLOCATION_REQUIRED');
        }
        $accounts = $book['accounts'] ?? null;
        $plan = $book['pgcc_accounts'] ?? null;
        $entries = $operation['base']['entries'] ?? null;
        if (! is_array($accounts) || ! array_is_list($accounts) || $accounts === [] || count($accounts) > 90000
            || ! is_array($plan) || ! array_is_list($plan) || ! is_array($entries) || ! array_is_list($entries)) {
            return $this->stop('INVALID', 'BOOK_OR_PGCC_ENTRIES_INVALID');
        }
        try {
            $end = LocalDate::fromString((string) $book['period_end'])->value();
            $month = substr($end, 0, 7);
            if ($end !== (new DateTimeImmutable($end))->format('Y-m-t')
                || LocalDate::fromString((string) ($book['observed_on'] ?? ''))->value() > $knownAt->format('Y-m-d')
                || $end > $knownAt->format('Y-m-d')) {
                return $this->stop('INVALID', 'MONTH_END_OR_OBSERVATION_CONFLICT');
            }
            $frequency = $book['closing_frequency'] ?? null;
            if (! in_array($frequency, ['A', 'S', 'Q', 'T', 'B', 'M'], true)) {
                return $this->stop('INVALID', 'CLOSING_FREQUENCY_INVALID');
            }
            $previousMonth = (new DateTimeImmutable($month.'-01'))->modify('-1 month')->format('Y-m');
            $pgcc = [];
            foreach ($plan as $row) {
                if (! is_array($row) || ! is_string($row['cCta'] ?? null) || $row['cCta'] === '' || isset($pgcc[$row['cCta']])
                    || ($row['indCta'] ?? null) !== 'A' || ! in_array($row['codNat'] ?? null, ['1', '2', '3', '4', '5'], true)
                    || ! isset($row['valid_from']) || LocalDate::fromString($row['valid_from'])->value() > $end
                    || (isset($row['valid_to']) && LocalDate::fromString($row['valid_to'])->value() < $end)) {
                    return $this->stop('INVALID', 'PGCC_ACCOUNT_DUPLICATE_SYNTHETIC_OR_NOT_EFFECTIVE');
                }
                $pgcc[$row['cCta']] = $row;
            }
            $financial = [];
            foreach ($entries as $entry) {
                if (! is_array($entry) || ! is_string($entry['id'] ?? null) || isset($financial[$entry['id']])
                    || ! is_string($entry['computed_amount'] ?? null)) {
                    return $this->stop('INVALID', 'FINANCIAL_ENTRIES_INVALID');
                }
                if (($entry['fiscal_origin'] ?? null) === 'D2101') {
                    if (($rule['d2101_origin_allowed'] ?? false) !== true || ($entry['kind'] ?? null) !== 'PUBLIC_OFFER_DEBT_YIELD'
                        || ($entry['book_apur_amount'] ?? null) !== '0.00' || ($entry['d2101_binding']['status'] ?? null) !== 'RESOLVED'
                        || ($entry['d2101_binding']['taxable_amount'] ?? null) !== $entry['computed_amount']) {
                        return $this->stop('ACTION_REQUIRED', 'D2101_FISCAL_ORIGIN_UNPROVEN');
                    }
                    $financial[$entry['id']] = DecimalValue::fromString('0.00');
                } else {
                    $financial[$entry['id']] = DecimalValue::fromString($entry['computed_amount']);
                }
            }
            $used = [];
            $seenAccounts = [];
            $qualified = [];
            foreach ($accounts as $account) {
                if (! is_array($account) || ! is_string($account['cCta'] ?? null) || strlen($account['cCta']) > 53
                    || isset($seenAccounts[$account['cCta']]) || ! isset($pgcc[$account['cCta']])) {
                    return $this->stop('INVALID', 'ACCOUNT_DUPLICATE_OR_ABSENT_IN_PGCC');
                }
                $seenAccounts[$account['cCta']] = true;
                if (! in_array($account['natSaldoInic'] ?? null, ['D', 'C'], true)
                    || ! in_array($account['natSaldoFinal'] ?? null, ['D', 'C'], true)
                    || (isset($account['natVApur']) && ! in_array($account['natVApur'], ['D', 'C'], true))) {
                    return $this->stop('INVALID', 'ACCOUNT_NATURE_INVALID');
                }
                $opening = $this->money($account['vSaldoInic'] ?? null);
                $debit = $this->money($account['vMovDebt'] ?? null);
                $credit = $this->money($account['vMovCred'] ?? null);
                $adjustDebit = $this->money($account['vAjusteDebt'] ?? '0.00');
                $adjustCredit = $this->money($account['vAjusteCred'] ?? '0.00');
                $reportedFinal = $this->money($account['vSaldoFinal'] ?? null);
                $reportedApur = $this->money($account['vApur'] ?? null);
                if ($adjustDebit->compareTo($debit) > 0 || $adjustCredit->compareTo($credit) > 0) {
                    return $this->stop('INVALID', 'ADJUSTMENT_EXCEEDS_MOVEMENT');
                }
                $nature = $pgcc[$account['cCta']]['codNat'];
                if (in_array($nature, ['1', '2', '3'], true) && ! $book['first_reporting_period']) {
                    $prior = $account['previous_balance'] ?? null;
                    if (! is_array($prior) || ($prior['period'] ?? null) !== $previousMonth || ! $this->hash($prior['source_hash'] ?? null)) {
                        return $this->stop('ACTION_REQUIRED', 'PATRIMONIAL_PREVIOUS_BALANCE_UNPROVEN');
                    }
                    if ($opening->compareTo($this->money($prior['amount'] ?? null)) !== 0 || ($prior['nature'] ?? null) !== $account['natSaldoInic']) {
                        return $this->stop('INVALID', 'PATRIMONIAL_OPENING_CONFLICT');
                    }
                }
                $monthNumber = (int) substr($month, 5, 2);
                $resets = match ($frequency) {
                    'A' => [1], 'S' => [1, 7], 'Q' => [1, 5, 9], 'T' => [1, 4, 7, 10], 'B' => [1, 3, 5, 7, 9, 11], 'M' => range(1, 12),
                };
                if (in_array($nature, ['4', '5'], true) && in_array($monthNumber, $resets, true) && $opening->compareTo(DecimalValue::fromString('0.00')) !== 0) {
                    return $this->stop('INVALID', 'RESULT_ACCOUNT_OPENING_NOT_ZERO');
                }
                $balance = ($account['natSaldoInic'] === 'C' ? $opening->multipliedBy(DecimalValue::fromString('-1')) : $opening)->plus($debit)->minus($credit);
                $finalNature = $balance->isNegative() ? 'C' : ($balance->compareTo(DecimalValue::fromString('0.00')) === 0 ? $account['natSaldoInic'] : 'D');
                $absolute = $balance->isNegative() ? $balance->multipliedBy(DecimalValue::fromString('-1')) : $balance;
                if ($reportedFinal->compareTo($absolute) !== 0 || $account['natSaldoFinal'] !== $finalNature) {
                    return $this->stop('INVALID', 'FINAL_BALANCE_OR_NATURE_CONFLICT');
                }
                if ($reportedApur->compareTo(DecimalValue::fromString('0.00')) > 0 && ! isset($account['natVApur'])) {
                    return $this->stop('INVALID', 'ASSESSMENT_NATURE_REQUIRED');
                }
                $computedApur = ($account['natVApur'] ?? null) === 'C' ? $credit->minus($adjustCredit)->plus($adjustDebit) : $debit->minus($adjustDebit)->plus($adjustCredit);
                if ($reportedApur->compareTo($computedApur) !== 0) {
                    return $this->stop('INVALID', 'ASSESSMENT_MOVEMENT_CONFLICT');
                }
                $links = $account['financial_entry_ids'] ?? null;
                if (! is_array($links) || ! array_is_list($links)) {
                    return $this->stop('INVALID', 'FINANCIAL_LINKS_INVALID');
                }
                $linked = DecimalValue::fromString('0.00');
                foreach ($links as $id) {
                    if (! is_string($id) || ! isset($financial[$id]) || isset($used[$id])) {
                        return $this->stop('INVALID', 'FINANCIAL_LINK_UNKNOWN_OR_DUPLICATE');
                    }
                    $used[$id] = true;
                    $linked = $linked->plus($financial[$id]);
                }
                if ($links !== [] && $linked->compareTo($reportedApur) !== 0) {
                    return $this->stop('INVALID', 'FINANCIAL_LINK_AMOUNT_CONFLICT');
                }
                $qualified[] = array_intersect_key($account, array_flip(['cCta', 'natSaldoInic', 'vSaldoInic', 'vMovDebt', 'vAjusteDebt', 'vMovCred', 'vAjusteCred', 'natSaldoFinal', 'vSaldoFinal', 'natVApur', 'vApur']));
            }
            if (count($used) !== count($financial)) {
                return $this->stop('INVALID', 'FINANCIAL_LEDGER_NOT_COMPLETELY_LINKED');
            }

            return ['status' => 'RESOLVED', 'reason' => 'RTC_DERE_TRIAL_BALANCE_QUALIFIED', 'supplier_id' => $book['supplier_id'],
                'document_id' => $book['document_id'], 'period' => $month, 'accounts' => $qualified,
                'pgcc_source_hash' => $book['pgcc_source_hash'], 'book_source_hash' => $book['book_source_hash'], 'source_hash' => $rule['source_hash'],
                'linked_financial_entries' => count($used), 'scope' => 'LOCAL_BALANCE_AND_LEDGER_LINK_NO_REFERENCE_PLAN_OR_TAX_CODE_APPROVAL', 'posting_performed' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('INVALID', 'AMOUNT_OR_DATE_INVALID');
        }
    }

    private function money(mixed $value): DecimalValue
    {
        if (! is_string($value) || ! preg_match('/^\d+\.\d{2}$/', $value)) {
            throw new InvalidArgumentException('Nonnegative monetary value required.');
        }

        return DecimalValue::fromString($value);
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_DERE_BALANCE_'.$reason];
    }
}
