<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Exact local arithmetic for advance/final event plans; never proof of prior booked debits. */
final readonly class AdvanceTaxReconciler84
{
    public function __construct(private TemporalRateCatalog $rates) {}

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function reconcile(array $plan, string $state, string $municipality, DateTimeImmutable $knownAt): array
    {
        $events = $plan['events'] ?? null;
        if (($plan['status'] ?? null) !== 'LOCAL_EVENT_PLAN_ONLY' || ! is_array($events)
            || ! array_is_list($events) || $events === []) {
            return $this->stop('EVENT_PLAN_REQUIRED');
        }
        $ids = [];
        $advances = [];
        $final = null;
        $today = $knownAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        foreach ($events as $event) {
            if (! is_array($event) || ! is_string($event['id'] ?? null) || trim($event['id']) === ''
                || str_contains($event['id'], "\0") || isset($ids[$event['id']])
                || ! $this->date($event['event_date'] ?? null) || ! $this->date($event['rate_date'] ?? null)
                || $event['rate_date'] > $event['event_date'] || $event['event_date'] > $today
                || ! is_string($event['base_amount'] ?? null)
                || preg_match('/\A(?:0|[1-9][0-9]{0,15})\.[0-9]{2}\z/', $event['base_amount']) !== 1
                || $event['base_amount'] === '0.00') {
                return $this->stop('EVENT_ID_DATE_OR_BASE_INVALID');
            }
            $ids[$event['id']] = true;
            if (($event['kind'] ?? null) === 'ADVANCE') {
                $advances[] = $event;
            } elseif (($event['kind'] ?? null) === 'FINAL_SUPPLY' && $final === null
                && $event['rate_date'] === $event['event_date']) {
                $final = $event;
            } else {
                return $this->stop('ORDINARY_ADVANCE_FINAL_EVENT_SET_REQUIRED');
            }
        }
        if ($final === null) {
            return $this->stop('FINAL_SUPPLY_REQUIRED');
        }
        $expected = array_column($advances, 'id');
        $declared = $final['reconcile_advance_ids'] ?? null;
        if (! is_array($declared) || ! array_is_list($declared)
            || count(array_filter($declared, 'is_string')) !== count($declared)) {
            return $this->stop('FINAL_ADVANCE_BINDING_INVALID');
        }
        sort($expected);
        sort($declared);
        if ($declared !== $expected) {
            return $this->stop('FINAL_ADVANCE_BINDING_INVALID');
        }
        $advancedBase = DecimalValue::fromString('0.00');
        foreach ($advances as $advance) {
            if ($advance['event_date'] >= $final['event_date']) {
                return $this->stop('ADVANCE_NOT_BEFORE_FINAL_SUPPLY');
            }
            $advancedBase = $advancedBase->plus(DecimalValue::fromString($advance['base_amount']));
        }
        if ($advancedBase->compareTo(DecimalValue::fromString($final['base_amount'])) > 0) {
            return $this->stop('ADVANCE_BASE_EXCEEDS_FINAL_REQUIRES_ADJUSTMENT');
        }
        usort($events, static fn (array $a, array $b): int => [$a['event_date'], $a['id']] <=> [$b['event_date'], $b['id']]);
        $taxes = [];
        foreach (['IBS_STATE', 'IBS_MUNICIPAL', 'CBS'] as $tax) {
            $advanceTotal = DecimalValue::fromString('0.00');
            $definitive = DecimalValue::fromString('0.00');
            $trace = [];
            foreach ($events as $event) {
                $jurisdiction = $tax === 'CBS' || $event['rate_date'] < '2029-01-01' ? 'BR'
                    : ($tax === 'IBS_STATE' ? $state : $municipality);
                try {
                    $selected = $this->rates->resolve($tax, $jurisdiction, LocalDate::fromString($event['rate_date']), $knownAt);
                } catch (Throwable) {
                    return $this->stop('RATE_CATALOG_INVALID');
                }
                if ($selected['status'] !== 'RESOLVED' || ! isset($selected['row'])) {
                    return $this->stop($selected['reason']);
                }
                $row = $selected['row'];
                $percent = $row['percent'] ?? null;
                if (! is_string($percent) || preg_match('/\A[0-9]+(?:\.[0-9]{1,6})?\z/', $percent) !== 1
                    || DecimalValue::fromString($percent)->compareTo(DecimalValue::fromString('100')) > 0) {
                    return $this->stop('RATE_PRECISION_OR_RANGE_INVALID');
                }
                $amount = DecimalValue::fromString($event['base_amount'])
                    ->multipliedBy(DecimalValue::fromString($percent))
                    ->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2);
                if ($event['kind'] === 'ADVANCE') {
                    $advanceTotal = $advanceTotal->plus($amount);
                } else {
                    $definitive = $amount;
                }
                $trace[] = ['event_id' => $event['id'], 'kind' => $event['kind'],
                    'event_date' => $event['event_date'], 'rate_date' => $event['rate_date'],
                    'base_amount' => $event['base_amount'], 'percent' => $percent, 'amount' => $amount->value(),
                    'jurisdiction' => $jurisdiction, 'source_id' => $row['source_id'], 'source_hash' => $row['source_hash']];
            }
            $balance = $definitive->minus($advanceTotal);
            $taxes[$tax] = ['computed_advances' => $advanceTotal->value(), 'definitive_amount' => $definitive->value(),
                'arithmetic_balance' => $balance->value(),
                'treatment' => $balance->isNegative() ? 'OVERPAYMENT_PROCEDURE_REQUIRED'
                    : ($balance->compareTo(DecimalValue::fromString('0')) === 0 ? 'NO_ADDITIONAL_DEBIT' : 'ADDITIONAL_DEBIT_CANDIDATE'),
                'events' => $trace];
        }

        return ['status' => 'LOCAL_ADVANCE_RECONCILIATION_ONLY', 'taxes' => $taxes,
            'advanced_base' => $advancedBase->value(), 'final_total_base' => $final['base_amount'],
            'prior_advance_debits_proven' => false, 'source_policy_qualified' => false,
            'assessment_period_qualified' => false, 'credit_appropriation_authorized' => false,
            'posting_authorized' => false, 'production_ready' => false];
    }

    private function date(mixed $date): bool
    {
        if (! is_string($date) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $date) !== 1) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date && $date >= '2026-01-01';
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'taxes' => [],
            'posting_authorized' => false, 'credit_appropriation_authorized' => false, 'production_ready' => false];
    }
}
