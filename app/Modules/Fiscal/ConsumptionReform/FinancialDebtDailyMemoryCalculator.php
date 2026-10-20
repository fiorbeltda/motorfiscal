<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use InvalidArgumentException;

/** Reconstructs acquisition cost, accounting movements and an explicitly qualified local Selic curve. */
final readonly class FinancialDebtDailyMemoryCalculator
{
    public const CONTRACT = 'DERE-D2101-DAILY-MEMORY-LOCAL-2026.10.06-1';

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $series
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function calculate(array $input, array $series, DateTimeImmutable $knownAt, array $rule): array
    {
        try {
            if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
                || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
                || ! $this->hash($rule['source_hash'] ?? null) || ! $this->hash($rule['rounding_source_hash'] ?? null)
                || ($rule['rounding_contract'] ?? null) !== 'DERE_HALF_EVEN_8_2') {
                return $this->stop('RULE_UNAVAILABLE');
            }
            if (! is_string($input['period'] ?? null) || preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $input['period']) !== 1
                || ! is_string($input['supplier_id'] ?? null) || preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $input['supplier_id']) !== 1
                || ! is_string($input['idTitulo'] ?? null) || preg_match('/^[A-Z]{2}[0-9A-Z]{9}[0-9]$/', $input['idTitulo']) !== 1
                || ! is_string($input['cCta'] ?? null) || preg_match('/^[A-Za-z0-9]{1,53}$/', $input['cCta']) !== 1) {
                return $this->stop('POSITION_SCOPE_INVALID');
            }
            $start = LocalDate::fromString($input['period'].'-01')->value();
            $end = (new DateTimeImmutable($start))->format('Y-m-t');
            if ($end > $knownAt->format('Y-m-d')) {
                return $this->stop('PERIOD_NOT_COMPLETE');
            }
            if (array_key_exists('competence_policy_selection', $rule)) {
                $selection = $rule['competence_policy_selection'];
                if (! is_array($selection) || ($selection['strategy'] ?? null) !== 'BY_COMPETENCE_WITH_FROZEN_OFFICIAL_POLICY_HASHES'
                    || ($selection['status'] ?? null) !== 'ACTIVE' || ! $this->hash($selection['source_hash'] ?? null)
                    || ! is_string($selection['published_at'] ?? null)) {
                    return $this->stop('COMPETENCE_POLICY_SELECTION_UNAVAILABLE');
                }
                try {
                    if (new DateTimeImmutable($selection['published_at']) > $knownAt) {
                        return $this->stop('COMPETENCE_POLICY_SELECTION_UNAVAILABLE');
                    }
                } catch (\DateMalformedStringException) {
                    return $this->stop('COMPETENCE_POLICY_SELECTION_UNAVAILABLE');
                }
                $selected = $selection['policies'][$input['period']] ?? null;
                if (! is_array($selected)) {
                    return $this->stop('COMPETENCE_POLICY_NOT_CONFIGURED');
                }
                if (isset($selected['competence_policy_selection']) || ! is_array($selected['series'] ?? null)
                    || ($selected['series']['period'] ?? null) !== $input['period']
                    || ($selection['policy_hashes'][$input['period']] ?? null) !== CanonicalJson::hash($selected)) {
                    return $this->stop('COMPETENCE_POLICY_OR_HASH_CONFLICT');
                }
                if (CanonicalJson::encode($series) !== CanonicalJson::encode($rule['series'] ?? null)
                    && CanonicalJson::encode($series) !== CanonicalJson::encode($selected['series'])) {
                    return $this->stop('COMPETENCE_CALLER_SERIES_CONFLICT');
                }

                // The selected policy still passes the complete native validation; no fallback or result rewrite.
                return $this->calculate($input, $selected['series'], $knownAt, $selected);
            }
            if (! $this->hash($series['source_hash'] ?? null) || $series['source_hash'] !== ($rule['series_hash'] ?? null)
                || $series['source_hash'] !== CanonicalJson::hash(array_diff_key($series, ['source_hash' => true]))
                || ($series['official_data_sha256'] ?? null) !== ($rule['official_data_sha256'] ?? null)
                || ($series['period'] ?? null) !== $input['period'] || ($series['series_code'] ?? null) !== 11
                || ($series['unit'] ?? null) !== 'PERCENT_PER_BUSINESS_DAY' || ! is_string($series['observed_at'] ?? null)
                || new DateTimeImmutable($series['observed_at']) > $knownAt || ! is_array($series['rows'] ?? null)
                || ! array_is_list($series['rows']) || $series['rows'] === [] || count($series['rows']) > 31) {
                return $this->stop('OFFICIAL_SERIES_UNPROVEN');
            }
            $rates = [];
            $lastRateDate = '';
            foreach ($series['rows'] as $row) {
                if (! is_array($row)) {
                    return $this->stop('DAILY_RATE_INVALID');
                }
                $date = LocalDate::fromString((string) ($row['date'] ?? ''))->value();
                $rate = $this->decimal($row['daily_percent'] ?? null);
                if ($date < $start || $date > $end || $date <= $lastRateDate || $rate->isGreaterThan('100')) {
                    return $this->stop('DAILY_RATE_INVALID');
                }
                $rates[$date] = $rate;
                $lastRateDate = $date;
            }
            $method = $input['method'] ?? null;
            if (! is_array($method) || ($method['cost_method'] ?? null) !== 'WEIGHTED_AVERAGE_ACQUISITION_COST'
                || ! in_array($method['event_timing'] ?? null, ['BEFORE_DAILY_RATE', 'AFTER_DAILY_RATE'], true)
                || ($method['benchmark_method'] ?? null) !== 'REMAINING_POSITION_COMPOUND_WITH_REALIZED_INTEREST_RETAINED'
                || ($method['coupon_effect'] ?? null) !== 'ACCOUNTING_ONLY_NO_ACQUISITION_COST_OR_BENCHMARK_REDUCTION'
                || ($method['normative_homologation_claimed'] ?? null) !== false
                || ! $this->proof($input['method_evidence'] ?? null, ['contract' => self::CONTRACT, 'method' => $method], $input, $start, $end, $knownAt)) {
                return $this->stop('TEMPORAL_OR_BENCHMARK_METHOD_UNPROVEN');
            }
            if (! $this->proof($input['memory_evidence'] ?? null, array_diff_key($input, ['memory_evidence' => true]), $input, $start, $end, $knownAt)) {
                return $this->stop('COMPLETE_MEMORY_EVIDENCE_UNPROVEN');
            }
            $intraday = ($method['appropriation_timing'] ?? null) === 'EXPLICIT_BEFORE_SALE_OR_AFTER_OPERATIONS';
            if (array_key_exists('appropriation_timing', $method) && (! $intraday
                || ($rule['appropriation_sequence_policy'] ?? null) !== 'INDEPENDENT_PRODUCED_YIELD_BEFORE_SALE')) {
                return $this->stop('APPROPRIATION_SEQUENCE_POLICY_UNAVAILABLE');
            }
            $opening = $input['opening'] ?? null;
            if (! is_array($opening)) {
                return $this->stop('OPENING_POSITION_UNPROVEN');
            }
            $quantity = $this->decimal($opening['quantity'] ?? null);
            $cost = $this->decimal($opening['acquisition_cost'] ?? null);
            $book = $this->money($opening['book_balance'] ?? null);
            $unit = $this->decimal($opening['unit_cost'] ?? null);
            if ($quantity->isZero() ? (! $cost->isZero() || ! $book->isZero() || ! $unit->isZero())
                : $cost->isZero() || ! $this->equal8($this->average($cost, $quantity), $unit)) {
                return $this->stop('OPENING_QUANTITY_COST_CONFLICT');
            }
            $previousEnd = (new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d');
            if ($quantity->isZero()) {
                if (($input['first_acquisition'] ?? null) !== true || isset($input['previous_position_evidence'])) {
                    return $this->stop('FIRST_ACQUISITION_SCOPE_CONFLICT');
                }
            } elseif (($input['first_acquisition'] ?? null) !== false
                || ! $this->proof($input['previous_position_evidence'] ?? null,
                    ['supplier_id' => $input['supplier_id'], 'idTitulo' => $input['idTitulo'], 'cCta' => $input['cCta'],
                        'period' => substr($previousEnd, 0, 7), 'closing' => $opening], $input, $previousEnd, $previousEnd, $knownAt)) {
                return $this->stop('PREVIOUS_POSITION_UNPROVEN');
            }
            $operations = $input['operations'] ?? null;
            $curve = $input['contract_curve'] ?? null;
            if (! is_array($operations) || ! array_is_list($operations) || count($operations) > 10000
                || ! is_array($curve) || ! array_is_list($curve) || count($curve) !== (int) substr($end, 8, 2)) {
                return $this->stop('OPERATIONS_OR_CURVE_COVERAGE_INVALID');
            }
            $byDate = [];
            $ids = [];
            $lastDate = '';
            $lastSequence = 0;
            foreach ($operations as $event) {
                if (! is_array($event) || ! is_string($event['id'] ?? null) || preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $event['id']) !== 1
                    || isset($ids[$event['id']]) || ! is_int($event['sequence'] ?? null) || $event['sequence'] < 1 || $event['sequence'] > 10000
                    || ! in_array($event['kind'] ?? null, ['BUY', 'SELL', 'AMORTIZATION', 'COUPON'], true)) {
                    return $this->stop('OPERATION_ID_SEQUENCE_OR_KIND_INVALID');
                }
                $date = LocalDate::fromString((string) ($event['date'] ?? ''))->value();
                if ($date < $start || $date > $end || $date < $lastDate || ($date === $lastDate && $event['sequence'] <= $lastSequence)) {
                    return $this->stop('OPERATION_ORDER_OR_DATE_INVALID');
                }
                $ids[$event['id']] = true;
                $byDate[$date][] = $event;
                $lastDate = $date;
                $lastSequence = $event['sequence'];
            }
            if ($quantity->isZero() && ! array_filter($operations, static fn (array $e): bool => $e['kind'] === 'BUY')) {
                return $this->stop('FIRST_ACQUISITION_MISSING');
            }
            $benchmark = $cost;
            $interest = BigDecimal::of('0');
            $variation = BigDecimal::of('0');
            $entries = BigDecimal::of('0');
            $exits = BigDecimal::of('0');
            $coupons = BigDecimal::of('0');
            $saleGains = BigDecimal::of('0');
            $memory = [];
            $index = 0;
            $dateObject = new DateTimeImmutable($start);
            while (($date = $dateObject->format('Y-m-d')) <= $end) {
                $row = $curve[$index++];
                if (! is_array($row) || ($row['date'] ?? null) !== $date || ! is_string($row['contract_curve_id'] ?? null)
                    || trim($row['contract_curve_id']) === '' || strlen($row['contract_curve_id']) > 100) {
                    return $this->stop('CURVE_DATE_OR_ID_INVALID');
                }
                $events = $byDate[$date] ?? [];
                $eventMemory = [];
                $before = $row['appropriation_before_operation_id'] ?? null;
                if (array_key_exists('appropriation_before_operation_id', $row) || array_key_exists('appropriation_sequence_evidence', $row)) {
                    $matches = array_values(array_filter($events, static fn (array $event): bool => $event['id'] === $before && $event['kind'] === 'SELL'));
                    if (! $intraday || ! is_string($before) || count($matches) !== 1 || ! is_array($row['appropriation_sequence_evidence'] ?? null)) {
                        return $this->stop('APPROPRIATION_SEQUENCE_TARGET_UNPROVEN');
                    }
                }
                $appropriation = $this->decimal($row['appropriation'] ?? null, true);
                $applied = false;
                $appropriate = function () use ($input, $row, $before, $date, $start, $end, $knownAt, $appropriation, &$quantity, &$cost, &$book, &$variation, &$applied): void {
                    if ($quantity->isZero() && ! $appropriation->isZero()) {
                        throw new InvalidArgumentException('Appropriation without a held position.');
                    }
                    if ($before !== null) {
                        $value = ['supplier_id' => $input['supplier_id'], 'period' => $input['period'], 'idTitulo' => $input['idTitulo'], 'cCta' => $input['cCta'],
                            'date' => $date, 'contract_curve_id' => $row['contract_curve_id'], 'operation_id' => $before,
                            'appropriation' => $row['appropriation'], 'quantity_before' => (string) $this->round8($quantity),
                            'acquisition_cost_before' => (string) $this->round8($cost), 'book_balance_before' => (string) $this->round8($book),
                            'produced_yield_not_fair_value' => true, 'recognized_before_disposal' => true];
                        if ($appropriation->isZero() || ! $this->proof($row['appropriation_sequence_evidence'], $value, $input, $start, $end, $knownAt)) {
                            throw new InvalidArgumentException('Independent intraday appropriation unproven.');
                        }
                    }
                    $book = $this->round8($book->plus($appropriation));
                    $variation = $this->round8($variation->plus($appropriation));
                    $applied = true;
                };
                $apply = function () use ($events, $before, $appropriate, &$quantity, &$cost, &$book, &$benchmark, &$entries, &$exits, &$coupons, &$saleGains, &$eventMemory): void {
                    foreach ($events as $event) {
                        if ($event['id'] === $before) {
                            $appropriate();
                        }
                        $bookAmount = $this->money($event['book_amount'] ?? null);
                        $releasedCost = BigDecimal::of('0');
                        $releasedBenchmark = BigDecimal::of('0');
                        $gain = BigDecimal::of('0');
                        $pu = null;
                        $q = null;
                        switch ($event['kind']) {
                            case 'BUY':
                                $q = $this->decimal($event['quantity'] ?? null);
                                $pu = $this->decimal($event['unit_price'] ?? null);
                                if ($q->isZero() || $pu->isZero() || ! $this->final($this->round8($q->multipliedBy($pu)))->isEqualTo($bookAmount)) {
                                    throw new InvalidArgumentException('Purchase amount differs from quantity and PU.');
                                }
                                $acquired = $this->round8($q->multipliedBy($pu));
                                $quantity = $quantity->plus($q);
                                $cost = $this->round8($cost->plus($acquired));
                                $benchmark = $this->round8($benchmark->plus($acquired));
                                $book = $book->plus($bookAmount);
                                $entries = $entries->plus($bookAmount);
                                break;
                            case 'SELL':
                                $q = $this->decimal($event['quantity'] ?? null);
                                if ($q->isZero() || $q->isGreaterThan($quantity)) {
                                    throw new InvalidArgumentException('Sale exceeds available quantity.');
                                }
                                $releasedCost = $q->isEqualTo($quantity) ? $cost : $cost->multipliedBy($q)->dividedBy($quantity, 8, RoundingMode::HalfEven);
                                $releasedBenchmark = $q->isEqualTo($quantity) ? $benchmark : $benchmark->multipliedBy($q)->dividedBy($quantity, 8, RoundingMode::HalfEven);
                                if (! $this->equal8($releasedCost, $this->decimal($event['claimed_acquisition_cost_released'] ?? null))) {
                                    throw new InvalidArgumentException('Sale acquisition cost mismatch.');
                                }
                                $gain = $this->money($event['sale_proceeds'] ?? null)->minus($bookAmount);
                                $saleGains = $saleGains->plus($gain);
                                $quantity = $quantity->minus($q);
                                $cost = $this->round8($cost->minus($releasedCost));
                                $benchmark = $this->round8($benchmark->minus($releasedBenchmark));
                                $book = $book->minus($bookAmount);
                                $exits = $exits->plus($bookAmount);
                                break;
                            case 'AMORTIZATION':
                                $releasedCost = $this->decimal($event['acquisition_cost_reduction'] ?? null);
                                if ($releasedCost->isZero() || $releasedCost->isGreaterThan($cost) || $quantity->isZero()) {
                                    throw new InvalidArgumentException('Invalid acquisition cost amortization.');
                                }
                                $releasedBenchmark = $releasedCost->isEqualTo($cost) ? $benchmark : $benchmark->multipliedBy($releasedCost)->dividedBy($cost, 8, RoundingMode::HalfEven);
                                $cost = $this->round8($cost->minus($releasedCost));
                                $benchmark = $this->round8($benchmark->minus($releasedBenchmark));
                                $book = $book->minus($bookAmount);
                                $exits = $exits->plus($bookAmount);
                                if ($cost->isZero()) {
                                    $quantity = BigDecimal::of('0');
                                }
                                break;
                            case 'COUPON':
                                if ($quantity->isZero() || $bookAmount->isZero()) {
                                    throw new InvalidArgumentException('Coupon without a held position.');
                                }
                                $book = $book->minus($bookAmount);
                                $coupons = $coupons->plus($bookAmount);
                                break;
                        }
                        if ($book->isNegative() || ($quantity->isZero() && (! $cost->isZero() || ! $book->isZero() || ! $benchmark->isZero()))) {
                            throw new InvalidArgumentException('Negative book balance or unallocated position after complete exit.');
                        }
                        $eventMemory[] = ['id' => $event['id'], 'sequence' => $event['sequence'], 'kind' => $event['kind'],
                            'quantity' => $q === null ? null : (string) $this->round8($q), 'acquisition_unit_price' => $pu === null ? null : (string) $this->round8($pu),
                            'book_amount' => (string) $bookAmount, 'acquisition_cost_released' => (string) $this->round8($releasedCost),
                            'benchmark_balance_released' => (string) $this->round8($releasedBenchmark), 'realized_sale_gain' => (string) $this->final($gain),
                            'quantity_after' => (string) $this->round8($quantity), 'acquisition_cost_after' => (string) $this->round8($cost),
                            'unit_cost_after' => (string) $this->average($cost, $quantity), 'book_balance_after' => (string) $this->round8($book)];
                    }
                };
                if ($method['event_timing'] === 'BEFORE_DAILY_RATE') {
                    $apply();
                }
                $rate = $rates[$date] ?? BigDecimal::of('0');
                $basis = $benchmark;
                $dailyInterest = $basis->multipliedBy($rate)->dividedBy('100', 8, RoundingMode::HalfEven);
                $benchmark = $this->round8($benchmark->plus($dailyInterest));
                $interest = $this->round8($interest->plus($dailyInterest));
                if ($method['event_timing'] === 'AFTER_DAILY_RATE') {
                    $apply();
                }
                if (! $applied) {
                    if ($quantity->isZero() && ! $appropriation->isZero()) {
                        return $this->stop('CURVE_WITHOUT_HELD_POSITION');
                    }
                    $appropriate();
                }
                if ($book->isNegative()) {
                    return $this->stop('CURVE_NEGATIVE_BOOK_BALANCE');
                }
                foreach (['quantity' => $quantity, 'acquisition_cost' => $cost, 'unit_cost' => $this->average($cost, $quantity),
                    'book_balance' => $book, 'selic_basis' => $basis] as $field => $actual) {
                    if (! $this->equal8($actual, $this->decimal($row['claimed_'.$field] ?? null))) {
                        return $this->stop('DAILY_POSITION_OR_CURVE_CONFLICT');
                    }
                }
                $memory[] = ['date' => $date, 'rate_observed' => isset($rates[$date]), 'daily_percent' => (string) $rate,
                    'selic_basis' => (string) $this->round8($basis), 'daily_selic_interest' => (string) $dailyInterest,
                    'accumulated_selic_interest' => (string) $interest, 'remaining_benchmark_balance' => (string) $benchmark,
                    'quantity' => (string) $this->round8($quantity), 'acquisition_cost' => (string) $this->round8($cost),
                    'unit_cost' => (string) $this->average($cost, $quantity), 'book_balance' => (string) $book,
                    'contract_curve_id' => $row['contract_curve_id'], 'appropriation' => (string) $this->round8($appropriation), 'operations' => $eventMemory,
                    ...($before === null ? [] : ['appropriation_before_operation_id' => $before, 'appropriation_sequence_evidence_hash' => $row['appropriation_sequence_evidence']['source_hash']])];
                $dateObject = $dateObject->modify('+1 day');
            }
            $pis = $this->money($input['vPisCofins'] ?? '0.00');
            $selic = $this->final($interest);
            $var = $this->final($variation);
            $base = ($selic->isLessThan($var) ? $selic : $var)->minus($pis);
            $base = $base->isNegative() ? BigDecimal::of('0.00') : $base;
            $summary = ['vSaldoContIni' => $opening['book_balance'], 'vEntradas' => (string) $this->final($entries),
                'vSaidas' => (string) $this->final($exits), 'vJurosRec' => (string) $this->final($coupons), 'vVarMensal' => (string) $var,
                'vSaldoContFin' => (string) $this->final($book), 'vSelic' => (string) $selic, 'vPisCofins' => (string) $pis, 'vApur' => (string) $this->final($base)];
            if (! is_array($input['declared_monthly'] ?? null) || CanonicalJson::encode($input['declared_monthly']) !== CanonicalJson::encode($summary)) {
                return $this->stop('MONTHLY_SUMMARY_CONFLICT');
            }

            return ['status' => 'RESOLVED', 'reason' => 'RTC_DERE_D2101_DAILY_LOCAL_MEMORY_CALCULATED', 'contract' => self::CONTRACT,
                'supplier_id' => $input['supplier_id'], 'period' => $input['period'], 'idTitulo' => $input['idTitulo'], 'cCta' => $input['cCta'],
                'method' => $method, 'monthly' => $summary, 'closing_quantity' => (string) $this->round8($quantity),
                'closing_acquisition_cost' => (string) $this->round8($cost), 'closing_unit_cost' => (string) $this->average($cost, $quantity),
                'realized_sale_gain' => (string) $this->final($saleGains), 'daily_memory' => $memory, 'daily_rates_applied' => count($rates),
                'memory_days' => count($memory), 'operations_count' => count($operations), 'input_hash' => CanonicalJson::hash($input),
                'series_hash' => $series['source_hash'], 'official_data_sha256' => $series['official_data_sha256'],
                'source_hash' => $rule['source_hash'], 'rounding_contract' => $rule['rounding_contract'], 'rounding_source_hash' => $rule['rounding_source_hash'],
                'memory_evidence_hash' => $input['memory_evidence']['source_hash'], 'method_evidence_hash' => $input['method_evidence']['source_hash'],
                'retention_years' => 5, 'normative_method_homologated' => false, 'financial_base_integration_performed' => false,
                'xml_projection_performed' => false, 'negative_base_created' => false, 'cross_title_loss_offset_performed' => false,
                'sale_gain_tax_treatment_performed' => false, 'posting_performed' => false, 'production_ready' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('AMOUNT_DATE_OR_STOCK_INVALID');
        }
    }

    /** @param array<string,mixed> $input */
    private function proof(mixed $proof, mixed $value, array $input, string $start, string $end, DateTimeImmutable $knownAt): bool
    {
        return is_array($proof) && ($proof['qualified'] ?? null) === true && ($proof['supplier_id'] ?? null) === $input['supplier_id']
            && is_string($proof['evidence_id'] ?? null) && trim($proof['evidence_id']) !== '' && $this->hash($proof['source_hash'] ?? null)
            && $proof['source_hash'] === CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]))
            && CanonicalJson::encode($proof['value'] ?? null) === CanonicalJson::encode($value)
            && LocalDate::fromString((string) ($proof['valid_from'] ?? ''))->value() <= $start
            && LocalDate::fromString((string) ($proof['valid_to'] ?? ''))->value() >= $end
            && LocalDate::fromString((string) ($proof['observed_on'] ?? ''))->value() >= $end
            && $proof['observed_on'] <= $knownAt->format('Y-m-d');
    }

    private function decimal(mixed $value, bool $signed = false): BigDecimal
    {
        if (! is_string($value) || preg_match($signed ? '/^-?(0|[1-9][0-9]{0,14})(\.[0-9]{1,8})?$/' : '/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,8})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Canonical decimal with at most eight fraction digits required.');
        }

        return BigDecimal::of($value);
    }

    private function money(mixed $value): BigDecimal
    {
        if (! is_string($value) || preg_match('/^(0|[1-9][0-9]{0,14})\.[0-9]{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('Canonical nonnegative cents amount required.');
        }

        return BigDecimal::of($value);
    }

    private function average(BigDecimal $cost, BigDecimal $quantity): BigDecimal
    {
        return $quantity->isZero() ? BigDecimal::of('0.00000000') : $cost->dividedBy($quantity, 8, RoundingMode::HalfEven);
    }

    private function round8(BigDecimal $value): BigDecimal
    {
        return $value->toScale(8, RoundingMode::HalfEven);
    }

    private function final(BigDecimal $value): BigDecimal
    {
        return $value->toScale(2, RoundingMode::HalfEven);
    }

    private function equal8(BigDecimal $a, BigDecimal $b): bool
    {
        return $this->round8($a)->isEqualTo($this->round8($b));
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_D2101_DAILY_'.$reason, 'production_ready' => false];
    }
}
