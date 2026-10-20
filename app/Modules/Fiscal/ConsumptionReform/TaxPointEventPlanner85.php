<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use DateTimeImmutable;
use DateTimeZone;

/** Isolated research component. Produces event dates, not tax amounts or posting authority. */
final class TaxPointEventPlanner85
{
    /** @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    public function plan(array $operation, DateTimeImmutable $knownAt): array
    {
        $today = $knownAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        if (! $this->id($operation['id'] ?? null) || ! $this->id($operation['document_id'] ?? null)) {
            return $this->stop('OPERATION_DOCUMENT_BINDING_REQUIRED');
        }
        $mode = $operation['mode'] ?? null;
        if (! in_array($mode, ['ORDINARY', 'CONTINUOUS'], true)) {
            return $this->stop('EVENT_MODE_UNPROVEN');
        }
        // Public purchases require their own qualified statutory context, never a generic payment override.
        if (($operation['public_purchase'] ?? null) !== false) {
            return $this->stop('PUBLIC_PURCHASE_CONTEXT_REQUIRES_OWN_QUALIFICATION');
        }
        if (($operation['cancelled'] ?? null) !== false) {
            return $this->stop('CANCELLATION_REQUIRES_RECONCILIATION');
        }
        $id = $operation['id'];
        $events = [];
        $seen = [];
        $advanceIds = [];
        $parts = $operation['installments'] ?? null;
        if (! is_array($parts) || ! array_is_list($parts)) {
            return $this->stop('INSTALLMENT_LIST_REQUIRED');
        }
        $supply = $operation['supply'] ?? null;
        if ($mode === 'ORDINARY') {
            if (! $this->proof($supply, $id, $today)
                || ! in_array($supply['kind'] ?? null, ['MATERIAL_DELIVERY', 'INTANGIBLE_AVAILABILITY',
                    'SERVICE_COMPLETION', 'DOMESTIC_TRANSPORT_START', 'FOREIGN_CARGO_TRANSPORT_END',
                    'UNDOCUMENTED_DISCOVERY', 'PUBLIC_AUCTION_ACQUISITION', 'JUDICIAL_AUCTION_ACQUISITION'], true)
                || ! $this->amount($operation['total_amount'] ?? null)) {
                return $this->stop('SUPPLY_EVENT_OR_TOTAL_UNPROVEN');
            }
        }
        foreach ($parts as $part) {
            if (! is_array($part) || ! $this->id($part['id'] ?? null)
                || $part['id'] === $id.':FINAL'
                || isset($seen[$part['id']]) || ! $this->amount($part['amount'] ?? null)) {
                return $this->stop('INSTALLMENT_ID_AMOUNT_OR_UNIQUENESS_INVALID');
            }
            $seen[$part['id']] = true;
            $advanceIds[] = $part['id'];
            $payment = $part['payment'] ?? null;
            $due = $part['due'] ?? null;
            foreach ([$payment, $due] as $proof) {
                if ($proof !== null && (! $this->proof($proof, $id, $today)
                    || ($proof['installment_id'] ?? null) !== $part['id'])) {
                    return $this->stop('INSTALLMENT_EVENT_BINDING_OR_DATE_INVALID');
                }
            }
            if ($payment !== null && (($payment['kind'] ?? null) !== 'PAYMENT'
                || ($payment['amount'] ?? null) !== $part['amount'])) {
                return $this->stop('PAYMENT_KIND_OR_AMOUNT_CONFLICT');
            }
            if ($mode === 'CONTINUOUS') {
                if ($payment === null && $due === null) {
                    return $this->stop('CONTINUOUS_DUE_OR_PAYMENT_REQUIRED');
                }
                if ($due !== null && ! in_array($due['kind'] ?? null, ['RIGHT_TO_RECEIVE', 'INVOICE_CONSTITUTING_RIGHT'], true)) {
                    return $this->stop('RIGHT_TO_RECEIVE_UNPROVEN');
                }
                $date = $payment === null ? $due['date'] : ($due === null ? $payment['date'] : min($payment['date'], $due['date']));
                $events[] = ['id' => $part['id'], 'kind' => 'CONTINUOUS_INSTALLMENT',
                    'event_date' => $date, 'rate_date' => $date, 'base_amount' => $part['amount'],
                    'evidence_ids' => array_values(array_filter([$due['evidence_id'] ?? null, $payment['evidence_id'] ?? null]))];
            } else {
                if ($due !== null) {
                    return $this->stop('DUE_EVENT_IN_ORDINARY_MODE_CONFLICT');
                }
                if ($payment === null || $payment['date'] >= $supply['date']) {
                    return $this->stop('ADVANCE_REQUIRES_PAYMENT_STRICTLY_BEFORE_SUPPLY');
                }
                $rateDate = $payment['date'];
                $document = $part['payment_document'] ?? null;
                if ($document !== null) {
                    if (! $this->proof($document, $id, $today)
                        || ($document['installment_id'] ?? null) !== $part['id']
                        || ! $this->id($document['document_id'] ?? null)) {
                        return $this->stop('ADVANCE_DOCUMENT_BINDING_OR_DATE_INVALID');
                    }
                    $rateDate = min($rateDate, $document['date']);
                }
                $events[] = ['id' => $part['id'], 'kind' => 'ADVANCE', 'event_date' => $payment['date'],
                    'rate_date' => $rateDate, 'base_amount' => $part['amount'],
                    'evidence_ids' => array_values(array_filter([$payment['evidence_id'], $document['evidence_id'] ?? null]))];
            }
        }
        if ($mode === 'CONTINUOUS' && $events === []) {
            return $this->stop('CONTINUOUS_INSTALLMENTS_REQUIRED');
        }
        if ($mode === 'ORDINARY') {
            sort($advanceIds, SORT_STRING);
            $events[] = ['id' => $id.':FINAL', 'kind' => 'FINAL_SUPPLY', 'event_date' => $supply['date'],
                'rate_date' => $supply['date'], 'base_amount' => $operation['total_amount'],
                'evidence_ids' => [$supply['evidence_id']], 'reconcile_advance_ids' => $advanceIds];
        }
        usort($events, static fn (array $a, array $b): int => [$a['event_date'], $a['id']] <=> [$b['event_date'], $b['id']]);

        return ['status' => 'LOCAL_EVENT_PLAN_ONLY', 'events' => $events,
            'source_policy_qualified' => false, 'tax_amounts_calculated' => false,
            'advance_amounts_reconciled' => false, 'assessment_period_qualified' => false,
            'government_evidence_authenticated' => false, 'production_ready' => false];
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

    private function proof(mixed $proof, string $id, string $today): bool
    {
        return is_array($proof) && ($proof['operation_id'] ?? null) === $id
            && $this->id($proof['evidence_id'] ?? null) && $this->date($proof['date'] ?? null)
            && $this->date($proof['observed_on'] ?? null) && $proof['date'] <= $proof['observed_on']
            && $proof['observed_on'] <= $today;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'events' => [], 'production_ready' => false];
    }
}
