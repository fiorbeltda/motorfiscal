<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use InvalidArgumentException;

/** Binds qualified private realizations to PGCC codes and independently evidenced, balanced book journals. */
final readonly class DereSecuritiesRealizationBindingQualifier
{
    public const CONTRACT = 'DERE-TVM-REALIZATION-BOOK-BINDING-2026.10.06-1';

    /** @param array<string,mixed> $operation
     * @param  array<string,mixed>  $month
     * @param  array<string,mixed>  $pgcc
     * @param  array<string,mixed>  $rule
     * @return array<string,mixed>
     */
    public function resolve(array $operation, array $month, array $pgcc, DateTimeImmutable $knownAt, array $rule): array
    {
        try {
            if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
                || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
                || ! $this->hash($rule['source_hash'] ?? null) || ! $this->hash($rule['table_source_hash'] ?? null)) {
                return $this->stop('RULE_UNAVAILABLE');
            }
            $base = $operation['base'] ?? null;
            if (($operation['status'] ?? null) !== 'RESOLVED' || ! is_array($base) || ($base['status'] ?? null) !== 'RESOLVED'
                || ($pgcc['status'] ?? null) !== 'RESOLVED' || ($month['book']['closing_frequency'] ?? null) !== 'M'
                || ($base['supplier_id'] ?? null) !== ($month['book']['supplier_id'] ?? null)) {
                return $this->stop('QUALIFIED_CONTEXT_UNPROVEN');
            }
            $parents = $base['realization_bindings'] ?? null;
            $rows = $month['securities_realization_book'] ?? null;
            if (! is_array($parents) || $parents === [] || ! is_array($rows) || ! array_is_list($rows) || count($rows) !== count($parents)) {
                return $this->stop('PARENT_BOOK_COVERAGE_INCOMPLETE');
            }
            $plan = array_column($pgcc['accounts'], null, 'cCta');
            $book = array_column($month['book']['accounts'], null, 'cCta');
            $entries = array_column($base['entries'], null, 'id');
            $covered = $assets = $qualified = [];
            $mixedOrigins = ($rule['asset_position_mode'] ?? null) === 'PUBLIC_AGGREGATE_WITH_PRIVATE_DISTINCT_ACCOUNTS';
            $aggregateAssets = $mixedOrigins || ($rule['asset_position_mode'] ?? null) === 'PUBLIC_DISTINCT_ISINS_AGGREGATED_ACCOUNT';
            if (isset($rule['asset_position_mode']) && ! $aggregateAssets) {
                return $this->stop('ASSET_POSITION_POLICY_UNAVAILABLE');
            }
            /** @var array<string,array{opening:BigDecimal,closing:BigDecimal,titles:int}> $assetPositions */
            $assetPositions = [];
            $assetIdentities = $cashAccounts = [];
            $hasFairValue = false;
            $hasPublic = false;
            /** @var array<string,array{D:BigDecimal,C:BigDecimal,links:array<string,true>}> $accountTotals */
            $accountTotals = [];
            $identity = new FinancialPublicOfferDebtQualifier;
            foreach ($rows as $row) {
                $parentId = $row['parent_id'] ?? null;
                if (! is_string($parentId) || isset($covered[$parentId]) || ! is_array($parents[$parentId] ?? null)
                    || ($parents[$parentId]['status'] ?? null) !== 'RESOLVED'
                    || ($parents[$parentId]['contract'] ?? null) !== FinancialSecuritiesRealizationQualifier::CONTRACT) {
                    return $this->stop('PARENT_UNKNOWN_OR_DUPLICATE');
                }
                $covered[$parentId] = true;
                $parent = $parents[$parentId];
                $public = $parent['analysis']['public_offer_art195'];
                if ($public && ($rule['public_curve_origin_policy'] ?? null) !== 'PUBLIC_REALIZATION_WITH_SEPARATELY_QUALIFIED_D2101_CURVE') {
                    return $this->stop('PUBLIC_ACCOUNTING_BRIDGE_PENDING');
                }
                $hasPublic = $hasPublic || $public;
                $fairValueRequired = false;
                foreach ([...$parent['analysis']['realizations'], ...($parent['other_release_realizations'] ?? [])] as $sale) {
                    foreach (['deferred_fair_value_realized', 'new_fair_value_change_at_sale'] as $key) {
                        if (! BigDecimal::of($sale['components'][$key])->isZero()) {
                            $fairValueRequired = true;
                        }
                    }
                }
                if ($fairValueRequired && ($rule['fair_value_journal_mode'] ?? null) !== 'EXPLICIT_RECLASSIFICATION_AND_INDEPENDENT_SUBLEDGER') {
                    return $this->stop('FAIR_VALUE_SUBACCOUNT_REALIZATION_BRIDGE_PENDING');
                }
                $hasFairValue = $hasFairValue || $fairValueRequired;
                if ($aggregateAssets && ! $mixedOrigins && (! $public || $fairValueRequired)) {
                    return $this->stop('AGGREGATED_ASSET_ORIGIN_UNSUPPORTED');
                }
                $aggregateCurrentAsset = $aggregateAssets && $public && ! $fairValueRequired;
                $asset = $row['asset_account'] ?? null;
                $cash = $row['cash_account'] ?? null;
                $mapping = $row['component_accounts'] ?? null;
                if (! is_string($asset) || ! is_string($cash) || $asset !== $parent['title']['cCta'] || $asset === $cash || (isset($assets[$asset]) && (! $aggregateCurrentAsset || ! isset($assetPositions[$asset])))
                    || ! is_array($mapping) || count($mapping) !== count($parent['financial_entries'])
                    || ($plan[$asset]['codNat'] ?? null) !== '1' || ($plan[$cash]['codNat'] ?? null) !== '1'
                    || ($plan[$asset]['indCta'] ?? null) !== 'A' || ($plan[$cash]['indCta'] ?? null) !== 'A') {
                    return $this->stop('ASSET_CASH_OR_COMPONENT_MAPPING_INVALID');
                }
                if ($aggregateCurrentAsset) {
                    $titleKey = CanonicalJson::hash([$parent['title']['idTitulo'], $asset]);
                    if (isset($assetIdentities[$titleKey])) {
                        return $this->stop('AGGREGATED_ASSET_TITLE_DUPLICATE');
                    }
                    $assetIdentities[$titleKey] = true;
                    $cashAccounts[$cash] = true;
                }
                $assets[$asset] = true;
                $components = [];
                foreach ($parent['financial_entries'] as $entry) {
                    $id = $entry['id'];
                    $account = $mapping[$id] ?? null;
                    if (! is_array($entries[$id] ?? null) || CanonicalJson::encode($entries[$id]) !== CanonicalJson::encode($entry)
                        || ! is_string($account) || $account === $asset || $account === $cash
                        || ($plan[$account]['indCta'] ?? null) !== 'A'
                        || ($plan[$account]['codTrib'] ?? null) !== $entry['realization_binding']['expected_codTrib']
                        || ($plan[$account]['codNat'] ?? null) !== ($entry['bucket'] === 'DEDUCTION' ? '5' : '4')
                        || ! in_array($plan[$account]['natCta'] ?? null, $entry['realization_component']['component_kind'] === 'DEFERRED_FAIR_VALUE_REALIZED'
                            ? ['V', $entry['bucket'] === 'DEDUCTION' ? 'D' : 'C'] : [$entry['bucket'] === 'DEDUCTION' ? 'D' : 'C'], true)) {
                        return $this->stop('COMPONENT_PGCC_CODE_OR_NATURE_CONFLICT');
                    }
                    $components[$entry['realization_component']['component_id']] = ['entry' => $entry, 'account' => $account];
                }
                if ($public) {
                    $originId = $parent['public_yield_entry_id'] ?? null;
                    $origin = is_string($originId) ? ($entries[$originId] ?? null) : null;
                    $q = is_array($origin) ? ($origin['d2101_binding'] ?? null) : null;
                    if (! is_array($q) || ($origin['fiscal_origin'] ?? null) !== 'D2101' || ($origin['book_apur_amount'] ?? null) !== '0.00'
                        || ($q['sale_recognition_binding']['companion_parent_id'] ?? null) !== $parentId
                        || ! is_array($parent['public_yield_binding'] ?? null)
                        || CanonicalJson::encode($q) !== CanonicalJson::encode($parent['public_yield_binding'])
                        || ($q['calculation_hash'] ?? null) !== $parent['analysis']['daily_memory_hash']
                        || ($plan[$asset]['codTrib'] ?? null) !== $q['expected_codTrib']) {
                        return $this->stop('PUBLIC_CURVE_ORIGIN_OR_ASSET_CONFLICT');
                    }
                    $result = $q['result_account_code'];
                    if (isset($q['gross_rounding_reconciliation'])) {
                        if (($rule['gross_rounding_journal_mode'] ?? null) !== FinancialPublicGrossRoundingQualifier::METHOD
                            || CanonicalJson::encode($row['gross_rounding_reconciliation'] ?? null) !== CanonicalJson::encode($q['gross_rounding_reconciliation'])) {
                            return $this->stop('PUBLIC_GROSS_ROUNDING_JOURNAL_UNPROVEN');
                        }
                    } elseif (isset($row['gross_rounding_reconciliation'])) {
                        return $this->stop('UNEXPECTED_PUBLIC_GROSS_ROUNDING_JOURNAL');
                    }
                    if ($result === $asset || $result === $cash || in_array($result, $mapping, true)
                        || ($plan[$result]['indCta'] ?? null) !== 'A' || ($plan[$result]['codNat'] ?? null) !== '4'
                        || ($plan[$result]['natCta'] ?? null) !== 'C'
                        || ($plan[$result]['codTrib'] ?? null) !== ($q['expected_codTrib'] === '110113001' ? '311011201' : '311011202')) {
                        return $this->stop('PUBLIC_CURVE_RESULT_ACCOUNT_SEGREGATION_CONFLICT');
                    }
                    foreach (['CURVE_CREDIT' => ['gross_result_credit', 'REVENUE'], 'CURVE_DEBIT' => ['gross_result_debit', 'DEDUCTION']] as $id => [$field, $bucket]) {
                        if (! BigDecimal::of($q[$field])->isZero()) {
                            $components[$id] = ['account' => $result, 'entry' => ['id' => $originId, 'bucket' => $bucket, 'computed_amount' => $q[$field]]];
                        }
                    }
                }
                $journals = [];
                // The daily memory was reproduced by the parent qualification. Its input is retained in the analysis-independent source.
                $memory = $row['daily_memory'] ?? null;
                if (! is_array($memory) || CanonicalJson::hash($memory) !== $parent['analysis']['daily_memory_hash']) {
                    return $this->stop('REPLAYED_POSITION_MEMORY_CONFLICT');
                }
                $rounding = isset($row['daily_journal_rounding']) ? $this->dailyRoundingBridge($parent, $row, $memory, $components, $knownAt, $rule) : null;
                if ($rounding !== null && $rounding['status'] !== 'RESOLVED') {
                    return $rounding;
                }
                $fair = $fairValueRequired ? $this->fairValueBridge($parent, $row, $plan, $book, $components) : null;
                if ($fair !== null && $fair['status'] !== 'RESOLVED') {
                    return $fair;
                }
                if (! $fairValueRequired && isset($row['fair_value_bridge'])) {
                    return $this->stop('UNEXPECTED_FAIR_VALUE_BRIDGE');
                }
                foreach ($memory['daily_memory'] as $day) {
                    foreach ($day['operations'] as $event) {
                        $amount = $this->money($event['book_amount']);
                        $lines = [];
                        if ($event['kind'] === 'BUY') {
                            $lines = [$this->line($asset, 'D', $amount), $this->line($cash, 'C', $amount)];
                        } else {
                            $cashAmount = $amount;
                            if ($event['kind'] === 'SELL') {
                                $cashAmount = $amount->plus($event['realized_sale_gain']);
                                foreach (['new_produced_return_at_sale', 'new_fair_value_change_at_sale'] as $part) {
                                    $key = 'SALE:'.$event['id'].':'.$part;
                                    if (isset($components[$key])) {
                                        $component = $components[$key];
                                        $lines[] = $this->line($component['account'], $component['entry']['bucket'] === 'DEDUCTION' ? 'D' : 'C',
                                            $this->money($component['entry']['computed_amount']), $component['entry']['id']);
                                    }
                                }
                            }
                            $lines[] = $this->line($asset, 'C', $amount);
                            $lines[] = $this->line($cash, 'D', $cashAmount);
                        }
                        $journals[] = $this->journal($event['kind'].':'.$event['id'], $parentId, $day['date'], $lines);
                        if (isset($fair['reclassifications'][$event['id']])) {
                            $journals[] = $this->journal('FAIR_VALUE_RECLASS:'.$event['id'], $parentId, $day['date'], $fair['reclassifications'][$event['id']]);
                        }
                    }
                    $appropriation = BigDecimal::of($day['appropriation']);
                    if (! $appropriation->isZero()) {
                        // No silent allocation of sub-cent daily amounts to gross movements.
                        $rounded = $rounding === null ? $appropriation->toScale(2, RoundingMode::HalfEven) : BigDecimal::of($rounding['booked_by_date'][$day['date']]);
                        if ($rounding === null && ! $rounded->isEqualTo($appropriation)) {
                            return $this->stop('DAILY_JOURNAL_ROUNDING_BRIDGE_PENDING');
                        }
                        if ($rounded->isZero()) {
                            continue;
                        }
                        $component = $components[$appropriation->isNegative() ? 'CURVE_DEBIT' : 'CURVE_CREDIT'] ?? null;
                        if (! is_array($component)) {
                            return $this->stop('CURVE_RESULT_COMPONENT_UNPROVEN');
                        }
                        $curveJournal = $this->journal('CURVE:'.$day['date'], $parentId, $day['date'], [
                            $this->line($asset, $appropriation->isNegative() ? 'C' : 'D', $rounded->abs()),
                            $this->line($component['account'], $appropriation->isNegative() ? 'D' : 'C', $rounded->abs(), $component['entry']['id'])]);
                        if (isset($day['appropriation_before_operation_id'])) {
                            $index = array_search('SELL:'.$day['appropriation_before_operation_id'], array_column($journals, 'event_id'), true);
                            if ($index === false) {
                                return $this->stop('INTRADAY_JOURNAL_TARGET_UNPROVEN');
                            }
                            array_splice($journals, $index, 0, [$curveJournal]);
                        } else {
                            $journals[] = $curveJournal;
                        }
                    }
                }
                if ($public && isset($q['gross_rounding_reconciliation'])) {
                    $journals[] = $q['gross_rounding_reconciliation']['journal'];
                }
                if (CanonicalJson::encode($row['journals'] ?? null) !== CanonicalJson::encode($journals)) {
                    return $this->stop('BOOK_JOURNAL_COMPONENT_OR_BALANCE_CONFLICT');
                }
                foreach ($journals as $journal) {
                    $debit = $credit = BigDecimal::of('0.00');
                    foreach ($journal['lines'] as $line) {
                        $amount = $this->money($line['amount']);
                        if ($line['nature'] === 'D') {
                            $debit = $debit->plus($amount);
                        } else {
                            $credit = $credit->plus($amount);
                        }
                        $accountTotals[$line['cCta']] ??= ['D' => BigDecimal::of('0.00'), 'C' => BigDecimal::of('0.00'), 'links' => []];
                        if ($line['nature'] === 'D') {
                            $accountTotals[$line['cCta']]['D'] = $accountTotals[$line['cCta']]['D']->plus($amount);
                        } else {
                            $accountTotals[$line['cCta']]['C'] = $accountTotals[$line['cCta']]['C']->plus($amount);
                        }
                        if ($line['financial_entry_id'] !== null) {
                            $accountTotals[$line['cCta']]['links'][$line['financial_entry_id']] = true;
                        }
                    }
                    if (! $debit->isEqualTo($credit)) {
                        return $this->stop('UNBALANCED_BOOK_JOURNAL');
                    }
                }
                if (($book[$asset]['natSaldoInic'] ?? null) !== 'D' || ($book[$asset]['natSaldoFinal'] ?? null) !== 'D'
                    || (! $aggregateCurrentAsset && ! $this->money($book[$asset]['vSaldoInic'] ?? null)->isEqualTo($memory['monthly']['vSaldoContIni']))
                    || (! $aggregateCurrentAsset && ! $this->money($book[$asset]['vSaldoFinal'] ?? null)->isEqualTo($memory['monthly']['vSaldoContFin']))
                    || ($book[$asset]['vApur'] ?? null) !== '0.00' || ($book[$cash]['vApur'] ?? null) !== '0.00') {
                    return $this->stop('ASSET_POSITION_OR_PRINCIPAL_EXCLUSION_CONFLICT');
                }
                if ($aggregateCurrentAsset) {
                    $assetPositions[$asset] ??= ['opening' => BigDecimal::of('0.00'), 'closing' => BigDecimal::of('0.00'), 'titles' => 0];
                    $assetPositions[$asset]['opening'] = $assetPositions[$asset]['opening']->plus($memory['monthly']['vSaldoContIni']);
                    $assetPositions[$asset]['closing'] = $assetPositions[$asset]['closing']->plus($memory['monthly']['vSaldoContFin']);
                    $assetPositions[$asset]['titles']++;
                }
                $proofValue = ['supplier_id' => $base['supplier_id'], 'period' => substr($base['period_end'], 0, 7), 'parent_id' => $parentId,
                    'qualification_hash' => CanonicalJson::hash($parent), 'asset_account' => $asset, 'cash_account' => $cash,
                    'component_accounts' => $mapping, 'journals' => $journals, 'daily_memory_hash' => CanonicalJson::hash($memory),
                    'book_source_hash' => $month['book']['book_source_hash'], 'pgcc_source_hash' => $pgcc['source_hash'],
                    'book_content_hash' => CanonicalJson::hash($month['book']), 'pgcc_content_hash' => CanonicalJson::hash($pgcc)];
                if ($fair !== null) {
                    $proofValue['fair_value_bridge'] = $row['fair_value_bridge'];
                }
                if ($rounding !== null) {
                    $proofValue['daily_journal_rounding'] = $row['daily_journal_rounding'];
                }
                if ($public && isset($q['gross_rounding_reconciliation'])) {
                    $proofValue['gross_rounding_reconciliation'] = $q['gross_rounding_reconciliation'];
                }
                if (! $identity->proof($row['independent_evidence'] ?? null, $proofValue, $base['supplier_id'], $base['period_start'], $base['period_end'], $knownAt)) {
                    return $this->stop('INDEPENDENT_BOOK_BRIDGE_UNPROVEN');
                }
                $qualified[$parentId] = ['journal_count' => count($journals), 'evidence_hash' => $row['independent_evidence']['source_hash'],
                    'component_accounts' => $mapping, 'asset_account' => $asset, 'cash_account' => $cash,
                    ...($fair === null ? [] : ['fair_value_subaccounts' => $fair['subaccounts']])];
                if ($rounding !== null) {
                    $qualified[$parentId]['daily_journal_rounding'] = $rounding;
                }
            }
            if ($aggregateAssets) {
                if (array_intersect_key($assets, $cashAccounts) !== []) {
                    return $this->stop('AGGREGATED_ASSET_CASH_ACCOUNT_CONFLICT');
                }
                foreach ($assetPositions as $code => $position) {
                    if (! $this->money($book[$code]['vSaldoInic'] ?? null)->isEqualTo($position['opening'])
                        || ! $this->money($book[$code]['vSaldoFinal'] ?? null)->isEqualTo($position['closing'])) {
                        return $this->stop('AGGREGATED_ASSET_POSITION_CONFLICT');
                    }
                }
            }
            foreach ($accountTotals as $code => $totals) {
                $account = $book[$code] ?? null;
                $links = array_keys($totals['links']);
                $actual = $account['financial_entry_ids'] ?? null;
                if (! is_array($account) || ! is_array($actual) || ! array_is_list($actual)) {
                    return $this->stop('JOURNAL_ACCOUNT_ABSENT_OR_LINKS_INVALID');
                }
                sort($links);
                sort($actual);
                if ($links !== $actual || ! $this->money($account['vMovDebt'] ?? null)->isEqualTo($totals['D'])
                    || ! $this->money($account['vMovCred'] ?? null)->isEqualTo($totals['C'])) {
                    return $this->stop('JOURNAL_GROSS_MOVEMENTS_OR_COMPONENT_LINKS_CONFLICT');
                }
            }

            return ['status' => 'RESOLVED', 'reason' => 'RTC_DERE_TVM_BOOK_BINDING_QUALIFIED', 'contract' => self::CONTRACT,
                ...($aggregateAssets ? ['asset_position_mode' => $rule['asset_position_mode'],
                    'aggregate_asset_positions' => array_map(static fn (array $position): array => ['opening' => (string) $position['opening']->toScale(2), 'closing' => (string) $position['closing']->toScale(2), 'titles' => $position['titles']], $assetPositions)] : []),
                'parents' => $qualified, 'accounts_checked' => count($accountTotals), 'production_ready' => false,
                'operation_hash' => CanonicalJson::hash($operation), 'book_content_hash' => CanonicalJson::hash($month['book']),
                'pgcc_content_hash' => CanonicalJson::hash($pgcc),
                'posting_performed' => false, 'scope' => $hasPublic ? 'PUBLIC_D2101_CURVE_AND_D1101_REALIZATION_BOOK_JOURNALS_SEGREGATED'
                    : ($hasFairValue ? 'PRIVATE_REALIZATION_BOOK_JOURNALS_WITH_EXPLICIT_FAIR_VALUE_SUBLEDGER'
                        : 'PRIVATE_REALIZATION_BOOK_JOURNALS_WITHOUT_FAIR_VALUE_BRIDGE')];
        } catch (InvalidArgumentException|\DateMalformedStringException) {
            return $this->stop('AMOUNT_OR_DATE_INVALID');
        }
    }

    /** @param array<string,mixed> $parent
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $plan
     * @param  array<string,mixed>  $book
     * @param  array<string,mixed>  $components
     * @return array<string,mixed>
     */
    private function fairValueBridge(array $parent, array $row, array $plan, array $book, array $components): array
    {
        $bridge = $row['fair_value_bridge'] ?? null;
        if (! is_array($bridge) || ($bridge['accounting_method'] ?? null) !== 'PROVIDED_REALIZATION_RECLASSIFICATION_NO_AUTOMATIC_POSTING'
            || ($bridge['normative_homologation_claimed'] ?? null) !== false || ! is_array($bridge['subaccounts'] ?? null)
            || ! array_is_list($bridge['subaccounts']) || $bridge['subaccounts'] === []) {
            return $this->stop('FAIR_VALUE_SUBLEDGER_AND_METHOD_UNPROVEN');
        }
        $sales = array_column([...$parent['analysis']['realizations'], ...($parent['other_release_realizations'] ?? [])], null, 'operation_id');
        $seen = $labels = $reclassifications = [];
        $opening = $closing = BigDecimal::of('0.00000000');
        /** @var array<string,array{D:BigDecimal,C:BigDecimal,role:string}> $expectedAdjustments */
        $expectedAdjustments = [];
        foreach ($bridge['subaccounts'] as $subaccount) {
            $label = $subaccount['cCta'] ?? null;
            $counter = $subaccount['reclassification_counter_account'] ?? null;
            $allowedLabels = [...$parent['analysis']['subaccounts'], ...array_column($parent['other_release_realizations'] ?? [], 'fair_value_subaccount')];
            if (! is_string($label) || isset($labels[$label]) || ! in_array($label, $allowedLabels, true)
                || ($plan[$label]['indCta'] ?? null) !== 'A' || ! is_array($subaccount['releases'] ?? null) || ! array_is_list($subaccount['releases'])) {
                return $this->stop('FAIR_VALUE_SUBACCOUNT_IDENTITY_CONFLICT');
            }
            $labels[$label] = true;
            $initial = $this->signed($subaccount['opening_deferred_value'] ?? null);
            $final = $this->signed($subaccount['closing_deferred_value'] ?? null);
            $opening = $opening->plus($initial);
            $closing = $closing->plus($final);
            $balance = $initial;
            foreach ($subaccount['releases'] as $release) {
                $id = $release['operation_id'] ?? null;
                $sale = is_string($id) ? ($sales[$id] ?? null) : null;
                if (! is_array($sale) || isset($seen[$id]) || $sale['fair_value_subaccount'] !== $label
                    || ! $this->signed($release['prior_deferred_value'] ?? null)->isEqualTo($sale['components']['deferred_fair_value_realized'])
                    || ! $this->signed($release['new_at_sale_value'] ?? null)->isEqualTo($sale['components']['new_fair_value_change_at_sale'])) {
                    return $this->stop('FAIR_VALUE_RELEASE_COMPONENT_CONFLICT');
                }
                $seen[$id] = true;
                $deferred = $this->signed($release['prior_deferred_value']);
                $new = $this->signed($release['new_at_sale_value']);
                $sign = ! $initial->isZero() ? $initial->isNegative() : (! $deferred->isZero() ? $deferred->isNegative() : $new->isNegative());
                if (($plan[$label]['codTrib'] ?? null) !== ($sign ? '411011701' : '311011702')
                    || ($plan[$label]['codNat'] ?? null) !== ($sign ? '5' : '4') || ! in_array($plan[$label]['natCta'] ?? null, ['V', $sign ? 'D' : 'C'], true)) {
                    return $this->stop('FAIR_VALUE_SUBACCOUNT_CODE_CONFLICT');
                }
                if (! $deferred->isZero() && ($balance->isNegative() !== $deferred->isNegative() || $deferred->abs()->isGreaterThan($balance->abs()))) {
                    return $this->stop('FAIR_VALUE_RELEASE_EXCEEDS_AVAILABLE_SUBACCOUNT');
                }
                $balance = $balance->minus($deferred);
                if (! $deferred->isZero()) {
                    $component = $components[(isset($sale['kind']) ? 'RELEASE:' : 'SALE:').$id.':deferred_fair_value_realized'] ?? null;
                    if (! is_array($component) || $component['account'] !== $label || ! is_string($counter) || $counter === $label
                        || ($plan[$counter]['indCta'] ?? null) !== 'A' || ($plan[$counter]['codTrib'] ?? null) !== ($sign ? '411011102' : '311011107')
                        || ($plan[$counter]['codNat'] ?? null) !== ($sign ? '5' : '4') || ($plan[$counter]['natCta'] ?? null) !== ($sign ? 'D' : 'C')) {
                        return $this->stop('FAIR_VALUE_RECLASSIFICATION_ACCOUNT_CONFLICT');
                    }
                    $amount = $this->money($component['entry']['computed_amount']);
                    if (! $amount->isEqualTo($deferred->abs())) {
                        return $this->stop('FAIR_VALUE_RECLASSIFICATION_ROUNDING_BRIDGE_PENDING');
                    }
                    $fromNature = $sign ? 'C' : 'D';
                    $toNature = $sign ? 'D' : 'C';
                    $reclassifications[$id] = [$this->line($label, $fromNature, $amount, $component['entry']['id']), $this->line($counter, $toNature, $amount)];
                    foreach ([[$label, $fromNature, 'ORIGIN'], [$counter, $toNature, 'COUNTER']] as [$account, $nature, $role]) {
                        $expectedAdjustments[$account] ??= ['D' => BigDecimal::of('0.00'), 'C' => BigDecimal::of('0.00'), 'role' => $role];
                        if ($expectedAdjustments[$account]['role'] !== $role) {
                            return $this->stop('FAIR_VALUE_RECLASSIFICATION_ROLE_REUSED');
                        }
                        if ($nature === 'D') {
                            $expectedAdjustments[$account]['D'] = $expectedAdjustments[$account]['D']->plus($amount);
                        } else {
                            $expectedAdjustments[$account]['C'] = $expectedAdjustments[$account]['C']->plus($amount);
                        }
                    }
                }
            }
            if (! isset($expectedAdjustments[$label]) && (($book[$label]['vMovDebt'] ?? null) !== '0.00' || ($book[$label]['vMovCred'] ?? null) !== '0.00'
                || ($book[$label]['vApur'] ?? null) !== '0.00' || ($book[$label]['financial_entry_ids'] ?? null) !== [])) {
                return $this->stop('FAIR_VALUE_ZERO_ORIGIN_ACCOUNT_CONFLICT');
            }
            if (! $balance->isEqualTo($final)) {
                return $this->stop('FAIR_VALUE_SUBACCOUNT_CLOSING_CONFLICT');
            }
        }
        foreach ($sales as $id => $sale) {
            if ((! BigDecimal::of($sale['components']['deferred_fair_value_realized'])->isZero()
                || ! BigDecimal::of($sale['components']['new_fair_value_change_at_sale'])->isZero()) && ! isset($seen[$id])) {
                return $this->stop('FAIR_VALUE_RELEASE_COVERAGE_INCOMPLETE');
            }
        }
        $inventory = $parent['analysis']['recognition_inventory'];
        if (! $opening->isEqualTo($inventory['opening']['deferred_fair_value_realized'])
            || ! $closing->isEqualTo($inventory['closing']['deferred_fair_value_realized'])) {
            return $this->stop('FAIR_VALUE_SUBLEDGER_INVENTORY_CONFLICT');
        }
        foreach ($expectedAdjustments as $account => $totals) {
            if (! $this->money($book[$account]['vAjusteDebt'] ?? '0.00')->isEqualTo($totals['D'])
                || ! $this->money($book[$account]['vAjusteCred'] ?? '0.00')->isEqualTo($totals['C'])) {
                return $this->stop('FAIR_VALUE_RECLASSIFICATION_TAX_ADJUSTMENT_CONFLICT');
            }
        }

        return ['status' => 'RESOLVED', 'reclassifications' => $reclassifications, 'subaccounts' => array_keys($labels)];
    }

    private function signed(mixed $value): BigDecimal
    {
        if (! is_string($value) || preg_match('/^-?(0|[1-9][0-9]{0,14})(\.[0-9]{1,8})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Canonical signed amount required.');
        }

        return BigDecimal::of($value);
    }

    /** @param array<string,mixed> $parent
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $memory
     * @param  array<string,mixed>  $components
     * @param  array<string,mixed>  $rule
     * @return array<string,mixed>
     */
    private function dailyRoundingBridge(array $parent, array $row, array $memory, array $components, DateTimeImmutable $knownAt, array $rule): array
    {
        $bridge = $row['daily_journal_rounding'];
        if (($rule['daily_journal_rounding_mode'] ?? null) !== 'PROVIDED_CUMULATIVE_GROSS_HALF_EVEN_8_2'
            || ! is_array($bridge) || ($bridge['method'] ?? null) !== 'CUMULATIVE_GROSS_HALF_EVEN_8_2'
            || ($bridge['normative_homologation_claimed'] ?? null) !== false) {
            return $this->stop('DAILY_JOURNAL_ROUNDING_POLICY_UNPROVEN');
        }
        $raw = ['C' => BigDecimal::of('0.00000000'), 'D' => BigDecimal::of('0.00000000')];
        $booked = ['C' => BigDecimal::of('0.00'), 'D' => BigDecimal::of('0.00')];
        $allocations = $dates = [];
        foreach ($memory['daily_memory'] as $day) {
            $amount = BigDecimal::of($day['appropriation']);
            if ($amount->isZero()) {
                continue;
            }
            $nature = $amount->isNegative() ? 'D' : 'C';
            $raw[$nature] = $raw[$nature]->plus($amount->abs());
            $total = $raw[$nature]->toScale(2, RoundingMode::HalfEven);
            $increment = $total->minus($booked[$nature]);
            $booked[$nature] = $total;
            $signed = $nature === 'D' ? $increment->negated() : $increment;
            $dates[$day['date']] = (string) $signed->toScale(2);
            $allocations[] = ['date' => $day['date'], 'nature' => $nature, 'raw_amount' => (string) $amount->toScale(8),
                'booked_amount' => (string) $signed->toScale(2), 'rounding_residual' => (string) $amount->minus($signed)->toScale(8),
                'accumulated_raw_gross' => (string) $raw[$nature]->toScale(8), 'accumulated_booked_gross' => (string) $total];
        }
        if (CanonicalJson::encode($bridge['allocations'] ?? null) !== CanonicalJson::encode($allocations)) {
            return $this->stop('DAILY_JOURNAL_ROUNDING_ALLOCATION_CONFLICT');
        }
        foreach (['C' => 'CURVE_CREDIT', 'D' => 'CURVE_DEBIT'] as $nature => $id) {
            $expected = $components[$id]['entry']['computed_amount'] ?? '0.00';
            $grossRounding = $parent['public_yield_binding']['gross_rounding_reconciliation'] ?? null;
            $correction = is_array($grossRounding) && (($grossRounding['signed_adjustment'][0] === '-') === ($nature === 'D'))
                ? BigDecimal::of($grossRounding['signed_adjustment'])->abs() : BigDecimal::of('0.00');
            if (! $booked[$nature]->plus($correction)->isEqualTo($expected)) {
                return $this->stop('DAILY_JOURNAL_ROUNDING_COMPONENT_GROSS_CONFLICT');
            }
        }
        $grossRounding = $parent['public_yield_binding']['gross_rounding_reconciliation'] ?? null;
        if (! $booked['C']->minus($booked['D'])->plus(is_array($grossRounding) ? $grossRounding['signed_adjustment'] : '0.00')->isEqualTo($memory['monthly']['vVarMensal'])) {
            return $this->stop('DAILY_JOURNAL_ROUNDING_MONTHLY_CONFLICT');
        }
        $start = $parent['period'].'-01';
        $end = (new DateTimeImmutable($start))->format('Y-m-t');
        $proofValue = ['supplier_id' => $parent['supplier_id'], 'period' => $parent['period'], 'title' => $parent['title'],
            'daily_memory_hash' => CanonicalJson::hash($memory), 'method' => $bridge['method'], 'allocations' => $allocations,
            'gross_credit' => (string) $booked['C'], 'gross_debit' => (string) $booked['D'], 'normative_homologation_claimed' => false];
        if (is_array($grossRounding)) {
            $proofValue['gross_rounding_reconciliation'] = $grossRounding;
        }
        if (! (new FinancialPublicOfferDebtQualifier)->proof($bridge['independent_evidence'] ?? null, $proofValue, $parent['supplier_id'], $start, $end, $knownAt)) {
            return $this->stop('INDEPENDENT_DAILY_JOURNAL_ROUNDING_UNPROVEN');
        }

        return ['status' => 'RESOLVED', 'method' => $bridge['method'], 'booked_by_date' => $dates, 'allocations' => $allocations,
            'gross_credit' => (string) $booked['C'], 'gross_debit' => (string) $booked['D'], 'evidence_hash' => $bridge['independent_evidence']['source_hash'],
            'normative_homologation_claimed' => false, 'posting_performed' => false];
    }

    /** @return array<string,mixed> */
    private function line(string $account, string $nature, BigDecimal $amount, ?string $entry = null): array
    {
        return ['cCta' => $account, 'nature' => $nature, 'amount' => (string) $amount->toScale(2), 'financial_entry_id' => $entry];
    }

    /** @param list<array<string,mixed>> $lines
     * @return array<string,mixed>
     */
    private function journal(string $id, string $parent, string $date, array $lines): array
    {
        usort($lines, static fn (array $a, array $b): int => [$a['cCta'], $a['nature'], $a['financial_entry_id']] <=> [$b['cCta'], $b['nature'], $b['financial_entry_id']]);

        return ['event_id' => $id, 'parent_id' => $parent, 'date' => $date, 'lines' => $lines];
    }

    private function money(mixed $amount): BigDecimal
    {
        if (! is_string($amount) || preg_match('/^(0|[1-9][0-9]{0,14})\.[0-9]{2}$/', $amount) !== 1) {
            throw new InvalidArgumentException('Nonnegative canonical two-decimal amount required.');
        }

        return BigDecimal::of($amount);
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_TVM_BOOK_'.$reason, 'production_ready' => false];
    }
}
