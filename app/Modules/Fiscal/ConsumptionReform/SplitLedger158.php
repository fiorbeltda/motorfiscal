<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Reconciles supplied operation records and consolidated PSP receipts; never posts an extinction. */
final readonly class SplitLedger158
{
    public const CONTRACT = 'RTC-SPLIT-LEDGER-A158-V1';

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function reconcile(array $input): array
    {
        try {
            return $this->calculate($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(),
                'missing_facts' => ['rtc.split_payment_ledger'], 'official' => false,
                'posted' => false, 'payment_performed' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null)
            && CanonicalJson::hash($this->reconcile($report['input'])) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function calculate(array $input): array
    {
        $this->require(($input['contract'] ?? null) === self::CONTRACT && ($input['currency'] ?? null) === 'BRL', 'A158_LEDGER_CONTRACT_BRL_REQUIRED');
        $this->require(in_array($input['source_kind'] ?? null, ['SYNTHETIC_TEST', 'DOCUMENTARY_INPUT'], true), 'A158_LEDGER_PROVENANCE_REQUIRED');
        $this->proof($input);
        $body = $input;
        unset($body['input_hash']);
        $this->require(($input['input_hash'] ?? null) === CanonicalJson::hash($body), 'A158_LEDGER_HASH_CONFLICT');
        foreach (['operation_id', 'document_id', 'supplier_id', 'acquirer_id'] as $key) {
            $this->require($this->identifier($input[$key] ?? null), 'A158_LEDGER_IDENTITY_REQUIRED');
        }
        $known = $this->instant($input['known_at'] ?? null);
        $history = $this->instant($input['history_as_of'] ?? null);
        $this->require($history <= $known && ($input['schedule_complete'] ?? null) === true
            && ($input['reports_complete'] ?? null) === true && ($input['remittances_complete'] ?? null) === true
            && ($input['applications_complete'] ?? null) === true && ($input['excess_complete'] ?? null) === true
            && ($input['transfers_complete'] ?? null) === true, 'A158_COMPLETE_LEDGER_REQUIRED');
        foreach (['transactions', 'reports', 'remittances', 'applications', 'excess_determinations', 'transfers', 'prior_receipt_ids'] as $key) {
            $this->require(is_array($input[$key] ?? null) && array_is_list($input[$key]), 'A158_LEDGER_LIST_REQUIRED:'.$key);
        }
        $operationAmount = $this->money($input['operation_amount'] ?? null);
        $this->require($operationAmount->isPositive() && $input['transactions'] !== [], 'A158_LEDGER_OPERATION_AMOUNT_REQUIRED');
        $debits = $this->twoTaxes($input['debits'] ?? null);
        $prior = $this->twoTaxes($input['previously_extinguished'] ?? null);
        $receipts = [];
        foreach ($input['prior_receipt_ids'] as $id) {
            $this->unique($id, $receipts);
        }
        $taxes = [];
        foreach (['IBS', 'CBS'] as $tax) {
            $this->require($prior[$tax]->compareTo($debits[$tax]) <= 0
                && (! $prior[$tax]->isPositive() || $input['prior_receipt_ids'] !== []), 'A158_LEDGER_PRIOR_EXTINCTION_CONFLICT');
            $taxes[$tax] = ['debit' => $debits[$tax], 'prior' => $prior[$tax], 'allocated' => $this->zero(),
                'segregated' => $this->zero(), 'reported' => $this->zero(), 'applied' => $this->zero(),
                'split_applied' => $this->zero(), 'other_applied' => $this->zero(), 'excess' => $this->zero(), 'transferred' => $this->zero()];
        }
        $transactions = [];
        $transactionReports = [];
        $numbers = [];
        $settlementReceipts = [];
        $originalTotal = $this->zero();
        $count = count($input['transactions']);
        $allocationFindings = [];
        foreach ($input['transactions'] as $transaction) {
            $this->require(is_array($transaction), 'A158_LEDGER_TRANSACTION_REQUIRED');
            $this->bind($transaction, $input);
            $this->require(($transaction['source_kind'] ?? null) === $input['source_kind']
                && $this->instant($transaction['known_at'] ?? null) == $known, 'A158_LEDGER_TRANSACTION_KNOWLEDGE_CONFLICT');
            $assessment = (new SplitTransaction158)->assess($transaction);
            $this->require($assessment['status'] === 'LOCAL_SPLIT_TRANSACTION_ASSESSED', 'A158_LEDGER_TRANSACTION_REFUSED');
            if ($assessment['segregation'] !== null) {
                $this->unique($transaction['settlement']['receipt_id'], $settlementReceipts);
            }
            $id = $transaction['transaction_id'];
            $this->require(! isset($transactions['#'.$id]), 'A158_LEDGER_DUPLICATE_TRANSACTION');
            $number = $transaction['installment']['number'];
            $this->require($transaction['installment']['count'] === $count && ! isset($numbers[$number]), 'A158_LEDGER_INSTALLMENT_SET_CONFLICT');
            $numbers[$number] = true;
            $original = $this->money($transaction['original_amount']);
            $originalTotal = $originalTotal->plus($original);
            foreach (['IBS', 'CBS'] as $tax) {
                $allocation = $this->money($transaction['taxes'][$tax]);
                $ratio = BigRational::of($debits[$tax])->multipliedBy($original)->dividedBy($operationAmount);
                if ($ratio->compareTo($allocation) !== 0) {
                    $allocationFindings[] = ['transaction_id' => $id, 'tax' => $tax,
                        'supplied_allocation' => (string) $allocation, 'exact_proportional_amount' => (string) $ratio,
                        'supplied_minus_exact' => (string) BigRational::of($allocation)->minus($ratio),
                        'status' => 'PROPORTIONALITY_NOT_PROVEN_NO_RESIDUAL_REALLOCATION'];
                }
                $taxes[$tax]['allocated'] = $taxes[$tax]['allocated']->plus($allocation);
                if ($assessment['segregation'] !== null) {
                    $this->require($this->instant($assessment['settled_at']) >= $history, 'A158_LEDGER_SETTLEMENT_OVERLAPS_OPENING_HISTORY');
                    $taxes[$tax]['segregated'] = $taxes[$tax]['segregated']->plus($assessment['segregation']['taxes'][$tax]['segregated']);
                }
            }
            $transactions['#'.$id] = ['input' => $transaction, 'assessment' => $assessment];
            $transactionReports[] = $assessment;
        }
        $this->require($originalTotal->compareTo($operationAmount) === 0, 'A158_LEDGER_SCHEDULE_PRINCIPAL_CONFLICT');
        foreach (['IBS', 'CBS'] as $tax) {
            $this->require($taxes[$tax]['allocated']->compareTo($debits[$tax]) === 0, 'A158_LEDGER_SCHEDULE_DEBIT_CONFLICT');
        }
        $reports = [];
        $reportedTransactions = [];
        $acks = [];
        foreach ($input['reports'] as $report) {
            $this->require(is_array($report), 'A158_LEDGER_REPORT_REQUIRED');
            $this->proof($report);
            $id = $report['id'] ?? null;
            $this->require($this->identifier($id) && ! isset($reports['#'.$id]), 'A158_LEDGER_REPORT_ID_CONFLICT');
            $this->unique($report['authority_acknowledgement_id'] ?? null, $acks);
            $sent = $this->instant($report['sent_at'] ?? null);
            $this->require($sent <= $known && $this->identifier($report['receiver_psp_id'] ?? null)
                && $this->date($report['reference_date'] ?? null)
                && $report['reference_date'] <= $sent->setTimezone(new DateTimeZone('-03:00'))->format('Y-m-d')
                && is_array($report['transaction_ids'] ?? null) && array_is_list($report['transaction_ids'])
                && $report['transaction_ids'] !== [], 'A158_LEDGER_REPORT_SCOPE_REQUIRED');
            $own = ['IBS' => $this->zero(), 'CBS' => $this->zero()];
            foreach ($report['transaction_ids'] as $transactionId) {
                $this->require($this->identifier($transactionId) && isset($transactions['#'.$transactionId])
                    && ! isset($reportedTransactions['#'.$transactionId]), 'A158_LEDGER_REPORT_TRANSACTION_CONFLICT');
                $record = $transactions['#'.$transactionId];
                $this->require($record['assessment']['segregation'] !== null
                    && $record['input']['receiver_psp_id'] === $report['receiver_psp_id']
                    && $record['input']['arrangement'] === ($report['arrangement'] ?? null)
                    && $this->instant($record['assessment']['settled_at']) <= $sent, 'A158_LEDGER_REPORT_BEFORE_OR_OUTSIDE_SETTLEMENT');
                $reportedTransactions['#'.$transactionId] = true;
                foreach (['IBS', 'CBS'] as $tax) {
                    $own[$tax] = $own[$tax]->plus($record['assessment']['segregation']['taxes'][$tax]['segregated']);
                }
            }
            $claimed = $this->twoTaxes($report['operation_taxes'] ?? null);
            $external = $this->twoTaxes($report['other_operation_taxes'] ?? null);
            $batch = $this->twoTaxes($report['batch_taxes'] ?? null);
            foreach (['IBS', 'CBS'] as $tax) {
                $this->require($claimed[$tax]->compareTo($own[$tax]) === 0
                    && $own[$tax]->plus($external[$tax])->compareTo($batch[$tax]) === 0, 'A158_LEDGER_REPORT_AMOUNTS_CONFLICT');
                $taxes[$tax]['reported'] = $taxes[$tax]['reported']->plus($own[$tax]);
            }
            $reports['#'.$id] = ['input' => $report, 'sent' => $sent, 'own' => $own, 'batch' => $batch,
                'group' => CanonicalJson::hash([$report['receiver_psp_id'], $report['arrangement'], $report['reference_date']])];
        }
        $remittances = [];
        $usedGroups = [];
        $remittanceReports = [];
        foreach ($input['remittances'] as $remittance) {
            $this->require(is_array($remittance), 'A158_LEDGER_REMITTANCE_REQUIRED');
            $this->proof($remittance);
            $id = $remittance['id'] ?? null;
            $tax = $remittance['tax'] ?? null;
            $this->require($this->identifier($id) && ! isset($remittances['#'.$id]) && in_array($tax, ['IBS', 'CBS'], true), 'A158_LEDGER_REMITTANCE_ID_OR_TAX_CONFLICT');
            $this->unique($remittance['authority_receipt_id'] ?? null, $receipts);
            $received = $this->instant($remittance['received_at'] ?? null);
            $this->require($received <= $known && ($remittance['rail'] ?? null) === ($tax === 'IBS' ? 'STR' : 'TES')
                && is_array($remittance['report_ids'] ?? null) && array_is_list($remittance['report_ids'])
                && $remittance['report_ids'] !== [], 'A158_LEDGER_REMITTANCE_SCOPE_REQUIRED');
            $expected = $this->zero();
            $own = $this->zero();
            $group = null;
            $usedReports = [];
            foreach ($remittance['report_ids'] as $reportId) {
                $this->require($this->identifier($reportId) && isset($reports['#'.$reportId]) && ! isset($usedReports['#'.$reportId]), 'A158_LEDGER_REMITTANCE_REPORT_CONFLICT');
                $usedReports['#'.$reportId] = true;
                $report = $reports['#'.$reportId];
                $group ??= $report['group'];
                $this->require($group === $report['group'] && $report['sent'] <= $received, 'A158_LEDGER_REMITTANCE_GROUP_OR_TIME_CONFLICT');
                foreach (['receiver_psp_id', 'arrangement', 'reference_date'] as $key) {
                    $this->require(($remittance[$key] ?? null) === $report['input'][$key], 'A158_LEDGER_REMITTANCE_BINDING_CONFLICT');
                }
                $expected = $expected->plus($report['batch'][$tax]);
                $own = $own->plus($report['own'][$tax]);
            }
            foreach ($reports as $key => $report) {
                $this->require($report['group'] !== $group || isset($usedReports[$key]), 'A158_LEDGER_INCOMPLETE_CONSOLIDATION_GROUP');
            }
            $this->require(! isset($usedGroups[$group.':'.$tax]), 'A158_LEDGER_DUPLICATE_CONSOLIDATED_REMITTANCE');
            $usedGroups[$group.':'.$tax] = true;
            $amount = $this->money($remittance['amount'] ?? null);
            $remittances['#'.$id] = ['input' => $remittance, 'received' => $received, 'amount' => $amount,
                'own' => $own, 'applied' => $this->zero(), 'excess' => $this->zero(), 'tax' => $tax];
            $remittanceReports[] = ['id' => $id, 'tax' => $tax, 'batch_expected' => (string) $expected,
                'batch_received' => (string) $amount, 'batch_difference' => (string) $amount->minus($expected),
                'operation_reported' => (string) $own, 'batch_includes_other_operations' => $expected->compareTo($own) > 0];
        }
        $applicationIds = [];
        $applicationEvents = [];
        foreach ($input['applications'] as $application) {
            $this->require(is_array($application), 'A158_LEDGER_APPLICATION_REQUIRED');
            $this->bind($application, $input);
            $this->proof($application);
            $this->unique($application['id'] ?? null, $applicationIds);
            $this->unique($application['application_receipt_id'] ?? null, $receipts);
            $tax = $application['tax'] ?? null;
            $modality = $application['modality'] ?? null;
            $this->require(in_array($tax, ['IBS', 'CBS'], true) && ($application['applied'] ?? null) === true
                && in_array($modality, ['SPLIT_PAYMENT', 'COMPENSATION', 'SUPPLIER_PAYMENT', 'ACQUIRER_PAYMENT', 'RESPONSIBLE_PAYMENT'], true), 'A158_LEDGER_APPLIED_EVENT_REQUIRED');
            $at = $this->instant($application['applied_at'] ?? null);
            $this->require($at >= $history && $at <= $known, 'A158_LEDGER_APPLICATION_DATE_CONFLICT');
            $amount = $this->money($application['amount'] ?? null);
            if ($modality === 'SPLIT_PAYMENT') {
                $key = $this->referenceKey($application['remittance_id'] ?? null);
                $this->require(isset($remittances[$key]) && $remittances[$key]['tax'] === $tax
                    && $at >= $remittances[$key]['received'], 'A158_LEDGER_APPLICATION_REMITTANCE_CONFLICT');
                $remittances[$key]['applied'] = $remittances[$key]['applied']->plus($amount);
                $taxes[$tax]['split_applied'] = $taxes[$tax]['split_applied']->plus($amount);
            } else {
                $taxes[$tax]['other_applied'] = $taxes[$tax]['other_applied']->plus($amount);
            }
            $taxes[$tax]['applied'] = $taxes[$tax]['applied']->plus($amount);
            $applicationEvents[] = ['tax' => $tax, 'at' => $at, 'amount' => $amount];
        }
        foreach (['IBS', 'CBS'] as $tax) {
            $remaining = $debits[$tax]->minus($prior[$tax])->minus($taxes[$tax]['applied']);
            $this->require(! $remaining->isNegative(), 'A158_LEDGER_APPLICATION_EXCEEDS_DEBIT');
            $taxes[$tax]['remaining'] = $remaining;
        }
        $excesses = [];
        foreach ($input['excess_determinations'] as $excess) {
            $this->require(is_array($excess), 'A158_LEDGER_EXCESS_REQUIRED');
            $this->bind($excess, $input);
            $this->proof($excess);
            $id = $excess['id'] ?? null;
            $this->require($this->identifier($id) && ! isset($excesses['#'.$id]), 'A158_LEDGER_EXCESS_ID_CONFLICT');
            $this->unique($excess['authority_receipt_id'] ?? null, $receipts);
            $key = $this->referenceKey($excess['remittance_id'] ?? null);
            $tax = $excess['tax'] ?? null;
            $this->require(isset($remittances[$key]) && $remittances[$key]['tax'] === $tax, 'A158_LEDGER_EXCESS_REMITTANCE_CONFLICT');
            $at = $this->instant($excess['determined_at'] ?? null);
            $this->require($at >= $remittances[$key]['received'] && $at <= $known, 'A158_LEDGER_EXCESS_DATE_CONFLICT');
            $amount = $this->money($excess['amount'] ?? null);
            $remainingAtDetermination = $debits[$tax]->minus($prior[$tax]);
            foreach ($applicationEvents as $event) {
                if ($event['tax'] === $tax && $event['at'] <= $at) {
                    $remainingAtDetermination = $remainingAtDetermination->minus($event['amount']);
                }
            }
            $this->require(! $amount->isPositive() || $remainingAtDetermination->isZero(), 'A158_LEDGER_EXCESS_WITH_OUTSTANDING_DEBT_CONFLICT');
            $remittances[$key]['excess'] = $remittances[$key]['excess']->plus($amount);
            $taxes[$tax]['excess'] = $taxes[$tax]['excess']->plus($amount);
            $excesses['#'.$id] = ['tax' => $tax, 'at' => $at, 'amount' => $amount, 'transferred' => $this->zero()];
        }
        foreach ($remittances as $remittance) {
            $attributed = $remittance['applied']->plus($remittance['excess']);
            $this->require($attributed->compareTo($remittance['amount']) <= 0 && $attributed->compareTo($remittance['own']) <= 0, 'A158_LEDGER_OPERATION_ATTRIBUTION_EXCEEDS_FUNDS');
        }
        $transferIds = [];
        foreach ($input['transfers'] as $transfer) {
            $this->require(is_array($transfer), 'A158_LEDGER_TRANSFER_REQUIRED');
            $this->bind($transfer, $input);
            $this->proof($transfer);
            $this->unique($transfer['id'] ?? null, $transferIds);
            $this->unique($transfer['authority_receipt_id'] ?? null, $receipts);
            $key = $this->referenceKey($transfer['determination_id'] ?? null);
            $tax = $transfer['tax'] ?? null;
            $this->require(isset($excesses[$key]) && $excesses[$key]['tax'] === $tax
                && ($transfer['beneficiary_id'] ?? null) === $input['supplier_id'], 'A158_LEDGER_TRANSFER_SCOPE_CONFLICT');
            $at = $this->instant($transfer['transferred_at'] ?? null);
            $this->require($at >= $excesses[$key]['at'] && $at <= $known, 'A158_LEDGER_TRANSFER_DATE_CONFLICT');
            $amount = $this->money($transfer['amount'] ?? null);
            $excesses[$key]['transferred'] = $excesses[$key]['transferred']->plus($amount);
            $this->require($excesses[$key]['transferred']->compareTo($excesses[$key]['amount']) <= 0, 'A158_LEDGER_TRANSFER_EXCEEDS_EXCESS');
            $taxes[$tax]['transferred'] = $taxes[$tax]['transferred']->plus($amount);
        }
        $balances = [];
        foreach ($taxes as $tax => $values) {
            $values['unreported'] = $values['segregated']->minus($values['reported']);
            $values['pending_transfer_to_supplier'] = $values['excess']->minus($values['transferred']);
            $balances[$tax] = array_map(static fn (BigDecimal $value): string => (string) $value, $values);
        }
        $batchDiscrepancy = false;
        foreach ($remittanceReports as &$remittanceReport) {
            $record = $remittances['#'.$remittanceReport['id']];
            $remittanceReport['operation_applied'] = (string) $record['applied'];
            $remittanceReport['operation_excess_determined'] = (string) $record['excess'];
            $remittanceReport['operation_reported_not_accounted'] = (string) $record['own']->minus($record['applied'])->minus($record['excess']);
            $batchDiscrepancy = $batchDiscrepancy || ! BigDecimal::of($remittanceReport['batch_difference'])->isZero();
        }
        unset($remittanceReport);
        $unremittedReports = false;
        foreach ($reports as $record) {
            foreach (['IBS', 'CBS'] as $tax) {
                $unremittedReports = $unremittedReports || ($record['batch'][$tax]->isPositive() && ! isset($usedGroups[$record['group'].':'.$tax]));
            }
        }
        $report = ['status' => 'LOCAL_SPLIT_LEDGER_RECONCILED', 'input' => $input, 'taxes' => $balances,
            'transactions' => $transactionReports, 'remittances' => $remittanceReports,
            'has_batch_amount_discrepancy' => $batchDiscrepancy, 'has_unremitted_reports' => $unremittedReports,
            'schedule_status' => $allocationFindings === [] ? 'COMPLETE_EXACT_PROPORTIONAL_SUPPLIED_SCHEDULE' : 'COMPLETE_SUPPLIED_SCHEDULE_PROPORTIONALITY_NOT_PROVEN',
            'allocation_proportionality_proven' => $allocationFindings === [], 'allocation_findings' => $allocationFindings,
            'extinction_status' => 'FROM_SUPPLIED_AUTHORITY_APPLICATION_RECORDS_NOT_POSTED',
            'deadline_status' => 'QUALIFIED_CALENDAR_AND_REPORTING_WINDOWS_REQUIRED', 'deadline_date' => null,
            'psp_role' => 'OPERATIONAL_SEGREGATION_AND_REMITTANCE', 'psp_role_creates_underlying_taxpayer_status' => false,
            'credit_appropriated' => false, 'official' => false, 'authenticated' => false, 'deployment_proven' => false,
            'posted' => false, 'payment_performed' => false];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }

    /** @param array<string, mixed> $record
     * @param  array<string, mixed>  $input
     */
    private function bind(array $record, array $input): void
    {
        foreach (['operation_id', 'document_id', 'supplier_id', 'acquirer_id'] as $key) {
            $this->require(($record[$key] ?? null) === $input[$key], 'A158_LEDGER_OPERATION_BINDING_CONFLICT');
        }
    }

    /** @param array<string, mixed> $record */
    private function proof(array $record): void
    {
        $this->require($this->identifier($record['evidence_id'] ?? null) && is_string($record['source_hash'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $record['source_hash']) === 1, 'A158_LEDGER_SOURCE_EVIDENCE_REQUIRED');
    }

    /** @param array<string, bool> $seen */
    private function unique(mixed $id, array &$seen): void
    {
        $this->require($this->identifier($id) && ! isset($seen['#'.$id]), 'A158_LEDGER_RECEIPT_ID_CONFLICT');
        $seen['#'.$id] = true;
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && trim($value) === $value && (new CreditEvidence156)->reference($value)
            && preg_match('/\A\p{Z}|\p{Z}\z/u', $value) === 0;
    }

    private function referenceKey(mixed $value): string
    {
        $this->require(is_string($value) && $this->identifier($value), 'A158_LEDGER_RECORD_REFERENCE_REQUIRED');

        return '#'.$value;
    }

    /** @return array<string, BigDecimal> */
    private function twoTaxes(mixed $value): array
    {
        $this->require(is_array($value) && count($value) === 2 && array_key_exists('IBS', $value)
            && array_key_exists('CBS', $value), 'A158_LEDGER_TWO_TAX_COMPONENTS_REQUIRED');

        return ['IBS' => $this->money($value['IBS']), 'CBS' => $this->money($value['CBS'])];
    }

    private function zero(): BigDecimal
    {
        return BigDecimal::of('0.00');
    }

    private function money(mixed $value): BigDecimal
    {
        if (! is_string($value) || preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) !== 1) {
            throw new InvalidArgumentException('A158_LEDGER_CANONICAL_NONNEGATIVE_MONEY_REQUIRED');
        }

        return BigDecimal::of($value);
    }

    private function date(mixed $value): bool
    {
        return (new CreditEvidence156)->date($value);
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $this->require(is_string($value) && preg_match('/^\d{4}-\d\d-\d\dT(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value) === 1, 'A158_LEDGER_TIMESTAMP_REQUIRED');
        try {
            $date = new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new InvalidArgumentException('A158_LEDGER_TIMESTAMP_INVALID');
        }
        $this->require(DateTimeImmutable::getLastErrors() === false, 'A158_LEDGER_TIMESTAMP_INVALID');

        return $date;
    }

    /** @phpstan-assert true $condition */
    private function require(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($reason);
        }
    }
}
