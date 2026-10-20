<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use InvalidArgumentException;

/** Checks a supplied accounting journal; never creates a posting or a fiscal adjustment. */
final readonly class FinancialPublicGrossRoundingQualifier
{
    public const METHOD = 'PROVIDED_PUBLIC_GROSS_ROUNDING_JOURNAL_8_2';

    /** @param array<string,mixed> $memory
     * @param  array<string,mixed>  $title
     * @param  array<string,mixed>  $rule
     * @return array<string,mixed>
     */
    public function resolve(mixed $bridge, array $memory, array $title, string $supplier, string $resultAccount, string $origin, string $parent, DateTimeImmutable $known, array $rule): array
    {
        try {
            if (($rule['gross_rounding_reconciliation_policy'] ?? null) !== self::METHOD || ! is_array($bridge)
                || ($bridge['method'] ?? null) !== self::METHOD || ($bridge['normative_homologation_claimed'] ?? null) !== false
                || ($memory['status'] ?? null) !== 'RESOLVED' || ($memory['contract'] ?? null) !== FinancialDebtDailyMemoryCalculator::CONTRACT) {
                return $this->stop('POLICY_OR_MEMORY_UNPROVEN');
            }
            $credit = $debit = BigDecimal::of('0.00000000');
            $date = null;
            foreach ($memory['daily_memory'] as $day) {
                $amount = BigDecimal::of($day['appropriation']);
                if ($amount->isZero()) {
                    continue;
                }
                $date = $day['date'];
                if ($amount->isNegative()) {
                    $debit = $debit->plus($amount->abs());
                } else {
                    $credit = $credit->plus($amount);
                }
            }
            $roundedCredit = $credit->toScale(2, RoundingMode::HalfEven);
            $roundedDebit = $debit->toScale(2, RoundingMode::HalfEven);
            $delta = BigDecimal::of($memory['monthly']['vVarMensal'])->minus($roundedCredit->minus($roundedDebit));
            if ($delta->isZero() || $delta->abs()->isGreaterThan('0.01') || ! is_string($date)
                || $resultAccount === $title['cCta'] || $origin === $parent) {
                return $this->stop('SCOPE_UNPROVEN');
            }
            $lines = [
                ['cCta' => $title['cCta'], 'nature' => $delta->isNegative() ? 'C' : 'D', 'amount' => (string) $delta->abs()->toScale(2), 'financial_entry_id' => null],
                ['cCta' => $resultAccount, 'nature' => $delta->isNegative() ? 'D' : 'C', 'amount' => (string) $delta->abs()->toScale(2), 'financial_entry_id' => $origin],
            ];
            usort($lines, static fn (array $a, array $b): int => [$a['cCta'], $a['nature'], $a['financial_entry_id']] <=> [$b['cCta'], $b['nature'], $b['financial_entry_id']]);
            $journal = ['event_id' => 'GROSS_ROUNDING:'.$date, 'parent_id' => $parent, 'date' => $date, 'lines' => $lines];
            if (CanonicalJson::encode($bridge['journal'] ?? null) !== CanonicalJson::encode($journal)) {
                return $this->stop('JOURNAL_CONFLICT');
            }
            $period = substr($date, 0, 7);
            $value = ['supplier_id' => $supplier, 'period' => $period, 'title' => $title, 'daily_memory_hash' => CanonicalJson::hash($memory),
                'method' => self::METHOD, 'raw_gross_credit' => (string) $credit->toScale(8), 'raw_gross_debit' => (string) $debit->toScale(8),
                'rounded_gross_credit' => (string) $roundedCredit, 'rounded_gross_debit' => (string) $roundedDebit,
                'signed_adjustment' => (string) $delta->toScale(2), 'monthly_variation' => $memory['monthly']['vVarMensal'],
                'journal' => $journal, 'result_account_code' => $resultAccount, 'financial_entry_id' => $origin, 'parent_id' => $parent,
                'policy_hash' => CanonicalJson::hash($rule), 'tax_origin' => 'D2101', 'book_apur_amount' => '0.00', 'normative_homologation_claimed' => false];
            if (! (new FinancialPublicOfferDebtQualifier)->proof($bridge['independent_evidence'] ?? null, $value, $supplier,
                $period.'-01', (new DateTimeImmutable($period.'-01'))->format('Y-m-t'), $known)) {
                return $this->stop('INDEPENDENT_JOURNAL_UNPROVEN');
            }

            return ['status' => 'RESOLVED', 'method' => self::METHOD, ...$value,
                'adjusted_gross_credit' => (string) $roundedCredit->plus($delta->isPositive() ? $delta : '0.00')->toScale(2),
                'adjusted_gross_debit' => (string) $roundedDebit->plus($delta->isNegative() ? $delta->abs() : '0.00')->toScale(2),
                'evidence_hash' => $bridge['independent_evidence']['source_hash'], 'posting_performed' => false, 'production_ready' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('AMOUNT_INVALID');
        }
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_PUBLIC_GROSS_ROUNDING_'.$reason, 'production_ready' => false];
    }
}
