<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use DateTimeImmutable;

/** Source-bound selection of a relevant date. Prior-debit proof and document settlement are separate gates. */
final readonly class TaxPointContext86
{
    public const CONTRACT = 'RTC-TAX-POINT-EVENTS-2026.10.15-86';

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $facts, DateTimeImmutable $knownAt, array $policy): array
    {
        $availability = $this->policyAvailability($knownAt, $policy);
        if ($availability['status'] !== 'RESOLVED') {
            return $availability;
        }

        return $this->qualifyOperation($facts, $knownAt, $policy);
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function policyAvailability(DateTimeImmutable $knownAt, array $policy): array
    {
        $hashes = ['LC214' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde',
            'CBS_REGULATION' => '8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235',
            'IBS_REGULATION' => '9ed7032ef9c25bbee51bdb5e90f60e1ad01a2bf56cb41a28221c3db7f61d419a',
            'IBS_AMENDMENT13' => '527f5b01b2c8effd984c0334ab1c3068d3926f4f77f90d023211758ee67f7222'];
        if (($policy['contract'] ?? null) !== self::CONTRACT || ($policy['status'] ?? null) !== 'ACTIVE'
            || ($policy['valid_from'] ?? null) !== '2026-04-30' || ($policy['valid_to'] ?? null) !== '2033-12-31'
            || ($policy['available_at'] ?? null) !== '2026-10-15T14:11:36.200607+00:00'
            || ! is_array($policy['source_hashes'] ?? null) || count($policy['source_hashes']) !== count($hashes)) {
            return $this->stop('RTC_TAX_POINT_POLICY_UNPROVEN');
        }
        foreach ($hashes as $id => $hash) {
            if (($policy['source_hashes'][$id] ?? null) !== $hash) {
                return $this->stop('RTC_TAX_POINT_SOURCE_BINDING_INVALID');
            }
        }
        if ($knownAt < new DateTimeImmutable($policy['available_at'])) {
            return $this->stop('RTC_TAX_POINT_POLICY_NOT_YET_AVAILABLE');
        }
        foreach (['revoked_at', 'superseded_at'] as $key) {
            if (isset($policy[$key])) {
                $instant = is_string($policy[$key]) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $policy[$key]) : false;
                if ($instant === false || $instant->format('Y-m-d\TH:i:sP') !== $policy[$key]) {
                    return $this->stop('RTC_TAX_POINT_POLICY_LIFECYCLE_INVALID');
                }
                if ($knownAt >= $instant) {
                    return $this->stop('RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE');
                }
            }
        }

        return ['status' => 'RESOLVED', 'source_hashes' => $hashes];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function qualifyOperation(array $facts, DateTimeImmutable $knownAt, array $policy): array
    {
        $hashes = $this->policyAvailability($knownAt, $policy)['source_hashes'];
        $contexts = $facts['rtc.tax_point_contexts'] ?? null;
        if (! is_array($contexts) || ! array_is_list($contexts) || count($contexts) !== 1 || ! is_array($contexts[0])) {
            return $this->stop('RTC_TAX_POINT_SINGLE_BOUND_OPERATION_REQUIRED', ['rtc.tax_point_contexts']);
        }
        $op = $contexts[0];
        if (! is_string($facts['rtc.tax_point_operation_id'] ?? null)
            || trim($facts['rtc.tax_point_operation_id']) === ''
            || ($op['id'] ?? null) !== $facts['rtc.tax_point_operation_id']
            || ($op['document_id'] ?? null) !== ($facts['rtc.document_id'] ?? null)) {
            return $this->stop('RTC_TAX_POINT_OPERATION_DOCUMENT_CONFLICT');
        }
        // No branch may use a plain-rate event plan to bypass a special incidence, regime or credit qualification.
        foreach (['rtc.market' => 'DOMESTIC', 'rtc.supplier_regime' => 'REGULAR', 'rtc.consideration' => 'ONEROUS',
            'rtc.tax_treatment' => 'STANDARD', 'rtc.credit_context' => 'OUTPUT', 'rtc.collection_method' => 'SUPPLIER'] as $key => $value) {
            if (($facts[$key] ?? null) !== $value) {
                return $this->stop('RTC_TAX_POINT_CONTEXT_REQUIRES_SEPARATE_TREATMENT');
            }
        }
        if (($op['public_purchase'] ?? null) !== false || ($op['cancelled'] ?? null) !== false
            || ($facts['customer.person_type'] ?? null) === 'PUBLIC_ADMINISTRATION') {
            return $this->stop('RTC_TAX_POINT_PUBLIC_OR_CANCELLED_CONTEXT_REQUIRES_SEPARATE_TREATMENT');
        }
        $kind = $facts['rtc.operation_kind'] ?? null;
        $supplyKind = match ($kind) {
            'SERVICE' => 'SERVICE_COMPLETION', 'DIGITAL' => 'INTANGIBLE_AVAILABILITY',
            'GOODS' => 'MATERIAL_DELIVERY', default => null,
        };
        if ($supplyKind === null || ($op['supply_kind'] ?? null) !== $supplyKind) {
            return $this->stop('RTC_TAX_POINT_SUPPLY_KIND_CONFLICT');
        }
        $resolved = null;
        if (($op['mode'] ?? null) === 'ORDINARY') {
            $resolved = (new SupplyDateEvidenceResolver83)->resolve($op, $knownAt);
            if ($resolved['status'] !== 'LOCAL_SUPPLY_DATE_ONLY') {
                return $this->stop('RTC_TAX_POINT_'.($resolved['reason'] ?? $resolved['status']), [], ['supply_resolution' => $resolved]);
            }
            $op['supply'] = $resolved['supply_event'];
            if (($op['total_amount'] ?? null) !== ($facts['rtc.gross_amount'] ?? null)) {
                return $this->stop('RTC_TAX_POINT_TOTAL_GROSS_CONFLICT');
            }
        }
        $plan = (new TaxPointEventPlanner85)->plan($op, $knownAt);
        if ($plan['status'] !== 'LOCAL_EVENT_PLAN_ONLY') {
            return $this->stop('RTC_TAX_POINT_'.$plan['reason']);
        }
        if (($op['mode'] ?? null) === 'CONTINUOUS') {
            $id = $facts['rtc.tax_point_installment_id'] ?? null;
            $selected = array_values(array_filter($plan['events'], static fn (array $row): bool => $row['id'] === $id));
            if (count($selected) !== 1 || $selected[0]['base_amount'] !== ($facts['rtc.gross_amount'] ?? null)) {
                return $this->stop('RTC_TAX_POINT_SELECTED_INSTALLMENT_GROSS_CONFLICT', ['rtc.tax_point_installment_id']);
            }
            $event = $selected[0];
        } else {
            $selected = array_values(array_filter($plan['events'], static fn (array $row): bool => $row['kind'] === 'FINAL_SUPPLY'));
            $event = $selected[0];
        }
        $date = $event['event_date'];
        foreach ($plan['events'] as $row) {
            if ($row['event_date'] < $policy['valid_from'] || $row['rate_date'] < $policy['valid_from']
                || $row['event_date'] > $policy['valid_to']) {
                return $this->stop('RTC_TAX_POINT_EVENT_OUTSIDE_POLICY_SCOPE');
            }
        }
        if (($facts['rtc.operation_date'] ?? null) !== $date) {
            return $this->stop('RTC_TAX_POINT_DECLARED_DATE_CONFLICT', ['rtc.operation_date'], ['derived_date' => $date]);
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_TAX_POINT_DATE_LOCALLY_QUALIFIED', 'missing_facts' => [],
            'operation_date' => $date, 'operation_id' => $op['id'], 'source_hashes' => $hashes,
            'supply_resolution' => $resolved, 'event_plan' => $plan,
            'requires_advance_reconciliation' => ($op['mode'] === 'ORDINARY' && count($plan['events']) > 1),
            'selected_event_id' => $event['id'], 'government_evidence_authenticated' => false, 'production_ready' => false];
    }

    /**
     * @param  list<string>  $missing
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function stop(string $reason, array $missing = [], array $details = []): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'missing_facts' => $missing,
            ...$details, 'government_evidence_authenticated' => false, 'production_ready' => false];
    }
}
