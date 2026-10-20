<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\NFSe\DereRounding;
use DateTimeImmutable;
use InvalidArgumentException;

/** D2101 arithmetic for a proven constant acquisition-cost position held throughout one month. */
final readonly class FinancialDebtConstantCostCalculator
{
    public const CONTRACT = 'DERE-D2101-CONSTANT-COST-CALCULATION-2026.10.06-1';

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $series
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function calculate(array $input, array $series, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
            || ! $this->hash($rule['source_hash'] ?? null)) {
            return $this->stop('RULE_UNAVAILABLE');
        }
        if (! $this->hash($series['source_hash'] ?? null) || $series['source_hash'] !== ($rule['series_hash'] ?? null)
            || $series['source_hash'] !== CanonicalJson::hash(array_diff_key($series, ['source_hash' => true]))
            || ($series['official_data_sha256'] ?? null) !== ($rule['official_data_sha256'] ?? null)
            || ($series['series_code'] ?? null) !== 11 || ($series['unit'] ?? null) !== 'PERCENT_PER_BUSINESS_DAY'
            || ($series['period'] ?? null) !== ($input['period'] ?? null)
            || ! is_string($series['observed_at'] ?? null) || new DateTimeImmutable($series['observed_at']) > $knownAt
            || ! is_array($series['rows'] ?? null) || ! array_is_list($series['rows']) || $series['rows'] === [] || count($series['rows']) > 31) {
            return $this->stop('OFFICIAL_DAILY_SERIES_UNPROVEN');
        }
        if (! is_string($input['period'] ?? null) || preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $input['period']) !== 1
            || ! is_string($input['supplier_id'] ?? null) || preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $input['supplier_id']) !== 1
            || ! is_string($input['idTitulo'] ?? null) || preg_match('/^[A-Z]{2}[0-9A-Z]{9}[0-9]$/', $input['idTitulo']) !== 1
            || ! is_string($input['cCta'] ?? null) || preg_match('/^[0-9A-Za-z]{1,53}$/', $input['cCta']) !== 1) {
            return $this->stop('POSITION_SCOPE_INVALID');
        }
        if (($input['held_entire_period'] ?? null) !== true || ($input['constant_average_cost'] ?? null) !== true
            || ($input['vEntradas'] ?? null) !== '0.00' || ($input['vSaidas'] ?? null) !== '0.00'
            || ($input['cost_basis_method'] ?? null) !== 'WEIGHTED_AVERAGE_ACQUISITION_COST') {
            return $this->stop('CHANGING_POSITION_OR_COST_PENDING');
        }
        $dereRounding = ($rule['rounding_contract'] ?? null) === 'DERE_HALF_EVEN_8_2';
        if ($dereRounding && ! $this->hash($rule['rounding_source_hash'] ?? null)) {
            return $this->stop('ROUNDING_SOURCE_UNPROVEN');
        }
        $moneyRound = static fn (DecimalValue $value): DecimalValue => $dereRounding
            ? DecimalValue::fromString((new DereRounding)->finalAmount((new DereRounding)->intermediate($value->value()))) : $value->rounded(2);
        try {
            $start = LocalDate::fromString($input['period'].'-01')->value();
            $end = (new DateTimeImmutable($start))->format('Y-m-t');
            $proof = $input['position_evidence'] ?? null;
            $value = array_diff_key($input, ['position_evidence' => true]);
            if (! is_array($proof) || ($proof['qualified'] ?? null) !== true || ($proof['supplier_id'] ?? null) !== $input['supplier_id']
                || ! is_string($proof['evidence_id'] ?? null) || trim($proof['evidence_id']) === ''
                || ! $this->hash($proof['source_hash'] ?? null)
                || $proof['source_hash'] !== CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]))
                || CanonicalJson::encode($proof['value'] ?? null) !== CanonicalJson::encode($value)
                || LocalDate::fromString((string) ($proof['valid_from'] ?? ''))->value() > $start
                || LocalDate::fromString((string) ($proof['valid_to'] ?? ''))->value() < $end
                || LocalDate::fromString((string) ($proof['observed_on'] ?? ''))->value() < $end
                || $proof['observed_on'] > $knownAt->format('Y-m-d') || $end > $knownAt->format('Y-m-d')) {
                return $this->stop('CONSTANT_POSITION_EVIDENCE_UNPROVEN');
            }
            $quantity = $this->decimal($input['quantity'] ?? null);
            $unitCost = $this->decimal($input['weighted_average_unit_cost'] ?? null);
            $principal = $this->money($input['cost_basis'] ?? null);
            $zero = DecimalValue::fromString('0.00');
            if ($principal->compareTo($zero) <= 0 || $quantity->compareTo($zero) <= 0 || $unitCost->compareTo($zero) <= 0
                || $moneyRound($quantity->multipliedBy($unitCost))->compareTo($principal) !== 0) {
                return $this->stop('QUANTITY_COST_BASIS_CONFLICT');
            }
            $factor = DecimalValue::fromString('1');
            $previousDate = '';
            $memory = [];
            foreach ($series['rows'] as $row) {
                if (! is_array($row)) {
                    return $this->stop('DAILY_RATE_DATE_OR_VALUE_INVALID');
                }
                $date = LocalDate::fromString((string) ($row['date'] ?? ''))->value();
                $rate = $this->decimal($row['daily_percent'] ?? null);
                if ($date < $start || $date > $end || $date <= $previousDate || $rate->isNegative()
                    || $rate->compareTo(DecimalValue::fromString('100')) > 0) {
                    return $this->stop('DAILY_RATE_DATE_OR_VALUE_INVALID');
                }
                $factor = $factor->multipliedBy(DecimalValue::fromString('1')->plus($rate->dividedBy(DecimalValue::fromString('100'), 12)));
                if ($dereRounding) {
                    $factor = DecimalValue::fromString((new DereRounding)->intermediate($factor->value()));
                }
                $memory[] = ['date' => $date, 'daily_percent' => $row['daily_percent'], 'accumulated_factor' => $factor->rounded(18)->value()];
                $previousDate = $date;
            }
            $selic = $moneyRound($principal->multipliedBy($factor->minus(DecimalValue::fromString('1'))));
            $variation = $this->money($input['vVarMensal'] ?? null, true);
            $pis = $this->money($input['vPisCofins'] ?? '0.00');
            $capped = $variation->compareTo($selic) < 0 ? $variation : $selic;
            $base = $capped->minus($pis);
            $base = $base->isNegative() ? $zero : $base;
            if ((isset($input['claimed_vSelic']) && $this->money($input['claimed_vSelic'])->compareTo($selic) !== 0)
                || (isset($input['claimed_vApur']) && $this->money($input['claimed_vApur'])->compareTo($base) !== 0)) {
                return $this->stop('DECLARED_SELIC_OR_BASE_CONFLICT');
            }
            $excess = $variation->minus($selic);

            return ['status' => 'RESOLVED', 'reason' => 'RTC_DERE_D2101_CONSTANT_COST_CALCULATED', 'contract' => self::CONTRACT,
                'supplier_id' => $input['supplier_id'], 'period' => $input['period'], 'idTitulo' => $input['idTitulo'], 'cCta' => $input['cCta'],
                'cost_basis' => $principal->value(), 'vSelic' => $selic->value(), 'vVarMensal' => $variation->value(), 'vPisCofins' => $pis->value(),
                'vApur' => $base->rounded(2)->value(), 'excess_above_selic' => ($excess->isNegative() ? $zero : $excess)->rounded(2)->value(),
                'daily_rates_applied' => count($memory), 'daily_memory' => $memory,
                'input_hash' => CanonicalJson::hash($input), 'position_evidence_hash' => $proof['source_hash'],
                'series_hash' => $series['source_hash'], 'official_data_sha256' => $series['official_data_sha256'], 'source_hash' => $rule['source_hash'],
                ...($dereRounding ? ['rounding_contract' => 'DERE_HALF_EVEN_8_2', 'rounding_source_hash' => $rule['rounding_source_hash']] : []),
                'scope' => 'CONSTANT_COST_FULL_MONTH_ARITHMETIC_ONLY_PUBLIC_OFFER_AND_LEDGER_QUALIFICATION_SEPARATE',
                'negative_base_created' => false, 'cross_title_loss_offset_performed' => false, 'tax_totalization_performed' => false,
                'production_ready' => false, 'posting_performed' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('AMOUNT_OR_DATE_INVALID');
        }
    }

    private function decimal(mixed $v): DecimalValue
    {
        if (! is_string($v) || preg_match('/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,8})?$/', $v) !== 1) {
            throw new InvalidArgumentException('Canonical nonnegative decimal required.');
        }

        return DecimalValue::fromString($v);
    }

    private function money(mixed $v, bool $signed = false): DecimalValue
    {
        if (! is_string($v) || preg_match($signed ? '/^-?(0|[1-9][0-9]{0,14})\.[0-9]{2}$/' : '/^(0|[1-9][0-9]{0,14})\.[0-9]{2}$/', $v) !== 1) {
            throw new InvalidArgumentException('Canonical money required.');
        }

        return DecimalValue::fromString($v);
    }

    private function hash(mixed $v): bool
    {
        return is_string($v) && preg_match('/^[a-f0-9]{64}$/', $v) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_D2101_'.$reason, 'production_ready' => false];
    }
}
