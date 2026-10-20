<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Reconcile captured prior values; never recompute a prior debit using the final rate catalog. */
final readonly class CapturedAdvanceFinalReconciler90
{
    public function __construct(private PriorAdvanceRecordSet89 $prior, private TemporalRateCatalog $rates) {}

    /**
     * @param  array<string, mixed>  $final
     * @param  list<array<string, mixed>>  $captures
     * @param  array<string, mixed>  $book
     * @return array<string, mixed>
     */
    public function reconcile(array $final, array $captures, array $book, DateTimeImmutable $knownAt): array
    {
        // Rebind original snapshots and book here; do not trust a caller-supplied RESOLVED marker.
        $prior = $this->prior->bind($final, $captures, $book, $knownAt);
        if ($prior['status'] !== 'LOCAL_PRIOR_ADVANCE_RECORD_SET_ONLY') {
            return $this->stop('PRIOR_'.$prior['reason']);
        }
        $today = $knownAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        $evidence = $final['supply_evidence'] ?? null;
        if (! $this->id($final['final_document_id'] ?? null) || ! $this->id($final['final_decision_id'] ?? null)
            || ! in_array($final['supply_kind'] ?? null, ['SERVICE_COMPLETION', 'INTANGIBLE_AVAILABILITY', 'MATERIAL_DELIVERY'], true)
            || ! is_array($evidence) || ($evidence['kind'] ?? null) !== $final['supply_kind']
            || ! $this->id($evidence['evidence_id'] ?? null) || ! $this->date($evidence['observed_on'] ?? null)
            || $evidence['observed_on'] > $today || $evidence['observed_on'] < $final['supply_date']) {
            return $this->stop('FINAL_SUPPLY_EVIDENCE_REQUIRED');
        }
        foreach (['operation_id', 'agreement_id', 'supplier_id', 'currency'] as $key) {
            if (($evidence[$key] ?? null) !== $final[$key]) {
                return $this->stop('FINAL_SUPPLY_EVIDENCE_OWNER_CONFLICT');
            }
        }
        if (($evidence['document_id'] ?? null) !== $final['final_document_id']
            || ($evidence['date'] ?? null) !== $final['supply_date']
            || ($evidence['total_base'] ?? null) !== $final['total_base']) {
            return $this->stop('FINAL_SUPPLY_DOCUMENT_DATE_OR_BASE_CONFLICT');
        }
        $taxes = [];
        foreach (['IBS_STATE', 'IBS_MUNICIPAL', 'CBS'] as $tax) {
            $jurisdiction = $tax === 'CBS' || $final['supply_date'] < '2029-01-01' ? 'BR'
                : ($tax === 'IBS_STATE' ? $final['destination_state'] : $final['destination_municipality']);
            try {
                $selected = $this->rates->resolve($tax, $jurisdiction, LocalDate::fromString($final['supply_date']), $knownAt);
            } catch (Throwable) {
                return $this->stop('FINAL_RATE_CATALOG_INVALID');
            }
            if ($selected['status'] !== 'RESOLVED' || ! isset($selected['row'])) {
                return $this->stop($selected['reason']);
            }
            $row = $selected['row'];
            $percent = $row['percent'] ?? null;
            if (! is_string($percent) || preg_match('/\A[0-9]+(?:\.[0-9]{1,6})?\z/', $percent) !== 1
                || DecimalValue::fromString($percent)->compareTo(DecimalValue::fromString('100')) > 0) {
                return $this->stop('FINAL_RATE_PRECISION_OR_RANGE_INVALID');
            }
            $definitive = DecimalValue::fromString($final['total_base'])->multipliedBy(DecimalValue::fromString($percent))
                ->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2);
            $advance = DecimalValue::fromString($prior['captured_advance_totals'][$tax]);
            $balance = $definitive->minus($advance);
            $taxes[$tax] = ['definitive_base' => $final['total_base'], 'definitive_percent' => $percent,
                'definitive_amount' => $definitive->value(), 'captured_advance_amount' => $advance->value(),
                'arithmetic_balance' => $balance->value(), 'final_source_id' => $row['source_id'], 'final_source_hash' => $row['source_hash'],
                'jurisdiction' => $jurisdiction, 'final_rate_date' => $final['supply_date'],
                'treatment' => $balance->isNegative() ? 'OVERPAYMENT_PROCEDURE_REQUIRED'
                    : ($balance->compareTo(DecimalValue::fromString('0')) === 0 ? 'NO_ADDITIONAL_DEBIT' : 'ADDITIONAL_DEBIT_CANDIDATE'),
                'cash_due' => null, 'credit_granted' => '0.00'];
        }
        $proposal = ['final_decision_id' => $final['final_decision_id'], 'final_document_id' => $final['final_document_id'],
            'book_id' => $final['book_id'], 'book_hash' => $final['book_hash'],
            'final_context_hash' => CanonicalJson::hash($final), 'reconciliation_hash' => CanonicalJson::hash($taxes),
            'registration_ids' => array_column($prior['records'], 'registration_id')];

        return ['status' => 'LOCAL_CAPTURED_ADVANCE_FINAL_RECONCILIATION_ONLY', 'taxes' => $taxes,
            'advanced_base' => $prior['advanced_base'], 'final_total_base' => $final['total_base'],
            'prior_records' => $prior['records'], 'consumption_proposal' => $proposal,
            'consumption_proposal_token' => CanonicalJson::hash($proposal), 'prior_registration_locally_bound' => true,
            'prior_advance_debits_proven' => false, 'posting_authorized' => false, 'actual_registration_consumed' => false,
            'extinction_proven' => false, 'cash_relief_qualified' => false, 'credit_appropriation_authorized' => false,
            'government_evidence_authenticated' => false, 'document_settlement_complete' => false, 'production_ready' => false];
    }

    private function id(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && ! str_contains($value, "\0");
    }

    private function date(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'taxes' => [], 'prior_advance_debits_proven' => false,
            'posting_authorized' => false, 'actual_registration_consumed' => false, 'extinction_proven' => false,
            'credit_appropriation_authorized' => false, 'government_evidence_authenticated' => false, 'production_ready' => false];
    }
}
