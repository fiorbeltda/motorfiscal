<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;

/** Conserves an already replayed closing; supplied fiscal allocation is never inferred from rounding. */
final readonly class FinancialPublicCreatedRoundingClosingQualifier
{
    public const METHOD = 'REPLAYED_PUBLIC_CREATED_ROUNDING_CLOSING_WITH_PROVED_CURRENT_ALLOCATION';

    /** @param array<string,mixed> $scope
     * @param  array<string,mixed>  $source
     * @param  array<string,mixed>  $entry
     * @param  array<string,BigDecimal>  $current
     * @param  array<string,mixed>  $closing
     * @param  array<string,mixed>  $memory
     * @return array{available:array<string,BigDecimal>,carrying:array<string,string>}|null
     */
    public function resolve(array $scope, array $source, array $entry, array $current, array $closing, array $memory,
        string $sourcePeriod, string $priorPeriod, DateTimeImmutable $known): ?array
    {
        $binding = $entry['d2101_binding'];
        $analysis = $binding['sale_recognition_binding']['analysis'] ?? null;
        $history = $analysis['replayed_fiscal_history'] ?? null;
        if ($sourcePeriod !== $priorPeriod || ! is_array($analysis) || ($analysis['status'] ?? null) !== 'RECONCILED'
            || ($analysis['public_offer_art195'] ?? null) !== true || ($history['public_explicit_net_closing_replayed'] ?? null) !== true
            || ($scope['title']['holding_route'] ?? null) !== 'DIRECT' || ($binding['gross_nature'] ?? null) !== 'C'
            || ($entry['public_offer_debt']['position']['first_acquisition'] ?? null) !== false
            || array_column($entry['public_offer_debt']['position']['operations'] ?? [], 'kind') !== ['SELL']
            || count($analysis['realizations'] ?? []) !== 1
            || CanonicalJson::encode($entry['public_offer_debt']['title'] ?? null) !== CanonicalJson::encode($scope['title'])
            || CanonicalJson::encode($entry['public_offer_debt']['position']) !== CanonicalJson::encode($closing['position'])
            || CanonicalJson::encode($binding['calculation'] ?? null) !== CanonicalJson::encode($memory)) {
            return null;
        }
        $sale = $analysis['realizations'][0];
        if (($sale['book_release_rounding']['method'] ?? null) !== 'PROVED_HISTORICAL_AVERAGE_RELEASE_WITH_SEPARATE_CREATED_ROUNDING_8_2'
            || ($sale['book_release_rounding']['status'] ?? null) !== 'RESOLVED') {
            return null;
        }
        foreach (['accounting_gain', 'new_public_produced_return'] as $field) {
            if (! BigDecimal::of($sale[$field])->isZero()) {
                return null;
            }
        }
        $inventory = $analysis['recognition_inventory'];
        if (($inventory['other_releases'] ?? []) !== [] || ! BigDecimal::of($inventory['closing']['deferred_fair_value_realized'])->isZero()) {
            return null;
        }
        $split = $inventory['rounding_inventory'] ?? null;
        $allocation = $source['current_fiscal_allocation'] ?? null;
        if (! is_array($split) || ! is_array($allocation) || ! is_array($allocation['retained'] ?? null)
            || ! is_array($allocation['realized'] ?? null) || count($allocation['retained']) !== count($current)
            || count($allocation['realized']) !== count($current)) {
            return null;
        }
        $available = $retained = $realized = [];
        foreach ($current as $key => $amount) {
            foreach (['retained', 'realized'] as $edge) {
                if (! is_string($allocation[$edge][$key] ?? null) || preg_match('/^-?[0-9]+\.[0-9]{8}$/', $allocation[$edge][$key]) !== 1) {
                    return null;
                }
            }
            $retained[$key] = BigDecimal::of($allocation['retained'][$key]);
            $realized[$key] = BigDecimal::of($allocation['realized'][$key]);
            if ($retained[$key]->isNegative() || $realized[$key]->isNegative()
                || ! $retained[$key]->plus($realized[$key])->isEqualTo($amount)) {
                return null;
            }
            $prior = $key === 'deferred_fair_value' ? '0.00000000' : ($inventory['closing_fiscal_components'][$key] ?? null);
            if (! is_string($prior)) {
                return null;
            }
            $available[$key] = BigDecimal::of($prior)->plus($retained[$key]);
        }
        $sum = static fn (array $values): BigDecimal => array_reduce($values, static fn (BigDecimal $a, BigDecimal $b): BigDecimal => $a->plus($b), BigDecimal::of('0'));
        $oldRound = BigDecimal::of($split['closing_existing']);
        $newRound = BigDecimal::of($split['closing_created']);
        $priorCurve = BigDecimal::of($inventory['closing']['prior_produced_return']);
        $currentCurve = BigDecimal::of($inventory['closing']['current_produced_return_already_in_curve']);
        if (! $sum($retained)->isEqualTo($currentCurve)
            || ! $oldRound->plus($newRound)->isEqualTo($inventory['closing']['rounding_bridge'])
            || ! $sum($available)->minus($sum($retained))->isEqualTo($priorCurve->plus($oldRound))
            || ! $sum($available)->plus($newRound)->isEqualTo(BigDecimal::of($memory['monthly']['vSaldoContFin'])->minus($memory['closing_acquisition_cost']))) {
            return null;
        }
        $format = static fn (array $values): array => array_map(static fn (BigDecimal $v): string => (string) $v->toScale(8), $values);
        $carrying = $format(['prior_produced_return' => $priorCurve->plus($currentCurve), 'rounding_bridge' => $oldRound->plus($newRound),
            'fiscal_covered_rounding' => $oldRound, 'uncovered_created_rounding' => $newRound]);
        $value = ['supplier_id' => $scope['supplier_id'], 'source_period' => $sourcePeriod, 'current_period' => $scope['period'],
            'title' => $scope['title'], 'snapshot_hash' => CanonicalJson::hash($source['snapshot']), 'decision_hash' => $source['snapshot']['result']['decision_hash'],
            'financial_entry_id' => $source['financial_entry_id'], 'method' => self::METHOD, 'daily_memory_hash' => CanonicalJson::hash($memory),
            'analysis_hash' => CanonicalJson::hash($analysis), 'monthly_fiscal_components' => $format($current),
            'current_fiscal_allocation' => ['retained' => $format($retained), 'realized' => $format($realized)],
            'previous_fiscal_closing' => $inventory['closing_fiscal_components'], 'opening_fiscal_components' => $format($available),
            'opening_carrying_components' => $carrying, 'fiscal_origin' => 'D2101', 'normative_homologation_claimed' => false];
        if (! (new FinancialPublicOfferDebtQualifier)->proof($source['created_rounding_closing_evidence'] ?? null, $value,
            $scope['supplier_id'], $sourcePeriod.'-01', (new DateTimeImmutable($sourcePeriod.'-01'))->format('Y-m-t'), $known)) {
            return null;
        }

        return ['available' => $available, 'carrying' => $carrying];
    }
}
