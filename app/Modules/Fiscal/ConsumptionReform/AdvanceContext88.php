<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use DateTimeImmutable;

final readonly class AdvanceContext88
{
    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $facts, DateTimeImmutable $knownAt, array $policy): array
    {
        if (($policy['advance_origin_contract'] ?? null) !== 'RTC-ADVANCE-ORIGIN-2026.10.15-88') {
            return $this->stop('RTC_ADVANCE_ORIGIN_POLICY_NOT_MATERIALIZED');
        }
        $availability = (new TaxPointContext86)->policyAvailability($knownAt, $policy);
        if ($availability['status'] !== 'RESOLVED') {
            return [...$availability, 'posting_authorized' => false, 'credit_appropriation_authorized' => false];
        }
        if (($facts['rtc.assess_tax_point_events'] ?? null) === true) {
            return $this->stop('RTC_ADVANCE_FINAL_CONTEXT_CONFLICT');
        }
        $contexts = $facts['rtc.advance_origin_contexts'] ?? null;
        if (! is_array($contexts) || ! array_is_list($contexts) || count($contexts) !== 1 || ! is_array($contexts[0])) {
            return $this->stop('RTC_ADVANCE_SINGLE_CONTEXT_REQUIRED', ['rtc.advance_origin_contexts']);
        }
        $context = $contexts[0];
        if (($context['operation_id'] ?? null) !== ($facts['rtc.tax_point_operation_id'] ?? null)
            || ! is_string($facts['rtc.tax_point_operation_id'] ?? null)
            || ($context['supplier_id'] ?? null) !== ($facts['rtc.supplier_id'] ?? null)) {
            return $this->stop('RTC_ADVANCE_OPERATION_SUPPLIER_BINDING_INVALID');
        }
        foreach (['rtc.market' => 'DOMESTIC', 'rtc.supplier_regime' => 'REGULAR', 'rtc.consideration' => 'ONEROUS',
            'rtc.tax_treatment' => 'STANDARD', 'rtc.credit_context' => 'OUTPUT', 'rtc.collection_method' => 'SUPPLIER',
            'rtc.gross_currency' => 'BRL'] as $key => $value) {
            if (($facts[$key] ?? null) !== $value) {
                return $this->stop('RTC_ADVANCE_CONTEXT_REQUIRES_SEPARATE_TREATMENT');
            }
        }
        if (! in_array($facts['rtc.operation_kind'] ?? null, ['DIGITAL', 'SERVICE', 'GOODS'], true)
            || ($facts['customer.person_type'] ?? null) === 'PUBLIC_ADMINISTRATION') {
            return $this->stop('RTC_ADVANCE_CONTEXT_REQUIRES_SEPARATE_TREATMENT');
        }
        $selectedId = $facts['rtc.tax_point_installment_id'] ?? null;
        if (! is_string($selectedId)) {
            return $this->stop('RTC_ADVANCE_INSTALLMENT_SELECTION_REQUIRED', ['rtc.tax_point_installment_id']);
        }
        $origin = (new AdvanceOnlyOrigin87)->select($context, $selectedId, $knownAt);
        if ($origin['status'] !== 'LOCAL_ADVANCE_ORIGIN_ONLY') {
            return $this->stop('RTC_ADVANCE_'.$origin['reason']);
        }
        $event = $origin['event'];
        if ($event['base_amount'] !== ($facts['rtc.gross_amount'] ?? null)
            || $event['event_date'] !== ($facts['rtc.operation_date'] ?? null)) {
            return $this->stop('RTC_ADVANCE_SELECTED_DATE_OR_GROSS_CONFLICT');
        }
        if ($event['payment_document_id'] === null
            || $event['payment_document_id'] !== ($facts['rtc.document_id'] ?? null)) {
            return $this->stop('RTC_ADVANCE_FISCAL_DOCUMENT_BINDING_REQUIRED');
        }
        if ($event['rate_date'] < $policy['valid_from'] || $event['event_date'] > $policy['valid_to']) {
            return $this->stop('RTC_ADVANCE_OUTSIDE_POLICY_SCOPE');
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_ADVANCE_ORIGIN_LOCALLY_QUALIFIED', 'missing_facts' => [],
            'context' => $context, 'selected_installment_id' => $selectedId, 'origin' => $origin,
            'source_hashes' => $availability['source_hashes'], 'posting_authorized' => false, 'production_ready' => false];
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    private function stop(string $reason, array $missing = []): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'missing_facts' => $missing,
            'posting_authorized' => false, 'production_ready' => false];
    }
}
