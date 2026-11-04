<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

/** Compose B11 then B14 outside the per-operation tax decision; never post a ledger. */
final readonly class PeriodCreditAssessment155
{
    public const CONTRACT = 'RTC-PERIOD-CREDIT-ASSESSMENT-2026.11.04-A155';

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        if (($input['contract'] ?? null) !== self::CONTRACT) {
            return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_CONTRACT_REQUIRED');
        }
        foreach (['assessment_id', 'taxpayer_id', 'ledger_evidence_id'] as $key) {
            if (! $this->id($input[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'RTC_PERIOD_CREDIT_IDENTITY_EVIDENCE_REQUIRED');
            }
        }
        if (($input['ledger_complete'] ?? null) !== true || ($input['prior_utilization_complete'] ?? null) !== true || ! is_bool($input['refund_requested'] ?? null)
            || ! is_string($input['period'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'RTC_PERIOD_CREDIT_COMPLETE_LEDGER_REQUIRED');
        }
        foreach (['credits', 'events', 'debts', 'transfers'] as $key) {
            if (! is_array($input[$key] ?? null) || ! array_is_list($input[$key])) {
                return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_LIST_REQUIRED');
            }
            foreach ($input[$key] as $row) {
                if (! is_array($row) || array_is_list($row)
                    || (isset($row['taxpayer_id']) && $row['taxpayer_id'] !== $input['taxpayer_id'])) {
                    return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_RECORD_SCOPE_CONFLICT');
                }
            }
        }
        $originals = [];
        foreach ($input['credits'] as $row) {
            if (! $this->id($row['credit_id'] ?? null) || isset($originals[$row['credit_id']])) {
                return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_DUPLICATE_OR_INVALID_ID');
            }
            if (isset($row['appropriated_period'])
                && (! is_string($row['appropriated_on'] ?? null) || $row['appropriated_period'] !== substr($row['appropriated_on'], 0, 7))) {
                return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_ORIGINAL_DATE_CONFLICT');
            }
            if (array_key_exists('monetary_correction', $row)) {
                return $this->stop('INVALID', 'B14_MONETARY_CORRECTION_PROHIBITED_ART53_2');
            }
            if (array_key_exists('refund_prohibited', $row) && ! is_bool($row['refund_prohibited'])) {
                return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_REFUND_RESTRICTION_TYPE_INVALID');
            }
            if (! is_string($row['previously_utilized_amount'] ?? null)
                || preg_match('/\A\d{1,15}(?:\.\d{1,2})?\z/', $row['previously_utilized_amount']) !== 1) {
                return $this->stop('ACTION_REQUIRED', 'RTC_PERIOD_CREDIT_PRIOR_UTILIZATION_REQUIRED');
            }
            if (isset($row['kind']) && ! is_string($row['kind'])) {
                return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_KIND_INVALID');
            }
            $originals[$row['credit_id']] = $row;
        }
        foreach ($input['events'] as $row) {
            if (! $this->id($row['credit_id'] ?? null)) {
                return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_EVENT_LINK_INVALID');
            }
        }
        $seenDebts = [];
        foreach ($input['debts'] as $row) {
            if (! $this->id($row['debt_id'] ?? null) || isset($seenDebts[$row['debt_id']])) {
                return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_DEBT_ID_INVALID_OR_DUPLICATE');
            }
            $seenDebts[$row['debt_id']] = true;
            $date = $row['fact_date'] ?? null;
            if (! is_string($date) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1
                || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_DEBT_DATE_INVALID');
            }
        }
        $adjustment = (new CreditAdjustmentCycleB11)->apply($input['credits'], $input['events'], $input['period']);
        if ($adjustment['status'] !== 'RESOLVED') {
            return $this->stop($adjustment['status'], $adjustment['reason']) + ['adjustment' => $adjustment];
        }
        $pool = [];
        foreach ($adjustment['balances'] as $balance) {
            $original = $originals[$balance['credit_id']];
            $available = DecimalValue::fromString($balance['available_for_consumption'])
                ->minus(DecimalValue::fromString($original['previously_utilized_amount']));
            if ($available->isNegative()) {
                return $this->stop('INVALID', 'RTC_PERIOD_CREDIT_ADJUSTED_BALANCE_ALREADY_CONSUMED');
            }
            $pool[] = [...$original, 'amount' => $available->rounded(2)->value(),
                'appropriated_period' => substr($original['appropriated_on'], 0, 7)];
        }
        $plan = (new CreditUtilizationPlannerB14)->plan($pool, $input['debts'], $input['period'], $input['refund_requested'], $input['transfers']);
        if ($plan['status'] !== 'RESOLVED') {
            return $this->stop($plan['status'], $plan['reason']) + ['adjustment' => $adjustment, 'utilization' => $plan];
        }
        $report = ['contract' => self::CONTRACT, 'status' => 'LOCAL_PERIOD_CREDIT_ASSESSMENT',
            'assessment_id' => $input['assessment_id'], 'taxpayer_id' => $input['taxpayer_id'],
            'ledger_evidence_id' => $input['ledger_evidence_id'], 'period' => $input['period'],
            'input' => $input, 'input_hash' => CanonicalJson::hash($input),
            'adjustment' => $adjustment, 'utilization' => $plan,
            'available_pool_after_prior_utilization' => $pool,
            'components' => [CreditAdjustmentCycleB11::VERSION, CreditUtilizationPlannerB14::VERSION],
            'ledger_posted' => false, 'credit_appropriated' => false, 'debts_extinguished' => false,
            'official_homologation' => false, 'production_ready' => false];

        return [...$report, 'report_hash' => CanonicalJson::hash($report)];
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null) && isset($report['report_hash'])
            && CanonicalJson::hash($this->assess($report['input'])) === CanonicalJson::hash($report);
    }

    private function id(mixed $value): bool
    {
        return is_string($value) && $value !== '' && strlen($value) <= 256 && preg_match('/[\p{C}\p{Z}\s]/u', $value) === 0;
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['contract' => self::CONTRACT, 'status' => $status, 'reason' => $reason,
            'ledger_posted' => false, 'credit_appropriated' => false, 'debts_extinguished' => false,
            'official_homologation' => false, 'production_ready' => false];
    }
}
