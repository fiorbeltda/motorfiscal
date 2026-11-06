<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * B34: binds the A158 split-payment ledger (LC214 arts. 31-35) to the engine decision behind its own gate.
 *
 * The reconciliation itself is SplitLedger158 (Agente A, commit 1f56dfa). This class only proves that the
 * ledger belongs to the operation being decided (parties, document, payment transaction, operation date,
 * calculated IBS/CBS debits and knowledge instant). It never authenticates a deployment, posts an extinction,
 * moves funds or chooses a simplified-procedure percentage.
 */
final readonly class SplitPaymentIntegrationB34
{
    public const KEY = 'split_payment_a158';

    public const FACT = 'rtc.split_payment_ledger';

    public const POLICY = ['version' => 'A158', 'integration' => 'B34', 'method' => 'SPLIT_PAYMENT', 'procedure' => 'STANDARD',
        'ledger_contract' => SplitLedger158::CONTRACT, 'transaction_contract' => SplitTransaction158::CONTRACT,
        'calculation_contract' => SplitPayment158::CONTRACT,
        'operations_manual_hash' => SplitPayment158::OPERATIONS_MANUAL_HASH,
        'times_manual_hash' => SplitTransaction158::TIMES_MANUAL_HASH, 'posting' => false];

    /** @param array<string, mixed> $codes */
    public static function present(array $codes): bool
    {
        return array_key_exists(self::KEY, $codes);
    }

    /** @param array<string, mixed> $codes */
    public static function valid(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null) && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY);
    }

    /** @param array<string, mixed> $codes */
    public static function synthetic(array $codes, FactBag $facts): bool
    {
        if (! self::valid($codes)) {
            return false;
        }
        foreach ($facts->value(self::FACT) ?? [] as $ledger) {
            if (is_array($ledger) && ($ledger['source_kind'] ?? null) === 'SYNTHETIC_TEST') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{IBS: string, CBS: string}  $debits  calculated liability of the decided operation
     * @return array{status: 'RESOLVED', missing_facts: list<string>, report: array<string, mixed>}|array{status: 'ACTION_REQUIRED', reason: string, missing_facts: list<string>}
     */
    public function integrate(FactBag $facts, string $operationDate, array $debits, DateTimeImmutable $knownAt): array
    {
        $ledgers = $facts->value(self::FACT);
        if (! is_array($ledgers) || ! array_is_list($ledgers) || count($ledgers) !== 1 || ! is_array($ledgers[0])) {
            return $this->refuse('RTC_SPLIT_LEDGER_REQUIRED');
        }
        $ledger = $ledgers[0];
        foreach (['document_id', 'supplier_id', 'acquirer_id'] as $key) {
            if (($ledger[$key] ?? null) !== $facts->value('rtc.'.$key)) {
                return $this->refuse('RTC_SPLIT_LEDGER_FACT_CONFLICT');
            }
        }
        $transactions = is_array($ledger['transactions'] ?? null) ? $ledger['transactions'] : [];
        $ids = [];
        foreach ($transactions as $transaction) {
            if (! is_array($transaction) || ! $this->sameDay($transaction['operation_at'] ?? null, $operationDate)) {
                return $this->refuse('RTC_SPLIT_LEDGER_OPERATION_DATE_CONFLICT');
            }
            $ids[] = $transaction['transaction_id'] ?? null;
        }
        if (! in_array($facts->value('rtc.payment_transaction_id'), $ids, true)) {
            return $this->refuse('RTC_SPLIT_LEDGER_TRANSACTION_CONFLICT');
        }
        if (! is_array($ledger['debits'] ?? null) || CanonicalJson::hash($ledger['debits']) !== CanonicalJson::hash($debits)) {
            return $this->refuse('RTC_SPLIT_LEDGER_DEBIT_CONFLICT');
        }
        $report = (new SplitLedger158)->reconcile($ledger);
        if ($report['status'] !== 'LOCAL_SPLIT_LEDGER_RECONCILED') {
            return $this->refuse((string) $report['reason']);
        }
        try {
            $sameKnowledge = new DateTimeImmutable((string) $ledger['known_at']) == $knownAt;
        } catch (Throwable) {
            $sameKnowledge = false;
        }
        if (! $sameKnowledge) {
            return $this->refuse('RTC_SPLIT_LEDGER_KNOWLEDGE_CONFLICT');
        }

        return ['status' => 'RESOLVED', 'missing_facts' => [], 'report' => $report];
    }

    private function sameDay(mixed $instant, string $date): bool
    {
        if (! is_string($instant)) {
            return false;
        }
        try {
            return (new DateTimeImmutable($instant))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d') === $date;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{status: 'ACTION_REQUIRED', reason: string, missing_facts: list<string>} */
    private function refuse(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'missing_facts' => [self::FACT]];
    }
}
