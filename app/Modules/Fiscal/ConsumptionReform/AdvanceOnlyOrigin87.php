<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use DateTimeImmutable;
use DateTimeZone;

/** Isolated advance origin selector. Does not fabricate a completed supply, registration or extinction. */
final readonly class AdvanceOnlyOrigin87
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function select(array $context, string $installmentId, DateTimeImmutable $knownAt): array
    {
        $today = $knownAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        foreach (['operation_id', 'agreement_id', 'supplier_id'] as $key) {
            if (! $this->id($context[$key] ?? null)) {
                return $this->stop('OPERATION_AGREEMENT_SUPPLIER_REQUIRED');
            }
        }
        if (($context['mode'] ?? null) !== 'ADVANCE_ONLY' || ($context['single_ordinary_supply'] ?? null) !== true
            || ($context['currency'] ?? null) !== 'BRL' || ($context['public_purchase'] ?? null) !== false
            || ($context['cancelled'] ?? null) !== false) {
            return $this->stop('ADVANCE_CONTEXT_REQUIRES_SEPARATE_QUALIFICATION');
        }
        if (! array_key_exists('actual_supply', $context) || $context['actual_supply'] !== null) {
            return $this->stop('ACTUAL_SUPPLY_STATE_UNKNOWN_OR_FINAL_ROUTE_REQUIRED');
        }
        $state = $context['supply_state_evidence'] ?? null;
        if (! $this->proof($state, $context, $today) || ($state['kind'] ?? null) !== 'NOT_YET_SUPPLIED_AS_OF') {
            return $this->stop('NO_SUPPLY_STATE_EVIDENCE_INVALID');
        }
        $parts = $context['paid_installments'] ?? null;
        if (! is_array($parts) || ! array_is_list($parts) || $parts === [] || ! $this->id($installmentId)) {
            return $this->stop('PAID_INSTALLMENTS_AND_SELECTION_REQUIRED');
        }
        $seen = [];
        $payments = [];
        $links = [];
        $selected = null;
        foreach ($parts as $part) {
            if (! is_array($part) || ! $this->id($part['id'] ?? null) || isset($seen['ID:'.$part['id']])
                || ! $this->amount($part['amount'] ?? null)) {
                return $this->stop('INSTALLMENT_ID_AMOUNT_OR_UNIQUENESS_INVALID');
            }
            $seen['ID:'.$part['id']] = true;
            $payment = $part['payment'] ?? null;
            if (! $this->proof($payment, $context, $today) || ($payment['kind'] ?? null) !== 'PAYMENT'
                || ($payment['installment_id'] ?? null) !== $part['id'] || ($payment['amount'] ?? null) !== $part['amount']
                || ! $this->id($payment['payment_reference_id'] ?? null)
                || isset($payments['PAY:'.$payment['payment_reference_id']]) || $payment['date'] > $state['date']) {
                return $this->stop('PAYMENT_BINDING_AMOUNT_OR_SUPPLY_STATE_INVALID');
            }
            $payments['PAY:'.$payment['payment_reference_id']] = true;
            if (! array_key_exists('payment_document', $part)) {
                return $this->stop('PAYMENT_DOCUMENT_STATE_UNKNOWN');
            }
            $document = $part['payment_document'];
            if ($document !== null && (! $this->proof($document, $context, $today)
                || ($document['kind'] ?? null) !== 'FISCAL_DOCUMENT_ISSUED'
                || ($document['installment_id'] ?? null) !== $part['id']
                || ($document['payment_reference_amount'] ?? null) !== $part['amount']
                || ! $this->id($document['document_id'] ?? null) || ! $this->id($document['payment_link_id'] ?? null)
                || isset($links['LINK:'.$document['payment_link_id']]))) {
                return $this->stop('PAYMENT_DOCUMENT_LINK_OR_AMOUNT_INVALID');
            }
            if ($document !== null) {
                $links['LINK:'.$document['payment_link_id']] = true;
            }
            if ($part['id'] === $installmentId) {
                $selected = ['operation_id' => $context['operation_id'], 'agreement_id' => $context['agreement_id'],
                    'supplier_id' => $context['supplier_id'], 'installment_id' => $part['id'], 'currency' => 'BRL',
                    'event_date' => $payment['date'], 'rate_date' => $document === null ? $payment['date'] : min($document['date'], $payment['date']),
                    'base_amount' => $part['amount'], 'payment_reference_id' => $payment['payment_reference_id'],
                    'payment_evidence_id' => $payment['evidence_id'], 'payment_document_id' => $document['document_id'] ?? null,
                    'payment_document_evidence_id' => $document['evidence_id'] ?? null,
                    'supply_state_evidence_id' => $state['evidence_id'], 'supply_not_yet_occurred_as_of' => $state['date']];
            }
        }
        if ($selected === null) {
            return $this->stop('SELECTED_PAID_INSTALLMENT_NOT_FOUND');
        }

        return ['status' => 'LOCAL_ADVANCE_ORIGIN_ONLY', 'event' => $selected,
            'supply_completed' => false, 'source_policy_qualified' => false, 'prior_registration_proven' => false,
            'extinction_proven' => false, 'cash_relief_qualified' => false, 'assessment_period_qualified' => false,
            'posting_authorized' => false, 'credit_appropriation_authorized' => false, 'production_ready' => false];
    }

    private function id(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && ! str_contains($value, "\0");
    }

    private function amount(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,15})\.[0-9]{2}\z/', $value) === 1 && $value !== '0.00';
    }

    private function date(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) !== 1) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('America/Sao_Paulo'));

        return $date !== false && $date->format('Y-m-d') === $value && $value >= '2026-01-01';
    }

    /** @param array<string, mixed> $context */
    private function proof(mixed $proof, array $context, string $today): bool
    {
        return is_array($proof) && ($proof['operation_id'] ?? null) === $context['operation_id']
            && ($proof['agreement_id'] ?? null) === $context['agreement_id']
            && ($proof['supplier_id'] ?? null) === $context['supplier_id'] && $this->id($proof['evidence_id'] ?? null)
            && $this->date($proof['date'] ?? null) && $this->date($proof['observed_on'] ?? null)
            && $proof['date'] <= $proof['observed_on'] && $proof['observed_on'] <= $today;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'posting_authorized' => false,
            'credit_appropriation_authorized' => false, 'production_ready' => false];
    }
}
