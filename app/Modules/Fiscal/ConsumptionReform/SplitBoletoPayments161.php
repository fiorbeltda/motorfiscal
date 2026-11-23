<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use InvalidArgumentException;

/** One supplied boleto/installment, with distinct frozen payment snapshots. Never posts a discharge. */
final readonly class SplitBoletoPayments161
{
    public const CONTRACT = 'RTC-SPLIT-BOLETO-PAYMENTS-A161-V1';

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        try {
            return $this->qualify($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(),
                'missing_facts' => ['rtc.split_payment_ledger'], 'official' => false, 'posted' => false, 'payment_performed' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null)
            && CanonicalJson::hash($this->assess($report['input'])) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function qualify(array $input): array
    {
        $fields = ['contract', 'currency', 'source_kind', 'source_hash', 'evidence_id', 'input_hash',
            'operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'transaction_id', 'receiver_psp_id',
            'operation_at', 'known_at', 'original_amount', 'taxes', 'installment', 'history_complete', 'current_snapshot', 'payments'];
        $this->require(count($input) === count($fields) && array_diff($fields, array_keys($input)) === [], 'A161_BOLETO_FIELDS_REQUIRED');
        $this->require($input['contract'] === self::CONTRACT && $input['currency'] === 'BRL', 'A161_BOLETO_CONTRACT_REQUIRED');
        $body = $input;
        unset($body['input_hash']);
        $this->require($input['input_hash'] === CanonicalJson::hash($body), 'A161_BOLETO_HASH_CONFLICT');
        $this->require(in_array($input['source_kind'], ['SYNTHETIC_TEST', 'DOCUMENTARY_INPUT'], true)
            && $this->identifier($input['evidence_id']) && is_string($input['source_hash'])
            && preg_match('/^[a-f0-9]{64}$/D', $input['source_hash']) === 1, 'A161_BOLETO_SOURCE_REQUIRED');
        $known = $this->instant($input['known_at']);
        $snapshot = $input['current_snapshot'];
        $this->require(is_array($snapshot) && array_key_exists('settlement', $snapshot) && $snapshot['settlement'] === null, 'A161_CURRENT_NONFINANCIAL_SNAPSHOT_REQUIRED');
        $current = $this->transaction($snapshot);
        $this->bind($snapshot, $input);
        $this->require($this->instant($snapshot['known_at'] ?? null) == $known
            && ($snapshot['original_amount'] ?? null) === $input['original_amount']
            && CanonicalJson::hash($snapshot['taxes'] ?? null) === CanonicalJson::hash($input['taxes'])
            && CanonicalJson::hash($snapshot['installment'] ?? null) === CanonicalJson::hash($input['installment']), 'A161_CURRENT_SNAPSHOT_CONFLICT');
        $rows = $input['payments'];
        $this->require($input['history_complete'] === true && is_array($rows) && array_is_list($rows) && $rows !== [], 'A161_COMPLETE_PAYMENT_HISTORY_REQUIRED');
        $ordered = [];
        foreach ($rows as $row) {
            $this->require(is_array($row) && is_int($row['sequence'] ?? null) && $row['sequence'] > 0
                && ! isset($ordered[$row['sequence']]), 'A161_PAYMENT_SEQUENCE_CONFLICT');
            $ordered[$row['sequence']] = $row;
        }
        ksort($ordered);
        $ids = [];
        $receipts = [];
        $paid = BigDecimal::of('0.00');
        $taxes = ['IBS' => BigDecimal::of('0.00'), 'CBS' => BigDecimal::of('0.00')];
        $payments = [];
        $firstAt = null;
        $lastAt = null;
        $integral = false;
        $lastClose = null;
        $states = [];
        foreach (array_values($ordered) as $index => $row) {
            $this->require(count($row) === 4 && array_diff(['payment_id', 'sequence', 'integral_payment', 'transaction'], array_keys($row)) === []
                && $row['sequence'] === $index + 1 && $this->identifier($row['payment_id'])
                && ! isset($ids['#'.$row['payment_id']]) && is_bool($row['integral_payment'])
                && ! $integral && is_array($row['transaction']), 'A161_PAYMENT_ID_ORDER_OR_INTEGRAL_CONFLICT');
            $ids['#'.$row['payment_id']] = true;
            $transaction = $row['transaction'];
            $report = $this->transaction($transaction);
            $this->bind($transaction, $input);
            $this->require($report['segregation'] !== null && is_array($transaction['settlement'] ?? null)
                && $this->instant($transaction['known_at'] ?? null) <= $known
                && $this->instant($transaction['initiated_at'] ?? null) == $this->instant($snapshot['initiated_at'] ?? null)
                && ($transaction['original_payer_id'] ?? null) === ($snapshot['original_payer_id'] ?? null)
                && ($transaction['installment']['number'] ?? null) === ($snapshot['installment']['number'] ?? null)
                && ($transaction['installment']['count'] ?? null) === ($snapshot['installment']['count'] ?? null), 'A161_PAYMENT_SNAPSHOT_CONFLICT');
            $receipt = $transaction['settlement']['receipt_id'];
            $this->require(! isset($receipts['#'.$receipt]), 'A161_DUPLICATE_SETTLEMENT_RECEIPT');
            $receipts['#'.$receipt] = true;
            $at = $this->instant($report['settled_at']);
            $this->require($lastAt === null || $at >= $lastAt, 'A161_PAYMENT_CHRONOLOGY_CONFLICT');
            $firstAt ??= $at;
            $lastAt = $at;
            $integral = $row['integral_payment'];
            if ($integral) {
                $this->require(($transaction['platform_window_close_reason'] ?? null) === 'FULL_PAYMENT_REPORTED'
                    && array_key_exists('platform_window_closed_at', $transaction), 'A161_INTEGRAL_PAYMENT_CLOSE_PROOF_REQUIRED');
                $lastClose = $this->instant($transaction['platform_window_closed_at']);
            } else {
                $this->require(! array_key_exists('platform_window_closed_at', $transaction), 'A161_PARTIAL_PAYMENT_CANNOT_CLOSE_WINDOW');
            }
            $paid = $paid->plus($transaction['settlement']['paid_amount']);
            foreach (['IBS', 'CBS'] as $tax) {
                $taxes[$tax] = $taxes[$tax]->plus($report['segregation']['taxes'][$tax]['segregated']);
            }
            $payments[] = ['payment_id' => $row['payment_id'], 'sequence' => $row['sequence'],
                'integral_payment' => $integral, 'transaction' => $transaction, 'assessment' => $report];
            $states[] = ['input' => $transaction, 'cutoff' => $this->instant($transaction['settlement']['calculation_frozen_at'])];
        }
        $states[] = ['input' => $snapshot, 'cutoff' => $known];
        usort($states, static fn (array $left, array $right): int => $left['cutoff'] <=> $right['cutoff']);
        for ($index = 1; $index < count($states); $index++) {
            $this->continuity($states[$index - 1]['input'], $states[$index]['input'], $states[$index - 1]['cutoff']);
        }
        if ($integral) {
            $this->require(($snapshot['platform_window_close_reason'] ?? null) === 'FULL_PAYMENT_REPORTED'
                && $this->instant($snapshot['platform_window_closed_at'] ?? null) == $lastClose, 'A161_CURRENT_CLOSURE_CONFLICT');
        } else {
            $this->require(($snapshot['platform_window_close_reason'] ?? null) !== 'FULL_PAYMENT_REPORTED', 'A161_UNPROVEN_CURRENT_INTEGRAL_STATE');
            if (($snapshot['platform_window_close_reason'] ?? null) === 'CANCELLED') {
                $this->require($this->instant($snapshot['platform_window_closed_at'] ?? null) >= $lastAt, 'A161_CANCELLATION_BEFORE_SUPPLIED_PAYMENT');
            }
        }
        $report = ['status' => 'LOCAL_SPLIT_BOLETO_PAYMENTS_ASSESSED', 'input' => $input,
            'financial_state' => $integral ? 'INTEGRAL_PAYMENT_REPORTED' : 'PARTIAL_PAYMENTS_RECORDED',
            'current_window_state' => $snapshot['platform_window_close_reason'] ?? 'OPEN', 'current_snapshot_assessment' => $current,
            'payment_records' => $payments, 'settled_at' => $firstAt->format('Y-m-d\TH:i:s.uP'),
            'last_settled_at' => $lastAt->format('Y-m-d\TH:i:s.uP'), 'paid_amount' => (string) $paid,
            'segregation' => ['taxes' => ['IBS' => ['segregated' => (string) $taxes['IBS']], 'CBS' => ['segregated' => (string) $taxes['CBS']]]],
            'net_after_segregation' => (string) $paid->minus($taxes['IBS'])->minus($taxes['CBS']),
            'installment_scope' => 'ONE_INSTALLMENT_MULTIPLE_FINANCIAL_PAYMENTS', 'integral_payment_is_inferred_from_amount' => false,
            'segregation_is_extinction' => false, 'official' => false, 'authenticated' => false, 'posted' => false,
            'payment_performed' => false, 'deployment_proven' => false];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function transaction(array $input): array
    {
        $this->require(($input['arrangement'] ?? null) === 'BOLETO', 'A161_MULTIPLE_PAYMENTS_ONLY_BOLETO');
        $service = new SplitTransaction158;
        $report = ($input['contract'] ?? null) === SplitTransaction158::HISTORY_CONTRACT ? $service->assessHistory($input) : $service->assess($input);
        $this->require($report['status'] === 'LOCAL_SPLIT_TRANSACTION_ASSESSED', 'A161_PAYMENT_TRANSACTION_REFUSED');

        return $report;
    }

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     */
    private function continuity(array $before, array $after, DateTimeImmutable $beforeCutoff): void
    {
        $afterHistory = ($after['contract'] ?? null) === SplitTransaction158::HISTORY_CONTRACT;
        if (! $afterHistory) {
            $this->require(($before['contract'] ?? null) !== SplitTransaction158::HISTORY_CONTRACT
                && $this->fiscalState($before) === $this->fiscalState($after), 'A161_FISCAL_CONTINUITY_REQUIRED');

            return;
        }
        $rows = $after['fiscal_revisions'] ?? [];
        $versions = [];
        $selected = null;
        $selectedAt = null;
        foreach ($rows as $row) {
            $versions['#'.$row['version']] = $row;
            $at = $this->instant($row['effective_at']);
            if ($at <= $beforeCutoff && ($selectedAt === null || $at > $selectedAt)) {
                $selected = $row;
                $selectedAt = $at;
            }
        }
        $this->require(is_array($selected), 'A161_PRIOR_FISCAL_REVISION_REQUIRED');
        $prior = ['fiscal_input_version' => $selected['version'], 'document_id' => $selected['document_id'],
            'original_amount' => $selected['original_amount'], 'taxes' => $selected['taxes'],
            'document_issued_at' => $selected['document_issued_at'], 'document_link' => ['linked_at' => $selected['document_linked_at']]];
        $this->require($this->fiscalState($before) === $this->fiscalState($prior), 'A161_REWRITTEN_FISCAL_SNAPSHOT');
        foreach ($before['fiscal_revisions'] ?? [] as $row) {
            $this->require(isset($versions['#'.$row['version']])
                && CanonicalJson::hash($row) === CanonicalJson::hash($versions['#'.$row['version']]), 'A161_REWRITTEN_FISCAL_HISTORY');
        }
    }

    /** @param array<string, mixed> $record */
    private function fiscalState(array $record): string
    {
        return CanonicalJson::hash([$record['fiscal_input_version'] ?? null, $record['document_id'] ?? null,
            $record['original_amount'] ?? null, $record['taxes'] ?? null,
            $this->instant($record['document_issued_at'] ?? null)->format('U.u'),
            $this->instant($record['document_link']['linked_at'] ?? null)->format('U.u')]);
    }

    /** @param array<string, mixed> $record
     * @param  array<string, mixed>  $input
     */
    private function bind(array $record, array $input): void
    {
        foreach (['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'transaction_id', 'receiver_psp_id', 'currency', 'source_kind'] as $key) {
            $this->require(($record[$key] ?? null) === $input[$key], 'A161_PAYMENT_BINDING_CONFLICT');
        }
        $this->require($this->instant($record['operation_at'] ?? null) == $this->instant($input['operation_at']), 'A161_PAYMENT_OPERATION_DATE_CONFLICT');
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && trim($value) === $value && (new CreditEvidence156)->reference($value)
            && preg_match('/\A\p{Z}|\p{Z}\z/u', $value) === 0;
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $this->require(is_string($value) && preg_match('/^\d{4}-\d\d-\d\dT(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value) === 1, 'A161_TIMESTAMP_REQUIRED');
        try {
            $at = new DateTimeImmutable($value);
        } catch (\Exception) {
            throw new InvalidArgumentException('A161_TIMESTAMP_INVALID');
        }
        $this->require(DateTimeImmutable::getLastErrors() === false, 'A161_TIMESTAMP_INVALID');

        return $at;
    }

    /** @phpstan-assert true $condition */
    private function require(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($reason);
        }
    }
}
