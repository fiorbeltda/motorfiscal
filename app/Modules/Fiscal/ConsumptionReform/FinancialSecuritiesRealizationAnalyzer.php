<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use InvalidArgumentException;

/** Reconciles individual disposals and their recognition history before tax/document integration. */
final readonly class FinancialSecuritiesRealizationAnalyzer
{
    public const CONTRACT = 'TVM-REALIZATION-COMPONENT-RECONCILIATION-2026.10.06-1';

    /** @param array<string,mixed> $input
     * @param  array<string,mixed>  $rule
     * @return array<string,mixed>
     */
    public function analyze(array $input, array $rule, DateTimeImmutable $knownAt): array
    {
        try {
            if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
                || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
                || ! is_string($rule['source_hash'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $rule['source_hash']) !== 1
                || ! is_array($rule['daily_memory_calculation'] ?? null)) {
                return $this->stop('RULE_UNAVAILABLE');
            }
            $position = $input['position'] ?? null;
            $title = $input['title'] ?? null;
            $identity = new FinancialPublicOfferDebtQualifier;
            if (! is_array($position) || ! is_array($title) || ! $identity->validTitleIdentity($title)
                || ($title['idTitulo'] ?? null) !== ($position['idTitulo'] ?? null) || ($title['cCta'] ?? null) !== ($position['cCta'] ?? null)
                || ($title['currency'] ?? null) !== 'BRL' || ($title['creditor_in_financial_specific_regime'] ?? null) !== true) {
                return $this->stop('TITLE_SCOPE_CONFLICT');
            }
            $public = ($title['instrument_type'] ?? null) === 'CORPORATE_DEBT_PUBLIC_OFFER';
            if ($public) {
                if (($title['public_offer_regulator'] ?? null) !== 'CVM' || ($title['issuer_regime'] ?? null) !== 'REGULAR'
                    || ($title['issuer_in_financial_specific_regime'] ?? null) !== false
                    || $title['CNPJDevedor'] === ($position['supplier_id'] ?? null)) {
                    return $this->stop('PUBLIC_ART195_ELIGIBILITY_UNPROVEN');
                }
                if (($title['holding_route'] ?? null) === 'EXCLUSIVE_FUND_95') {
                    $percentage = $this->decimal($title['public_offer_portfolio_percent'] ?? null);
                    if (($title['exclusive_fund'] ?? null) !== true || $percentage->isLessThan('95') || $percentage->isGreaterThan('100')) {
                        return $this->stop('PUBLIC_FUND_ELIGIBILITY_UNPROVEN');
                    }
                } elseif (($title['holding_route'] ?? null) !== 'DIRECT') {
                    return $this->stop('HOLDING_ROUTE_UNPROVEN');
                }
            } elseif (! in_array($title['instrument_type'] ?? null, ['CORPORATE_DEBT_PRIVATE', 'EQUITY'], true)
                || ($title['holding_route'] ?? null) !== 'DIRECT') {
                return $this->stop('GENERAL_TVM_SCOPE_UNPROVEN');
            }
            $policy = $rule['daily_memory_calculation'];
            $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], $knownAt, $policy);
            if ($memory['status'] !== 'RESOLVED') {
                return $memory;
            }
            $start = $position['period'].'-01';
            $end = (new DateTimeImmutable($start))->format('Y-m-t');
            $supplier = $position['supplier_id'];
            $boundHistory = null;
            $firstWithoutHistory = ($rule['historical_snapshot_required'] ?? false) === true
                && ($rule['first_acquisition_without_history_allowed'] ?? false) === true && ($position['first_acquisition'] ?? null) === true;
            if ($firstWithoutHistory && ! empty($input['recognition_history'])) {
                return $this->stop('FIRST_ACQUISITION_UNEXPECTED_FISCAL_HISTORY');
            }
            if (($rule['historical_snapshot_required'] ?? false) === true && ! $firstWithoutHistory) {
                if (! is_array($rule['history_policy'] ?? null) || ! is_array($input['recognition_history'] ?? null)) {
                    return $this->stop('REPLAYED_FISCAL_HISTORY_MISSING');
                }
                $scope = ['supplier_id' => $supplier, 'period' => $position['period'], 'title' => $title, 'opening' => $position['opening']];
                $boundHistory = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $input['recognition_history'], $knownAt, $rule['history_policy']);
                if (($boundHistory['status'] ?? null) !== 'RESOLVED') {
                    return $boundHistory;
                }
            }
            if (! $identity->proof($input['classification_evidence'] ?? null,
                ['supplier_id' => $supplier, 'period' => $position['period'], 'title' => $title, 'position_input_hash' => CanonicalJson::hash($position)],
                $supplier, $start, $end, $knownAt)) {
                return $this->stop('INDEPENDENT_CLASSIFICATION_UNPROVEN');
            }
            $declarations = $input['realizations'] ?? null;
            if (! is_array($declarations) || ! array_is_list($declarations) || count($declarations) > 10000) {
                return $this->stop('REALIZATION_LIST_INVALID');
            }
            $sales = [];
            foreach ($memory['daily_memory'] as $day) {
                foreach ($day['operations'] as $operation) {
                    if ($operation['kind'] === 'SELL') {
                        $sales[$operation['id']] = ['date' => $day['date'], ...$operation];
                    }
                }
            }
            $seen = [];
            $rows = [];
            $zero = BigDecimal::of('0.00000000');
            $totals = array_fill_keys(['accounting_gain', 'price_minus_acquisition_cost', 'prior_produced_return',
                'current_produced_return_already_in_curve', 'new_produced_return_at_sale', 'deferred_fair_value_realized',
                'new_fair_value_change_at_sale', 'rounding_bridge'], $zero);
            $subaccounts = [];
            foreach ($declarations as $declaration) {
                if (! is_array($declaration) || ! is_string($declaration['operation_id'] ?? null)
                    || isset($seen[$declaration['operation_id']]) || ! isset($sales[$declaration['operation_id']])) {
                    return $this->stop('SALE_UNKNOWN_OR_DUPLICATE');
                }
                $id = $declaration['operation_id'];
                $seen[$id] = true;
                $sale = $sales[$id];
                $components = $declaration['components'] ?? null;
                if (! is_array($components) || count($components) !== 6) {
                    return $this->stop('COMPONENT_COVERAGE_INVALID');
                }
                $prior = $this->decimal($components['prior_produced_return'] ?? null, true);
                $curve = $this->decimal($components['current_produced_return_already_in_curve'] ?? null, true);
                $newProduced = $this->decimal($components['new_produced_return_at_sale'] ?? null, true);
                $deferred = $this->decimal($components['deferred_fair_value_realized'] ?? null, true);
                $newFair = $this->decimal($components['new_fair_value_change_at_sale'] ?? null, true);
                $rounding = $this->decimal($components['rounding_bridge'] ?? null, true);
                $thirdClosing = ($boundHistory['public_created_rounding_closing_replayed'] ?? false) === true;
                if ($thirdClosing && ($rule['third_sale_book_rounding_policy'] ?? null) !== FinancialThirdSaleRoundingQualifier::METHOD) {
                    return $this->stop('THIRD_BOOK_RELEASE_ROUNDING_POLICY_UNPROVEN');
                }
                if ($thirdClosing && ! isset($declaration['book_release_rounding_evidence'])) {
                    return $this->stop('THIRD_BOOK_RELEASE_ROUNDING_EVIDENCE_MISSING');
                }
                $historicalSplitRequested = ($boundHistory['public_explicit_net_closing_replayed'] ?? false) === true
                    && isset($declaration['book_release_rounding_evidence'])
                    && ($rule['historical_sale_book_rounding_policy'] ?? null) === 'PROVED_HISTORICAL_AVERAGE_RELEASE_WITH_SEPARATE_CREATED_ROUNDING_8_2';
                if (! $historicalSplitRequested && ! $thirdClosing && $rounding->abs()->isGreaterThan('0.00500000')) {
                    return $this->stop('ROUNDING_BRIDGE_OUTSIDE_CENTS');
                }
                $book = $this->decimal($sale['book_amount']);
                $cost = $this->decimal($sale['acquisition_cost_released']);
                $gain = $this->decimal($sale['realized_sale_gain'], true);
                $proceeds = $book->plus($gain);
                $bookReleaseRounding = null;
                $historicalCarryingRounding = ($boundHistory['public_explicit_net_closing_replayed'] ?? false) === true || $thirdClosing;
                if ($thirdClosing && $boundHistory !== null) {
                    if (count($declarations) !== 1 || array_column($position['operations'], 'kind') !== ['SELL']
                        || ! $deferred->isZero() || ! $newFair->isZero() || ! $newProduced->isZero() || ! $gain->isZero()) {
                        return $this->stop('THIRD_BOOK_RELEASE_ROUNDING_SCOPE_UNPROVEN');
                    }
                    $bookReleaseRounding = (new FinancialThirdSaleRoundingQualifier)->resolve($input, $declaration, $sale, $memory, $boundHistory, $knownAt, $rounding);
                    if ($bookReleaseRounding['status'] !== 'RESOLVED') {
                        return $bookReleaseRounding;
                    }
                }
                if ($historicalCarryingRounding && ! $thirdClosing && $boundHistory !== null && isset($declaration['book_release_rounding_evidence'])
                    && ($rule['historical_sale_book_rounding_policy'] ?? null) === 'PROVED_HISTORICAL_AVERAGE_RELEASE_WITH_SEPARATE_CREATED_ROUNDING_8_2') {
                    if (count($declarations) !== 1 || array_column($position['operations'], 'kind') !== ['SELL']
                        || ! $deferred->isZero() || ! $newFair->isZero() || ! $newProduced->isZero() || ! $gain->isZero()) {
                        return $this->stop('HISTORICAL_BOOK_RELEASE_ROUNDING_SCOPE_UNPROVEN');
                    }
                    $bookReleaseRounding = $this->historicalBookReleaseRounding($input, $declaration, $sale, $memory, $boundHistory, $knownAt, $rounding);
                    if ($bookReleaseRounding['status'] !== 'RESOLVED') {
                        return $bookReleaseRounding;
                    }
                }
                if ($public && (! $rounding->isZero() || isset($declaration['book_release_rounding_evidence']))
                    && $bookReleaseRounding === null
                    && ! ($historicalCarryingRounding && ! isset($declaration['book_release_rounding_evidence']))
                    && ($rule['sale_book_rounding_policy'] ?? null) === 'PROVED_AVERAGE_CARRYING_RELEASE_HALF_EVEN_8_2') {
                    if (! $firstWithoutHistory || $boundHistory !== null || count($declarations) !== 1
                        || ! $prior->isZero() || ! $deferred->isZero() || ! $newFair->isZero() || ! $newProduced->isZero() || ! $gain->isZero()) {
                        return $this->stop('BOOK_RELEASE_ROUNDING_SCOPE_UNPROVEN');
                    }
                    $bookReleaseRounding = $this->bookReleaseRounding($input, $declaration, $sale, $memory, $knownAt, $rounding);
                    if ($bookReleaseRounding['status'] !== 'RESOLVED') {
                        return $bookReleaseRounding;
                    }
                }
                if (! $book->minus($cost)->isEqualTo($prior->plus($curve)->plus($deferred)->plus($rounding))
                    || ! $gain->isEqualTo($newProduced->plus($newFair))) {
                    return $this->stop('BOOK_COST_OR_PRICE_COMPONENT_CONFLICT');
                }
                $historical = $declaration['history'] ?? null;
                if (! is_array($historical) || ! is_string($historical['snapshot_id'] ?? null) || trim($historical['snapshot_id']) === ''
                    || ! is_string($historical['period'] ?? null) || preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $historical['period']) !== 1
                    || $historical['period'] >= $position['period']
                    || $this->decimal($historical['previously_recognized_produced_return'] ?? null, true)->compareTo($prior) !== 0
                    || $this->decimal($historical['deferred_fair_value_released'] ?? null, true)->compareTo($deferred) !== 0) {
                    return $this->stop('RECOGNITION_HISTORY_CONFLICT');
                }
                $assessed = $this->decimal($historical['previously_assessed_produced_return'] ?? null, true);
                $previousExcluded = $this->decimal($historical['previously_excluded_art195_return'] ?? null, true);
                $previousPis = $this->decimal($historical['previously_removed_pis_cofins'] ?? '0', true);
                $lossTracked = $public && $boundHistory !== null && array_key_exists('unoffset_public_title_loss', $boundHistory['opening_fiscal_components']);
                if (array_key_exists('previously_unoffset_public_title_loss', $historical) && ! $lossTracked) {
                    return $this->stop('UNOFFSET_PUBLIC_LOSS_HISTORY_UNPROVEN');
                }
                $previousLoss = $this->decimal($historical['previously_unoffset_public_title_loss'] ?? '0', true);
                $coveredRounding = $historicalCarryingRounding ? $this->decimal($bookReleaseRounding['fiscal_covered_rounding_consumed']
                    ?? $bookReleaseRounding['existing_rounding_consumed'] ?? (string) $rounding, true) : BigDecimal::of('0');
                if ($historicalCarryingRounding
                    ? ! $this->decimal($historical['previously_recognized_carrying_rounding'] ?? null, true)->isEqualTo($coveredRounding)
                    : array_key_exists('previously_recognized_carrying_rounding', $historical)) {
                    return $this->stop('HISTORICAL_CARRYING_ROUNDING_UNPROVEN');
                }
                $publicChain = $lossTracked && (($boundHistory['public_position_chain_replayed'] ?? false) === true
                    || ($boundHistory['public_realized_closing_replayed'] ?? false) === true || $historicalCarryingRounding);
                if ($previousLoss->isPositive() || ($lossTracked && ! array_key_exists('previously_unoffset_public_title_loss', $historical))
                    || ! $assessed->plus($previousExcluded)->plus($previousPis)->plus($previousLoss)->isEqualTo($prior->plus($coveredRounding))
                    || ($publicChain && ($assessed->isNegative() || $previousExcluded->isNegative() || $previousPis->isNegative()))
                    || (! $public && (! $previousExcluded->isZero() || ! $previousPis->isZero()))
                    || (! $publicChain && (
                        (! $previousLoss->isZero() && (! $prior->isNegative() || $previousLoss->abs()->isGreaterThan($prior->abs())))
                    || (! $assessed->isZero() && ($assessed->isNegative() !== $prior->isNegative() || $assessed->abs()->isGreaterThan($prior->abs())))
                    || (! $previousExcluded->isZero() && ($previousExcluded->isNegative() !== $prior->isNegative() || $previousExcluded->abs()->isGreaterThan($prior->abs())))
                    || (! $previousPis->isZero() && ($previousPis->isNegative() !== $prior->isNegative() || $previousPis->abs()->isGreaterThan($prior->abs())))))) {
                    return $this->stop('PRIOR_ASSESSED_AND_EXCLUDED_RETURN_CONFLICT');
                }
                if ($boundHistory !== null && (($historical['snapshot_id'] ?? null) !== $boundHistory['source_set_hash']
                    || $historical['period'] !== $boundHistory['prior_period']
                    || ! array_key_exists('previously_removed_pis_cofins', $historical))) {
                    return $this->stop('SALE_FISCAL_HISTORY_REFERENCE_CONFLICT');
                }
                if ($firstWithoutHistory && (($historical['snapshot_id'] ?? null) !== 'FIRST_ACQUISITION_NO_PRIOR_SNAPSHOT'
                    || ! $prior->isZero() || ! $deferred->isZero() || ! $assessed->isZero() || ! $previousExcluded->isZero() || ! $previousPis->isZero())) {
                    return $this->stop('FIRST_ACQUISITION_PRIOR_RECOGNITION_CONFLICT');
                }
                $subaccount = $historical['fair_value_subaccount'] ?? null;
                if (! $deferred->isZero() || ! $newFair->isZero()) {
                    if (! is_string($subaccount) || preg_match('/^[A-Za-z0-9]{1,53}$/', $subaccount) !== 1 || $subaccount === $title['cCta']) {
                        return $this->stop('FAIR_VALUE_SUBACCOUNT_UNPROVEN');
                    }
                    $subaccounts[$subaccount] = true;
                }
                $historyStart = $historical['period'].'-01';
                $historyEnd = (new DateTimeImmutable($historyStart))->format('Y-m-t');
                $historyValue = ['supplier_id' => $supplier, 'idTitulo' => $title['idTitulo'], 'cCta' => $title['cCta'],
                    'operation_id' => $id, 'quantity' => $sale['quantity'], 'history' => $historical];
                if (! $identity->proof($declaration['history_evidence'] ?? null, $historyValue, $supplier, $historyStart, $historyEnd, $knownAt)) {
                    return $this->stop('INDEPENDENT_HISTORY_UNPROVEN');
                }
                $value = ['supplier_id' => $supplier, 'period' => $position['period'], 'idTitulo' => $title['idTitulo'], 'cCta' => $title['cCta'],
                    'sale' => $sale, 'components' => $components, 'history_evidence_hash' => $declaration['history_evidence']['source_hash']];
                if (! $identity->proof($declaration['component_evidence'] ?? null, $value, $supplier, $start, $end, $knownAt)) {
                    return $this->stop('INDEPENDENT_COMPONENTS_UNPROVEN');
                }
                $ordinary = $deferred->plus($newFair)->plus($public ? $zero : $newProduced);
                $rows[] = ['operation_id' => $id, 'date' => $sale['date'], 'quantity' => $sale['quantity'],
                    'sale_proceeds' => (string) $proceeds, 'book_value_released' => (string) $book,
                    'acquisition_cost_released' => (string) $cost, 'accounting_gain' => (string) $gain,
                    'price_minus_acquisition_cost' => (string) $proceeds->minus($cost), 'components' => $components,
                    'ordinary_current_component' => (string) $ordinary, 'new_public_produced_return' => (string) ($public ? $newProduced : $zero),
                    'current_curve_return_not_reincluded' => (string) $curve, 'prior_return_not_reincluded' => (string) $prior,
                    'previously_assessed_return' => (string) $assessed, 'previously_excluded_art195_return' => (string) $previousExcluded,
                    'previously_removed_pis_cofins' => (string) $previousPis,
                    ...($lossTracked ? ['previously_unoffset_public_title_loss' => (string) $previousLoss] : []),
                    'fair_value_subaccount' => $subaccount, 'history_evidence_hash' => $declaration['history_evidence']['source_hash'],
                    'component_evidence_hash' => $declaration['component_evidence']['source_hash']];
                if ($bookReleaseRounding !== null) {
                    $rows[array_key_last($rows)]['book_release_rounding'] = $bookReleaseRounding;
                }
                if ($historicalCarryingRounding) {
                    $rows[array_key_last($rows)]['historical_carrying_rounding'] = (string) $coveredRounding->toScale(8);
                }
                $totals['accounting_gain'] = $totals['accounting_gain']->plus($gain);
                $totals['price_minus_acquisition_cost'] = $totals['price_minus_acquisition_cost']->plus($proceeds->minus($cost));
                foreach ($components as $key => $amount) {
                    $totals[$key] = $totals[$key]->plus($this->decimal($amount, true));
                }
            }
            $publicReleaseWithoutSale = $sales === [] && $declarations === [] && $public && $boundHistory !== null
                && ($rule['public_other_release_without_sale_policy'] ?? null) === 'REPLAYED_PUBLIC_AMORTIZATION_OR_COUPON_WITHOUT_SALE';
            if (count($seen) !== count($sales) || ($seen === [] && ! $publicReleaseWithoutSale)) {
                return $this->stop('SALE_COVERAGE_INCOMPLETE');
            }
            if ($publicReleaseWithoutSale && ! $this->publicReleaseWithoutSaleScope($input, $memory)) {
                return $this->stop('PUBLIC_OTHER_RELEASE_WITHOUT_SALE_SCOPE_UNPROVEN');
            }
            $inventory = $this->reconcileInventory($input, $memory, $rows, $knownAt, $boundHistory);
            if ($inventory['status'] !== 'RECONCILED') {
                return $inventory;
            }
            $value = ['supplier_id' => $supplier, 'period' => $position['period'], 'position_input_hash' => CanonicalJson::hash($position),
                'title' => $title, 'realizations' => $declarations, 'recognition_inventory' => $input['recognition_inventory'],
                'inventory_evidence_hash' => $input['inventory_evidence']['source_hash']];
            if (! $identity->proof($input['complete_evidence'] ?? null, $value, $supplier, $start, $end, $knownAt)) {
                return $this->stop('COMPLETE_SCOPE_UNPROVEN');
            }
            usort($rows, static fn (array $a, array $b): int => [$a['date'], $a['operation_id']] <=> [$b['date'], $b['operation_id']]);
            $normalized = array_map(static fn (BigDecimal $v): string => (string) $v->toScale(8, RoundingMode::HalfEven), $totals);

            return ['status' => 'RECONCILED', 'reason' => 'RTC_TVM_REALIZATION_COMPONENTS_RECONCILED', 'contract' => self::CONTRACT,
                'supplier_id' => $supplier, 'period' => $position['period'], 'title' => $title, 'public_offer_art195' => $public,
                'realizations' => $rows, 'component_totals' => $normalized, 'subaccounts' => array_keys($subaccounts),
                'recognition_inventory' => $inventory,
                ...($publicReleaseWithoutSale ? ['public_other_release_without_sale_reconciled' => true] : []),
                ...($boundHistory === null ? [] : ['replayed_fiscal_history' => $boundHistory]),
                'daily_memory_hash' => CanonicalJson::hash($memory), 'input_hash' => CanonicalJson::hash($input),
                'classification_evidence_hash' => $input['classification_evidence']['source_hash'],
                'complete_evidence_hash' => $input['complete_evidence']['source_hash'], 'source_hash' => $rule['source_hash'],
                'scope' => 'ECONOMIC_ACCOUNTING_AND_RECOGNITION_COMPONENT_RECONCILIATION_ONLY',
                'financial_base_integration_performed' => false, 'xml_projection_performed' => false,
                'historical_fiscal_snapshot_replay_performed' => $boundHistory !== null,
                'tax_totalization_performed' => false, 'production_ready' => false, 'posting_performed' => false];
        } catch (InvalidArgumentException|\DateMalformedStringException) {
            return $this->stop('AMOUNT_OR_DATE_INVALID');
        }
    }

    /** @param array<string,mixed> $input
     * @param  array<string,mixed>  $declaration
     * @param  array<string,mixed>  $sale
     * @param  array<string,mixed>  $memory
     * @return array<string,mixed>
     */
    private function bookReleaseRounding(array $input, array $declaration, array $sale, array $memory, DateTimeImmutable $known, BigDecimal $rounding): array
    {
        $quantity = $this->decimal($sale['quantity'])->plus($this->decimal($sale['quantity_after']));
        $before = $this->decimal($sale['book_balance_after'])->plus($this->decimal($sale['book_amount']));
        $average = $before->multipliedBy($this->decimal($sale['quantity']))->dividedBy($quantity, 8, RoundingMode::HalfEven);
        $booked = $average->toScale(2, RoundingMode::HalfEven);
        if ($rounding->isZero() || ! $booked->isEqualTo($sale['book_amount']) || ! $booked->minus($average)->isEqualTo($rounding)) {
            return $this->stop('BOOK_RELEASE_ROUNDING_AMOUNT_CONFLICT');
        }
        $position = $input['position'];
        $method = 'PROVED_AVERAGE_CARRYING_RELEASE_HALF_EVEN_8_2';
        $value = ['supplier_id' => $position['supplier_id'], 'period' => $position['period'], 'title' => $input['title'], 'operation' => $sale,
            'daily_memory_hash' => CanonicalJson::hash($memory), 'method' => $method, 'unrounded_average_release' => (string) $average,
            'actual_book_release' => (string) $booked, 'signed_adjustment' => (string) $rounding->toScale(8), 'normative_homologation_claimed' => false];
        $start = $position['period'].'-01';
        if (! (new FinancialPublicOfferDebtQualifier)->proof($declaration['book_release_rounding_evidence'] ?? null, $value,
            $position['supplier_id'], $start, (new DateTimeImmutable($start))->format('Y-m-t'), $known)) {
            return $this->stop('INDEPENDENT_BOOK_RELEASE_ROUNDING_UNPROVEN');
        }

        return ['status' => 'RESOLVED', 'method' => $method, 'unrounded_average_release' => (string) $average,
            'actual_book_release' => (string) $booked, 'signed_adjustment' => (string) $rounding->toScale(8),
            'evidence_hash' => $declaration['book_release_rounding_evidence']['source_hash'], 'posting_performed' => false];
    }

    /** @param array<string,mixed> $input
     * @param  array<string,mixed>  $declaration
     * @param  array<string,mixed>  $sale
     * @param  array<string,mixed>  $memory
     * @param  array<string,mixed>  $history
     * @return array<string,mixed>
     */
    private function historicalBookReleaseRounding(array $input, array $declaration, array $sale, array $memory, array $history,
        DateTimeImmutable $known, BigDecimal $total): array
    {
        $quantity = $this->decimal($sale['quantity'])->plus($this->decimal($sale['quantity_after']));
        $before = $this->decimal($sale['book_balance_after'])->plus($this->decimal($sale['book_amount']));
        $average = $before->multipliedBy($this->decimal($sale['quantity']))->dividedBy($quantity, 8, RoundingMode::HalfEven);
        $booked = $average->toScale(2, RoundingMode::HalfEven);
        $created = $booked->minus($average)->toScale(8);
        $old = $this->decimal($history['opening_carrying_components']['rounding_bridge'], true)
            ->multipliedBy($this->decimal($sale['quantity']))->dividedBy($quantity, 8, RoundingMode::HalfEven);
        if (! $booked->isEqualTo($sale['book_amount']) || ! $old->plus($created)->isEqualTo($total)) {
            return $this->stop('HISTORICAL_BOOK_RELEASE_ROUNDING_AMOUNT_CONFLICT');
        }
        $period = $input['position']['period'];
        $method = 'PROVED_HISTORICAL_AVERAGE_RELEASE_WITH_SEPARATE_CREATED_ROUNDING_8_2';
        $amounts = ['unrounded_average_release' => (string) $average, 'actual_book_release' => (string) $booked,
            'existing_rounding_consumed' => (string) $old, 'created_rounding_adjustment' => (string) $created,
            'total_rounding_release' => (string) $total->toScale(8)];
        $value = ['supplier_id' => $input['position']['supplier_id'], 'period' => $period, 'title' => $input['title'], 'operation' => $sale,
            'daily_memory_hash' => CanonicalJson::hash($memory), 'history_source_set_hash' => $history['source_set_hash'], 'method' => $method,
            ...$amounts, 'normative_homologation_claimed' => false];
        if (! (new FinancialPublicOfferDebtQualifier)->proof($declaration['book_release_rounding_evidence'] ?? null, $value,
            $input['position']['supplier_id'], $period.'-01', (new DateTimeImmutable($period.'-01'))->format('Y-m-t'), $known)) {
            return $this->stop('INDEPENDENT_HISTORICAL_BOOK_RELEASE_ROUNDING_UNPROVEN');
        }

        return ['status' => 'RESOLVED', 'method' => $method, ...$amounts,
            'evidence_hash' => $declaration['book_release_rounding_evidence']['source_hash'], 'posting_performed' => false];
    }

    /** @param array<string,mixed> $input
     * @param  array<string,mixed>  $memory
     */
    private function publicReleaseWithoutSaleScope(array $input, array $memory): bool
    {
        $releases = $input['recognition_inventory']['other_releases'] ?? null;
        if (! is_array($releases) || ! array_is_list($releases) || $releases === []) {
            return false;
        }
        $operations = [];
        foreach ($memory['daily_memory'] as $day) {
            foreach ($day['operations'] as $operation) {
                if (in_array($operation['kind'], ['AMORTIZATION', 'COUPON'], true)) {
                    $operations[$operation['id']] = true;
                }
            }
        }
        if (count($operations) !== count($releases)) {
            return false;
        }
        foreach ($releases as $release) {
            if (! is_array($release) || ! is_string($release['operation_id'] ?? null) || ! isset($operations[$release['operation_id']])) {
                return false;
            }
            unset($operations[$release['operation_id']]);
        }
        foreach ([$input['recognition_inventory']['opening'] ?? [], $input['recognition_inventory']['closing'] ?? [],
            ...array_column($releases, 'components')] as $components) {
            if (! $this->decimal($components['deferred_fair_value_realized'] ?? null, true)->isZero()
                || ! $this->decimal($components['rounding_bridge'] ?? null, true)->isZero()) {
                return false;
            }
        }

        return $operations === [];
    }

    /** @param array<string,mixed> $input
     * @param  array<string,mixed>  $memory
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string,mixed>|null  $boundHistory
     * @return array<string,mixed>
     */
    private function reconcileInventory(array $input, array $memory, array $rows, DateTimeImmutable $knownAt, ?array $boundHistory = null): array
    {
        $inventory = $input['recognition_inventory'] ?? null;
        $keys = ['prior_produced_return', 'current_produced_return_already_in_curve', 'deferred_fair_value_realized', 'rounding_bridge'];
        if (! is_array($inventory) || ! is_array($inventory['opening'] ?? null) || ! is_array($inventory['closing'] ?? null)
            || ! is_array($inventory['other_releases'] ?? null) || ! array_is_list($inventory['other_releases'])) {
            return $this->stop('RECOGNITION_INVENTORY_MISSING');
        }
        $balances = [];
        $closing = [];
        foreach (['opening', 'closing'] as $edge) {
            if (count($inventory[$edge]) !== count($keys)) {
                return $this->stop('INVENTORY_COMPONENT_COVERAGE_INVALID');
            }
            foreach ($keys as $key) {
                $value = $this->decimal($inventory[$edge][$key] ?? null, true);
                if ($edge === 'opening') {
                    $balances[$key] = $value;
                } else {
                    $closing[$key] = $value;
                }
            }
        }
        $sum = static fn (array $values): BigDecimal => array_reduce($values, static fn (BigDecimal $a, BigDecimal $b): BigDecimal => $a->plus($b), BigDecimal::of('0'));
        $position = $input['position'];
        $fiscalKeys = ['assessed_produced_return', 'excluded_art195_return', 'removed_pis_cofins'];
        if ($boundHistory !== null && array_key_exists('unoffset_public_title_loss', $boundHistory['opening_fiscal_components'])) {
            $fiscalKeys[] = 'unoffset_public_title_loss';
        }
        /** @var array<string,BigDecimal>|null $fiscal */
        $fiscal = null;
        $thirdClosing = ($boundHistory['public_created_rounding_closing_replayed'] ?? false) === true;
        $explicitClosing = ($boundHistory['public_explicit_net_closing_replayed'] ?? false) === true || $thirdClosing;
        if ($boundHistory !== null) {
            $fiscal = [];
            foreach ($fiscalKeys as $key) {
                $fiscal[$key] = $this->decimal($boundHistory['opening_fiscal_components'][$key], true);
            }
            $coveredOpening = $thirdClosing ? $this->decimal($boundHistory['opening_carrying_components']['fiscal_covered_rounding'], true) : $balances['rounding_bridge'];
            if (! $sum($fiscal)->isEqualTo($explicitClosing ? $balances['prior_produced_return']->plus($coveredOpening) : $balances['prior_produced_return'])
                || ! $this->decimal($boundHistory['opening_fiscal_components']['deferred_fair_value'], true)->isEqualTo($balances['deferred_fair_value_realized'])) {
                return $this->stop('OPENING_FISCAL_RECOGNITION_CONFLICT');
            }
            if ($explicitClosing && (! $balances['prior_produced_return']->isEqualTo($boundHistory['opening_carrying_components']['prior_produced_return'])
                || ! $balances['rounding_bridge']->isEqualTo($boundHistory['opening_carrying_components']['rounding_bridge']))) {
                return $this->stop('OPENING_EXPLICIT_CARRYING_COMPONENT_CONFLICT');
            }
        }
        if (! $balances['current_produced_return_already_in_curve']->isZero()
            || ! $sum($balances)->isEqualTo($this->decimal($position['opening']['book_balance'])->minus($this->decimal($position['opening']['acquisition_cost'])))) {
            return $this->stop('OPENING_RECOGNITION_INVENTORY_CONFLICT');
        }
        $supplier = $position['supplier_id'];
        $start = $position['period'].'-01';
        $end = (new DateTimeImmutable($start))->format('Y-m-t');
        $value = ['supplier_id' => $supplier, 'period' => $position['period'], 'idTitulo' => $position['idTitulo'],
            'cCta' => $position['cCta'], 'position_input_hash' => CanonicalJson::hash($position), 'inventory' => $inventory];
        if (! (new FinancialPublicOfferDebtQualifier)->proof($input['inventory_evidence'] ?? null, $value, $supplier, $start, $end, $knownAt)) {
            return $this->stop('INDEPENDENT_RECOGNITION_INVENTORY_UNPROVEN');
        }
        $bySale = array_column($rows, null, 'operation_id');
        $splitRounding = null;
        $other = [];
        foreach ($inventory['other_releases'] as $release) {
            if (! is_array($release) || ! is_string($release['operation_id'] ?? null) || isset($other[$release['operation_id']])
                || isset($bySale[$release['operation_id']]) || ! is_array($release['components'] ?? null) || count($release['components']) !== count($keys)) {
                return $this->stop('OTHER_RECOGNITION_RELEASE_INVALID');
            }
            $other[$release['operation_id']] = $release;
        }
        $checkpoints = [];
        foreach ($memory['daily_memory'] as $day) {
            foreach ($day['operations'] as $operation) {
                if (($day['appropriation_before_operation_id'] ?? null) === $operation['id']) {
                    $balances['current_produced_return_already_in_curve'] = $balances['current_produced_return_already_in_curve']->plus($this->decimal($day['appropriation'], true));
                }
                if ($operation['kind'] === 'BUY') {
                    $cost = $this->decimal($operation['quantity'])->multipliedBy($this->decimal($operation['acquisition_unit_price']))->toScale(8, RoundingMode::HalfEven);
                    $balances['rounding_bridge'] = $balances['rounding_bridge']->plus($this->decimal($operation['book_amount'])->minus($cost));
                } else {
                    $components = $operation['kind'] === 'SELL' ? $bySale[$operation['id']]['components'] : ($other[$operation['id']]['components'] ?? null);
                    if (! is_array($components)) {
                        return $this->stop('OTHER_RECOGNITION_RELEASE_MISSING');
                    }
                    $released = [];
                    foreach ($keys as $key) {
                        $amount = $this->decimal($components[$key] ?? null, true);
                        $generatedRounding = $key === 'rounding_bridge' && isset($bySale[$operation['id']]['book_release_rounding']);
                        $historicalSplit = $generatedRounding && isset($bySale[$operation['id']]['book_release_rounding']['existing_rounding_consumed']);
                        $availableConsumed = $historicalSplit ? $this->decimal($bySale[$operation['id']]['book_release_rounding']['existing_rounding_consumed'], true) : $amount;
                        if ($generatedRounding && ! $historicalSplit && ! $balances[$key]->isZero()) {
                            return $this->stop('BOOK_RELEASE_ROUNDING_EXISTING_POOL_CONFLICT');
                        }
                        if ((! $generatedRounding || $historicalSplit) && ! $availableConsumed->isZero()
                            && ($availableConsumed->isNegative() !== $balances[$key]->isNegative() || $availableConsumed->abs()->isGreaterThan($balances[$key]->abs()))) {
                            return $this->stop('RECOGNITION_COMPONENT_REUSED_OR_UNAVAILABLE');
                        }
                        if ($historicalSplit) {
                            $created = $this->decimal($bySale[$operation['id']]['book_release_rounding']['created_rounding_adjustment'], true);
                            $splitRounding = ['opening_existing' => (string) $balances[$key]->toScale(8), 'consumed_existing' => (string) $availableConsumed->toScale(8),
                                'created_at_sale' => (string) $created->toScale(8), 'closing_existing' => (string) $balances[$key]->minus($availableConsumed)->toScale(8),
                                'closing_created' => (string) $created->negated()->toScale(8), 'closing_total' => (string) $balances[$key]->minus($amount)->toScale(8)];
                            if ($thirdClosing && $boundHistory !== null) {
                                $parts = $bySale[$operation['id']]['book_release_rounding'];
                                foreach (['fiscal_covered_rounding' => 'fiscal_covered_rounding_consumed', 'uncovered_created_rounding' => 'uncovered_created_rounding_consumed'] as $origin => $consumedField) {
                                    $openingPart = $this->decimal($boundHistory['opening_carrying_components'][$origin], true);
                                    $consumedPart = $this->decimal($parts[$consumedField], true);
                                    if (! $consumedPart->isZero() && ($consumedPart->isNegative() !== $openingPart->isNegative() || $consumedPart->abs()->isGreaterThan($openingPart->abs()))) {
                                        return $this->stop('THIRD_ROUNDING_ORIGIN_REUSED_OR_UNAVAILABLE');
                                    }
                                    $splitRounding[$origin] = ['opening' => (string) $openingPart->toScale(8), 'consumed' => (string) $consumedPart->toScale(8),
                                        'closing' => (string) $openingPart->minus($consumedPart)->toScale(8)];
                                }
                            }
                        }
                        $released[$key] = $amount;
                        $balances[$key] = $balances[$key]->minus($amount);
                    }
                    if (! $sum($released)->isEqualTo($this->decimal($operation['book_amount'])->minus($this->decimal($operation['acquisition_cost_released'])))) {
                        return $this->stop('OTHER_RECOGNITION_RELEASE_CONFLICT');
                    }
                    if ($fiscal !== null) {
                        $allocation = $operation['kind'] === 'SELL'
                            ? ['assessed_produced_return' => $bySale[$operation['id']]['previously_assessed_return'],
                                'excluded_art195_return' => $bySale[$operation['id']]['previously_excluded_art195_return'],
                                'removed_pis_cofins' => $bySale[$operation['id']]['previously_removed_pis_cofins']]
                            : ($other[$operation['id']]['fiscal_components'] ?? null);
                        if ($operation['kind'] === 'SELL' && in_array('unoffset_public_title_loss', $fiscalKeys, true)) {
                            $allocation['unoffset_public_title_loss'] = $bySale[$operation['id']]['previously_unoffset_public_title_loss'];
                        }
                        if ($allocation === null && $released['prior_produced_return']->isZero()) {
                            $allocation = array_fill_keys($fiscalKeys, '0');
                        }
                        if (! is_array($allocation) || count($allocation) !== count($fiscalKeys)) {
                            return $this->stop('HISTORICAL_FISCAL_RELEASE_MISSING');
                        }
                        $fiscalReleased = [];
                        foreach ($fiscalKeys as $key) {
                            $amount = $this->decimal($allocation[$key] ?? null, true);
                            if (! $amount->isZero() && ($amount->isNegative() !== $fiscal[$key]->isNegative() || $amount->abs()->isGreaterThan($fiscal[$key]->abs()))) {
                                return $this->stop('HISTORICAL_FISCAL_COMPONENT_REUSED_OR_UNAVAILABLE');
                            }
                            $fiscalReleased[$key] = $amount;
                            $fiscal[$key] = $fiscal[$key]->minus($amount);
                        }
                        $coveredRound = isset($bySale[$operation['id']]['historical_carrying_rounding'])
                            ? $this->decimal($bySale[$operation['id']]['historical_carrying_rounding'], true) : $released['rounding_bridge'];
                        if (! $sum($fiscalReleased)->isEqualTo($explicitClosing ? $released['prior_produced_return']->plus($coveredRound) : $released['prior_produced_return'])) {
                            return $this->stop('HISTORICAL_FISCAL_RELEASE_CONFLICT');
                        }
                    }
                    unset($other[$operation['id']]);
                }
                if (! $sum($balances)->isEqualTo($this->decimal($operation['book_balance_after'])->minus($this->decimal($operation['acquisition_cost_after'])))) {
                    return $this->stop('INTRAMONTH_RECOGNITION_INVENTORY_CONFLICT');
                }
                $checkpoints[] = ['date' => $day['date'], 'operation_id' => $operation['id'],
                    'remaining' => array_map(static fn (BigDecimal $v): string => (string) $v->toScale(8, RoundingMode::HalfEven), $balances)];
            }
            if (! isset($day['appropriation_before_operation_id'])) {
                $balances['current_produced_return_already_in_curve'] = $balances['current_produced_return_already_in_curve']->plus($this->decimal($day['appropriation'], true));
            }
            if (! $sum($balances)->isEqualTo($this->decimal($day['book_balance'])->minus($this->decimal($day['acquisition_cost'])))) {
                return $this->stop('DAILY_RECOGNITION_INVENTORY_CONFLICT');
            }
        }
        foreach ($keys as $key) {
            if (! $balances[$key]->isEqualTo($closing[$key])) {
                return $this->stop('CLOSING_RECOGNITION_INVENTORY_CONFLICT');
            }
        }
        if ($other !== []) {
            return $this->stop('OTHER_RECOGNITION_RELEASE_UNKNOWN');
        }
        if ($fiscal !== null) {
            if (! is_array($inventory['closing_fiscal_components'] ?? null) || count($inventory['closing_fiscal_components']) !== count($fiscalKeys)) {
                return $this->stop('CLOSING_FISCAL_COMPONENTS_MISSING');
            }
            foreach ($fiscalKeys as $key) {
                if (! $fiscal[$key]->isEqualTo($this->decimal($inventory['closing_fiscal_components'][$key] ?? null, true))) {
                    return $this->stop('CLOSING_FISCAL_COMPONENT_CONFLICT');
                }
            }
        }

        return ['status' => 'RECONCILED', 'opening' => $inventory['opening'], 'closing' => $inventory['closing'],
            ...($fiscal === null ? [] : ['closing_fiscal_components' => $inventory['closing_fiscal_components']]),
            ...($splitRounding === null ? [] : ['rounding_inventory' => $splitRounding]),
            'checkpoints' => $checkpoints, 'inventory_evidence_hash' => $input['inventory_evidence']['source_hash']];
    }

    private function decimal(mixed $value, bool $signed = false): BigDecimal
    {
        if (! is_string($value) || preg_match($signed ? '/^-?(0|[1-9][0-9]{0,14})(\.[0-9]{1,8})?$/' : '/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,8})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Canonical decimal amount with at most eight fraction digits required.');
        }

        return BigDecimal::of($value);
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_TVM_REALIZATION_'.$reason, 'production_ready' => false];
    }
}
