<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Qualifies a supplied transaction history. Never authenticates or posts a payment. */
final readonly class SplitTransaction158
{
    public const CONTRACT = 'RTC-SPLIT-TRANSACTION-A158-V1';

    public const TIMES_MANUAL_HASH = '13685b4d1a2f486cd092efb34c83e741b4300edf9692b09ffb6ce0f27036bdf1';

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        try {
            return $this->qualify($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(),
                'missing_facts' => ['rtc.split_payment_transaction'], 'official' => false,
                'posted' => false, 'payment_performed' => false];
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
        $this->require(($input['contract'] ?? null) === self::CONTRACT, 'A158_TRANSACTION_CONTRACT_REQUIRED');
        $this->require(($input['operations_manual_hash'] ?? null) === SplitPayment158::OPERATIONS_MANUAL_HASH
            && ($input['times_manual_hash'] ?? null) === self::TIMES_MANUAL_HASH, 'A158_TRANSACTION_MANUALS_REQUIRED');
        $this->require(($input['procedure'] ?? null) === 'STANDARD' && ($input['currency'] ?? null) === 'BRL', 'A158_STANDARD_BRL_REQUIRED');
        $this->require(in_array($input['source_kind'] ?? null, ['SYNTHETIC_TEST', 'DOCUMENTARY_INPUT'], true), 'A158_TRANSACTION_PROVENANCE_REQUIRED');
        $body = $input;
        unset($body['input_hash']);
        $this->require(($input['input_hash'] ?? null) === CanonicalJson::hash($body), 'A158_TRANSACTION_HASH_CONFLICT');
        foreach (['transaction_id', 'operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'original_payer_id', 'effective_payer_id', 'receiver_psp_id', 'evidence_id', 'fiscal_input_version'] as $key) {
            $this->require($this->identifier($input[$key] ?? null), 'A158_TRANSACTION_ID_REQUIRED:'.$key);
        }
        $this->hash($input['source_hash'] ?? null);
        $known = $this->instant($input['known_at'] ?? null);
        $operation = $this->instant($input['operation_at'] ?? null);
        $initiated = $this->instant($input['initiated_at'] ?? null);
        $issued = $this->instant($input['document_issued_at'] ?? null);
        $this->require($operation <= $known && $issued <= $known && $initiated <= $known, 'A158_TRANSACTION_FUTURE_EVENT');
        $this->require(($input['platform_history_complete'] ?? null) === true
            && ($input['fiscal_inputs_unchanged'] ?? null) === true, 'A158_COMPLETE_STABLE_INPUT_HISTORY_REQUIRED');
        $arrangement = $input['arrangement'] ?? null;
        $receiver = in_array($arrangement, ['BOLETO', 'PIX_DYNAMIC', 'PIX_AUTOMATIC'], true);
        $this->require($receiver || in_array($arrangement, ['PIX_STATIC', 'TED', 'TEF'], true), 'A158_TRANSACTION_ARRANGEMENT_UNSUPPORTED');
        $this->require(($input['originator'] ?? null) === ($receiver ? 'RECEIVER' : 'PAYER'), 'A158_TRANSACTION_ORIGINATOR_CONFLICT');
        $this->require(($input['supplier_kind'] ?? null) === 'PJ' && ($input['original_payer_kind'] ?? null) === 'PJ'
            && ($input['supplier_cnpj_registered'] ?? null) === true && ($input['original_payer_cnpj_registered'] ?? null) === true
            && in_array($input['effective_payer_kind'] ?? null, $receiver ? ['PJ', 'PF'] : ['PJ'], true)
            && ($input['open_finance_initiated'] ?? null) === false, 'A158_PHASE1_PARTICIPANT_SCOPE_CONFLICT');
        $original = $this->money($input['original_amount'] ?? null);
        $this->require($original->isPositive(), 'A158_TRANSACTION_POSITIVE_ORIGINAL_REQUIRED');
        $this->require(is_array($input['taxes'] ?? null) && count($input['taxes']) === 2
            && array_key_exists('IBS', $input['taxes']) && array_key_exists('CBS', $input['taxes']), 'A158_TRANSACTION_TWO_TAXES_REQUIRED');
        $informed = [];
        foreach (['IBS', 'CBS'] as $tax) {
            $informed[$tax] = (string) $this->money($input['taxes'][$tax]);
        }
        $this->require(BigDecimal::of($informed['IBS'])->plus($informed['CBS'])->compareTo($original) <= 0, 'A158_TRANSACTION_TAX_SUM_EXCEEDS_ORIGINAL');
        $link = $input['document_link'] ?? null;
        $this->require(is_array($link) && ($link['document_id'] ?? null) === $input['document_id']
            && ($link['transaction_id'] ?? null) === $input['transaction_id']
            && $this->identifier($link['evidence_id'] ?? null), 'A158_DOCUMENT_LINK_REQUIRED');
        $linked = $this->instant($link['linked_at'] ?? null);
        $this->require($issued <= $linked && $linked <= $known, 'A158_DOCUMENT_LINK_DATE_CONFLICT');
        $this->require(in_array($link['channel'] ?? null, $receiver ? ['ARRANGEMENT', 'GOVERNMENT'] : ['ARRANGEMENT'], true), 'A158_DOCUMENT_LINK_CHANNEL_CONFLICT');
        $this->require($receiver || $linked <= $initiated, 'A158_PAYER_DOCUMENT_MUST_PRECEDE_INITIATION');
        $installment = $input['installment'] ?? null;
        $this->require(is_array($installment) && is_int($installment['number'] ?? null) && is_int($installment['count'] ?? null)
            && $installment['number'] >= 1 && $installment['number'] <= $installment['count']
            && ($installment['document_id'] ?? null) === $input['document_id']
            && ($installment['operation_id'] ?? null) === $input['operation_id']
            && ($installment['original_amount'] ?? null) === $input['original_amount']
            && CanonicalJson::hash($installment['taxes'] ?? null) === CanonicalJson::hash($input['taxes'])
            && $this->identifier($installment['allocation_evidence_id'] ?? null), 'A158_INSTALLMENT_ALLOCATION_REQUIRED');
        $closed = array_key_exists('platform_window_closed_at', $input) ? $this->instant($input['platform_window_closed_at']) : null;
        $this->require($closed === null || ($closed >= $initiated && $closed <= $known), 'A158_PLATFORM_WINDOW_DATE_CONFLICT');
        $this->require($closed === null || ($this->identifier($input['platform_window_close_evidence_id'] ?? null)
            && in_array($input['platform_window_close_reason'] ?? null, ['FULL_PAYMENT_REPORTED', 'CANCELLED'], true)), 'A158_PLATFORM_WINDOW_CLOSE_PROOF_REQUIRED');
        $settlement = $input['settlement'] ?? null;
        $this->require(array_key_exists('settlement', $input) && ($settlement === null || is_array($settlement)), 'A158_SETTLEMENT_STATE_REQUIRED');
        $freeze = null;
        $settled = null;
        $paid = null;
        if ($settlement !== null) {
            $this->require(($input['platform_window_close_reason'] ?? null) !== 'CANCELLED', 'A158_CANCELLED_TRANSACTION_CANNOT_SETTLE');
            $expectedMilestone = match ($arrangement) {
                'BOLETO' => 'BOLETO_PAYMENT_DISCHARGE_COMPLETE',
                'PIX_DYNAMIC', 'PIX_AUTOMATIC', 'PIX_STATIC' => 'PIX_SPI_OR_INTRABANK_SETTLED',
                'TED' => 'TED_STR_OR_SITRAF_SETTLED',
                'TEF' => 'TEF_PAYER_DEBITED',
            };
            $this->require(($settlement['milestone'] ?? null) === $expectedMilestone
                && ($settlement['transaction_id'] ?? null) === $input['transaction_id']
                && $this->identifier($settlement['receipt_id'] ?? null) && $this->identifier($settlement['evidence_id'] ?? null), 'A158_ARRANGEMENT_SETTLEMENT_PROOF_REQUIRED');
            $this->hash($settlement['source_hash'] ?? null);
            $milestone = $this->instant($settlement['milestone_at'] ?? null);
            $cleared = $this->instant($settlement['noncontractual_clear_at'] ?? null);
            $freeze = $this->instant($settlement['calculation_frozen_at'] ?? null);
            $settled = $this->instant($settlement['settled_at'] ?? null);
            $this->require($initiated <= $milestone && $initiated <= $cleared && $initiated <= $freeze
                && $freeze <= $settled && $settled <= $known && $settled == max($milestone, $cleared), 'A158_FINANCIAL_SETTLEMENT_DATE_CONFLICT');
            $this->require($linked <= $settled, 'A158_DOCUMENT_LINK_AFTER_SETTLEMENT');
            $paid = $this->money($settlement['paid_amount'] ?? null);
            $this->require($paid->isPositive(), 'A158_POSITIVE_SETTLED_PAYMENT_REQUIRED');
            if (array_key_exists('contractual_credit_available_at', $settlement)) {
                $availableAt = $this->instant($settlement['contractual_credit_available_at']);
                $this->require($availableAt >= $settled && $availableAt <= $known, 'A158_CONTRACTUAL_CREDIT_DATE_CONFLICT');
            }
        }
        $advance = $input['receivable_advance'] ?? null;
        if ($advance !== null) {
            $this->require(is_array($advance) && ($advance['transaction_id'] ?? null) === $input['transaction_id']
                && $this->identifier($advance['evidence_id'] ?? null), 'A158_ADVANCE_EVIDENCE_CONFLICT');
            $advanceAt = $this->instant($advance['at'] ?? null);
            $this->require($initiated <= $advanceAt && $advanceAt <= $known, 'A158_ADVANCE_DATE_CONFLICT');
            $this->money($advance['amount'] ?? null);
        }
        $messages = $input['platform_returns'] ?? null;
        $this->require(is_array($messages) && array_is_list($messages), 'A158_PLATFORM_RETURN_HISTORY_REQUIRED');
        $ordered = [];
        $ids = [];
        foreach ($messages as $message) {
            $this->require(is_array($message) && $receiver, 'A158_PLATFORM_RETURN_NOT_ALLOWED');
            $this->require($this->identifier($message['id'] ?? null) && ! isset($ids[$message['id']])
                && is_int($message['sequence'] ?? null) && $message['sequence'] > 0
                && ! isset($ordered[$message['sequence']]), 'A158_PLATFORM_RETURN_ID_OR_SEQUENCE_CONFLICT');
            $ids[$message['id']] = true;
            $ordered[$message['sequence']] = $message;
        }
        ksort($ordered);
        $selected = [];
        $previousOpen = [];
        $lastAvailable = $initiated;
        $upwardDeadline = $initiated->setTimezone(new DateTimeZone('-03:00'))->modify('+1 day')->setTime(0, 30);
        $trace = [];
        foreach ($ordered as $message) {
            $this->require(($message['document_id'] ?? null) === $input['document_id']
                && ($message['transaction_id'] ?? null) === $input['transaction_id']
                && ($message['fiscal_input_version'] ?? null) === $input['fiscal_input_version']
                && $this->identifier($message['evidence_id'] ?? null), 'A158_PLATFORM_RETURN_BINDING_CONFLICT');
            $this->hash($message['source_hash'] ?? null);
            $available = $this->instant($message['available_at'] ?? null);
            $this->require($available >= $lastAvailable && $available >= $linked && $available <= $known
                && ($closed === null || $available <= $closed), 'A158_PLATFORM_RETURN_WINDOW_CONFLICT');
            $lastAvailable = $available;
            $processed = ($message['processed_at'] ?? null) === null ? null : $this->instant($message['processed_at']);
            $this->require($processed === null || ($processed >= $available && $processed <= $known), 'A158_PLATFORM_PROCESSING_DATE_CONFLICT');
            $tax = $message['tax'] ?? null;
            $kind = $message['kind'] ?? null;
            $this->require(in_array($tax, ['IBS', 'CBS'], true) && in_array($kind, ['CORRECTED', 'OUTSTANDING'], true), 'A158_PLATFORM_RETURN_TYPE_CONFLICT');
            $amount = $this->money($message['amount'] ?? null);
            if ($kind === 'CORRECTED') {
                $this->require($amount->compareTo($informed[$tax]) <= 0 || $available <= $upwardDeadline, 'A158_UPWARD_CORRECTION_OUTSIDE_WINDOW');
            } else {
                $this->require(! isset($previousOpen[$tax]) || $amount->compareTo($previousOpen[$tax]) <= 0, 'A158_OUTSTANDING_MUST_NOT_INCREASE');
                $previousOpen[$tax] = $amount;
            }
            $usable = $freeze !== null && $processed !== null && $processed <= $freeze;
            if ($usable) {
                $selected[$tax][$kind] = ['amount' => (string) $amount, 'message_id' => $message['id']];
            }
            $trace[] = ['id' => $message['id'], 'sequence' => $message['sequence'], 'usable_at_freeze' => $usable];
        }
        $segregation = null;
        $net = null;
        if ($settlement !== null && $paid !== null) {
            $taxes = [];
            foreach (['IBS', 'CBS'] as $tax) {
                $taxes[$tax] = ['informed' => $informed[$tax]];
                foreach (['CORRECTED' => 'corrected', 'OUTSTANDING' => 'outstanding'] as $kind => $field) {
                    if (isset($selected[$tax][$kind])) {
                        $taxes[$tax][$field] = $selected[$tax][$kind]['amount'];
                    }
                }
            }
            $calculation = ['contract' => SplitPayment158::CONTRACT, 'manual_hash' => SplitPayment158::OPERATIONS_MANUAL_HASH,
                'procedure' => 'STANDARD', 'currency' => 'BRL', 'source_kind' => $input['source_kind'],
                'transaction_id' => $input['transaction_id'], 'arrangement' => $arrangement, 'originator' => $input['originator'],
                'original_amount' => $input['original_amount'], 'paid_amount' => (string) $paid, 'taxes' => $taxes];
            $calculation['input_hash'] = CanonicalJson::hash($calculation);
            $segregation = (new SplitPayment158)->calculateSegregation($calculation);
            $this->require($segregation['status'] === 'LOCAL_SPLIT_ARITHMETIC_CALCULATED', 'A158_TRANSACTION_ARITHMETIC_REFUSED');
            $net = $paid->minus($segregation['taxes']['IBS']['segregated'])->minus($segregation['taxes']['CBS']['segregated']);
            $this->require(! $net->isNegative(), 'A158_SEGREGATION_EXCEEDS_SETTLED_PAYMENT');
        }
        $report = ['status' => 'LOCAL_SPLIT_TRANSACTION_ASSESSED', 'input' => $input,
            'financial_state' => $settled !== null ? 'SEGREGATION_CALCULATED_FROM_SUPPLIED_SETTLEMENT'
                : (($input['platform_window_close_reason'] ?? null) === 'CANCELLED' ? 'CANCELLED_WITHOUT_SETTLEMENT' : 'WAITING_FINANCIAL_SETTLEMENT'),
            'settled_at' => $settled?->format('Y-m-d\TH:i:s.uP'), 'selected_platform_values' => $selected,
            'platform_return_trace' => $trace, 'segregation' => $segregation, 'net_after_segregation' => $net === null ? null : (string) $net,
            'advance_is_settlement' => false, 'installment_scope' => 'ONE_TRANSACTION_NOT_COMPLETE_SCHEDULE',
            'remittance_status' => 'NOT_RECONCILED', 'extinction_proven' => false, 'deployment_proven' => false,
            'official' => false, 'authenticated' => false, 'posted' => false, 'payment_performed' => false];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && trim($value) === $value && (new CreditEvidence156)->reference($value)
            && preg_match('/\A\p{Z}|\p{Z}\z/u', $value) === 0;
    }

    private function hash(mixed $value): void
    {
        $this->require(is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1, 'A158_TRANSACTION_SOURCE_HASH_REQUIRED');
    }

    private function money(mixed $value): BigDecimal
    {
        if (! is_string($value) || preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) !== 1) {
            throw new InvalidArgumentException('A158_TRANSACTION_CANONICAL_MONEY_REQUIRED');
        }

        return BigDecimal::of($value);
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $this->require(is_string($value) && preg_match('/^\d{4}-\d\d-\d\dT(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value) === 1, 'A158_TRANSACTION_TIMESTAMP_REQUIRED');
        try {
            $date = new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new InvalidArgumentException('A158_TRANSACTION_TIMESTAMP_INVALID');
        }
        $this->require(DateTimeImmutable::getLastErrors() === false, 'A158_TRANSACTION_TIMESTAMP_INVALID');

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
