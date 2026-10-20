<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use DateTimeImmutable;

/** Bind captured records to the already qualified final operation; local evidence is not posting authority. */
final readonly class RecordedAdvanceContext91
{
    public function __construct(private TemporalRateCatalog $rates) {}

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $taxPoint
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function reconcile(array $facts, array $taxPoint, DateTimeImmutable $knownAt, array $policy): array
    {
        if (($policy['recorded_advance_contract'] ?? null) !== 'RTC-RECORDED-ADVANCE-2026.10.15-91') {
            return $this->stop('RTC_RECORDED_ADVANCE_POLICY_NOT_MATERIALIZED');
        }
        $available = (new TaxPointContext86)->policyAvailability($knownAt, $policy);
        if ($available['status'] !== 'RESOLVED') {
            return [...$available, 'posting_authorized' => false, 'credit_appropriation_authorized' => false];
        }
        if (($taxPoint['status'] ?? null) !== 'RESOLVED' || ($taxPoint['requires_advance_reconciliation'] ?? null) !== true
            || ($facts['rtc.assess_tax_point_events'] ?? null) !== true || ($facts['rtc.gross_currency'] ?? null) !== 'BRL') {
            return $this->stop('RTC_RECORDED_ADVANCE_FINAL_CONTEXT_REQUIRED');
        }
        $qualified = (new TaxPointContext86)->qualify($facts, $knownAt, $policy);
        if ($qualified['status'] !== 'RESOLVED') {
            return [...$qualified, 'posting_authorized' => false, 'credit_appropriation_authorized' => false];
        }
        if (CanonicalJson::hash($qualified) !== CanonicalJson::hash($taxPoint)) {
            return $this->stop('RTC_RECORDED_ADVANCE_QUALIFIER_RESULT_BINDING_INVALID');
        }
        $finals = $facts['rtc.recorded_advance_final_contexts'] ?? null;
        $books = $facts['rtc.prior_advance_books'] ?? null;
        $captures = $facts['rtc.prior_advance_captures'] ?? null;
        if (! is_array($finals) || ! array_is_list($finals) || count($finals) !== 1 || ! is_array($finals[0])
            || ! is_array($books) || ! array_is_list($books) || count($books) !== 1 || ! is_array($books[0])
            || ! is_array($captures) || ! array_is_list($captures) || $captures === []) {
            return $this->stop('RTC_RECORDED_ADVANCE_SINGLE_FINAL_BOOK_AND_CAPTURE_SET_REQUIRED');
        }
        foreach ($captures as $capture) {
            if (! is_array($capture)) {
                return $this->stop('RTC_RECORDED_ADVANCE_SINGLE_FINAL_BOOK_AND_CAPTURE_SET_REQUIRED');
            }
        }
        $final = $finals[0];
        $book = $books[0];
        foreach (['operation_id' => 'rtc.tax_point_operation_id', 'supplier_id' => 'rtc.supplier_id',
            'currency' => 'rtc.gross_currency', 'supply_date' => 'rtc.operation_date', 'total_base' => 'rtc.gross_amount',
            'final_document_id' => 'rtc.document_id', 'destination_state' => 'rtc.destination_state',
            'destination_municipality' => 'rtc.destination_municipality_ibge'] as $key => $factKey) {
            if (($final[$key] ?? null) !== ($facts[$factKey] ?? null) || ! is_string($facts[$factKey] ?? null)) {
                return $this->stop('RTC_RECORDED_ADVANCE_FINAL_FACT_BINDING_INVALID');
            }
        }
        $supply = $taxPoint['supply_resolution']['supply_event'] ?? null;
        $evidence = $final['supply_evidence'] ?? null;
        if (! is_array($supply) || ! is_array($evidence)) {
            return $this->stop('RTC_RECORDED_ADVANCE_QUALIFIED_SUPPLY_BINDING_INVALID');
        }
        foreach (['operation_id', 'document_id', 'kind', 'date', 'observed_on', 'evidence_id'] as $key) {
            if (($supply[$key] ?? null) !== ($evidence[$key] ?? null)) {
                return $this->stop('RTC_RECORDED_ADVANCE_QUALIFIED_SUPPLY_BINDING_INVALID');
            }
        }
        foreach (['agreement_id', 'supplier_id', 'currency', 'total_base'] as $key) {
            if (array_key_exists($key, $supply) && $supply[$key] !== ($evidence[$key] ?? null)) {
                return $this->stop('RTC_RECORDED_ADVANCE_QUALIFIED_SUPPLY_BINDING_INVALID');
            }
        }
        $events = $taxPoint['event_plan']['events'];
        $advances = array_values(array_filter($events, static fn (array $row): bool => $row['kind'] === 'ADVANCE'));
        $ids = array_column($advances, 'id');
        $declared = $book['complete_advance_installment_ids'] ?? null;
        if (! is_array($declared) || ! array_is_list($declared) || count(array_filter($declared, 'is_string')) !== count($declared)) {
            return $this->stop('RTC_RECORDED_ADVANCE_EVENT_SET_BINDING_INVALID');
        }
        sort($ids, SORT_STRING);
        sort($declared, SORT_STRING);
        if ($ids !== $declared || ! is_array($book['entries'] ?? null) || ! array_is_list($book['entries'])) {
            return $this->stop('RTC_RECORDED_ADVANCE_EVENT_SET_BINDING_INVALID');
        }
        $records = [];
        foreach ($book['entries'] as $row) {
            if (! is_array($row) || ! is_string($row['installment_id'] ?? null) || isset($records['ID:'.$row['installment_id']])) {
                return $this->stop('RTC_RECORDED_ADVANCE_EVENT_SET_BINDING_INVALID');
            }
            $records['ID:'.$row['installment_id']] = $row;
        }
        foreach ($advances as $event) {
            $record = $records['ID:'.$event['id']]['event'] ?? null;
            if (! is_array($record) || ($record['event_date'] ?? null) !== $event['event_date']
                || ($record['rate_date'] ?? null) !== $event['rate_date'] || ($record['base_amount'] ?? null) !== $event['base_amount']) {
                return $this->stop('RTC_RECORDED_ADVANCE_PLAN_VALUE_OR_DATE_CONFLICT');
            }
            $recordEvidence = array_values(array_filter([$record['payment_evidence_id'] ?? null, $record['payment_document_evidence_id'] ?? null], 'is_string'));
            $planEvidence = $event['evidence_ids'];
            sort($recordEvidence, SORT_STRING);
            sort($planEvidence, SORT_STRING);
            if ($recordEvidence !== $planEvidence) {
                return $this->stop('RTC_RECORDED_ADVANCE_PLAN_EVIDENCE_CONFLICT');
            }
        }
        $reconciler = new CapturedAdvanceFinalReconciler90(new PriorAdvanceRecordSet89(new RtcScenarioRunService(new FiscalEngineFactory)), $this->rates);
        $result = $reconciler->reconcile($final, $captures, $book, $knownAt);
        if ($result['status'] !== 'LOCAL_CAPTURED_ADVANCE_FINAL_RECONCILIATION_ONLY') {
            return $this->stop('RTC_RECORDED_ADVANCE_'.$result['reason']);
        }

        return [...$result, 'source_hashes' => $available['source_hashes'],
            'qualified_final_date_basis' => $taxPoint['supply_resolution']['basis']];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'posting_authorized' => false,
            'credit_appropriation_authorized' => false, 'prior_advance_debits_proven' => false, 'production_ready' => false];
    }
}
