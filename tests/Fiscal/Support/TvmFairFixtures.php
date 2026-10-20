<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;

require_once __DIR__.'/TvmCurrentFixtures.php';
require_once __DIR__.'/TvmBookFixtures.php';

function tvmFairFixture(string $case, ?array $providedLedger = null, string $version = '2026.10.06-28-draft4', ?array $providedRounding = null): array
{
    $root = dirname(__DIR__, 3);
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($root.'/resources/fiscal/bundles/consumption-phase4b-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $known = new DateTimeImmutable($manifest['knowledge_available_at']);
    $rule = $catalog['financial_operation']['base'];
    if ($providedLedger !== null) {
        $ledger = $providedLedger;
    } elseif ($case === 'mixed-history-fair-value') {
        [$ledger] = tvmCurrentFixture();
    } else {
        $fixture = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-fair-value-2026.10.06-28-draft4/'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);
        [$ledger] = tvmCurrentFirstFixture($case, $fixture, $rule);
    }
    [$operation, $month] = tvmMonthlyBookFixture('first-gain');
    $period = substr($ledger['period_end'], 0, 7);
    if (in_array($period, ['2026-07', '2026-08'], true)) {
        $periodStart = new DateTimeImmutable($period.'-01');
        $dateMap = ['2026-09-30' => $periodStart->format('Y-m-t'), '2026-09' => $period,
            '2026-08' => $periodStart->modify('-1 month')->format('Y-m')];
        $shift = function (mixed $value) use (&$shift, $dateMap): mixed {
            if (is_array($value)) {
                $value = array_map($shift, $value);
                if (($value['qualified'] ?? false) === true && isset($value['source_hash'])) {
                    unset($value['source_hash']);
                    $value['source_hash'] = CanonicalJson::hash($value);
                }

                return $value;
            }

            return is_string($value) ? strtr($value, $dateMap) : $value;
        };
        $month = $shift($month);
    }
    $operation['base'] = tvmCurrentBase($ledger, $rule, $known);
    expect($operation['base']['status'])->toBe('RESOLVED', $operation['base']['reason']);
    $parent = $operation['base']['realization_bindings']['tvm1'];
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $position = $input['position'];
    $policy = $rule['securities_realization']['analysis']['daily_memory_calculation'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], $known, $policy);
    expect($memory['status'])->toBe('RESOLVED');
    $asset = $position['cCta'];
    $roles = [$asset => ['1', 'D', '118915099', '1311033105', 'TVM000'],
        'cash000' => ['1', 'D', '118915099', '1111000008', 'CASH000']];
    $mapping = $byPart = [];
    foreach ($parent['financial_entries'] as $entry) {
        $part = $entry['realization_component'];
        $loss = $entry['bucket'] === 'DEDUCTION';
        $code = match ($part['component_kind']) {
            'CURRENT_PRODUCED_RETURN' => $loss ? 'curveloss000' : 'curve000',
            'DEFERRED_FAIR_VALUE_REALIZED' => 'FV000',
            'NEW_FAIR_VALUE_REALIZED' => $loss ? 'fvloss000' : 'fvnew000',
            default => $loss ? 'saleloss000' : 'sale000',
        };
        $ref = $loss ? '8152000008' : ($part['component_kind'] === 'DISPOSAL_NEW_PRODUCED_RETURN' ? '7157500009' : '7151000000');
        $roles[$code] = [$loss ? '5' : '4', $code === 'FV000' ? 'V' : ($loss ? 'D' : 'C'), $part['expected_codTrib'], $ref, $loss ? 'EXPENSE000' : 'REVENUE000'];
        $mapping[$entry['id']] = $code;
        $byPart[$part['component_id']] = ['entry' => $entry, 'account' => $code];
    }
    $publicYield = $parent['public_yield_binding'] ?? null;
    if (is_array($publicYield)) {
        $roles[$asset][2] = $publicYield['expected_codTrib'];
        $roles[$publicYield['result_account_code']] = ['4', 'C', $publicYield['expected_codTrib'] === '110113001' ? '311011201' : '311011202', '7151000000', 'REVENUE000'];
        foreach (['CURVE_CREDIT' => ['gross_result_credit', 'REVENUE'], 'CURVE_DEBIT' => ['gross_result_debit', 'DEDUCTION']] as $id => [$field, $bucket]) {
            if (! BigDecimal::of($publicYield[$field])->isZero()) {
                $byPart[$id] = ['account' => $publicYield['result_account_code'],
                    'entry' => ['id' => $parent['public_yield_entry_id'], 'bucket' => $bucket, 'computed_amount' => $publicYield[$field]]];
            }
        }
    }
    $fair = $parent['analysis']['subaccounts'] !== [];
    $releases = [];
    if ($fair) {
        $firstSale = $input['realizations'][0];
        $openingFair = BigDecimal::of($parent['analysis']['recognition_inventory']['opening']['deferred_fair_value_realized']);
        $sign = $openingFair->isZero() ? BigDecimal::of($firstSale['components']['new_fair_value_change_at_sale'])->isNegative() : $openingFair->isNegative();
        $roles['FV000'] ??= [$sign ? '5' : '4', 'V', $sign ? '411011701' : '311011702', $sign ? '8152000008' : '7151000000', $sign ? 'EXPENSE000' : 'REVENUE000'];
        foreach ([...$input['realizations'], ...($parent['other_release_realizations'] ?? [])] as $sale) {
            $releases[] = ['operation_id' => $sale['operation_id'], 'prior_deferred_value' => $sale['components']['deferred_fair_value_realized'],
                'new_at_sale_value' => $sale['components']['new_fair_value_change_at_sale']];
        }
        if (! $openingFair->isZero()) {
            $roles[$sign ? 'fvloss000' : 'fvnew000'] ??= $sign ? ['5', 'D', '411011102', '8152000008', 'EXPENSE000'] : ['4', 'C', '311011107', '7151000000', 'REVENUE000'];
        }
        if (count($parent['analysis']['subaccounts']) > 1 && $openingFair->isZero()) {
            foreach ($parent['analysis']['subaccounts'] as $label) {
                if ($label === 'FV000') {
                    continue;
                }
                $sample = array_values(array_filter($input['realizations'], static fn (array $sale): bool => ($sale['history']['fair_value_subaccount'] ?? null) === $label))[0];
                $negative = BigDecimal::of($sample['components']['new_fair_value_change_at_sale'])->isNegative();
                $roles[$label] = [$negative ? '5' : '4', 'V', $negative ? '411011701' : '311011702', $negative ? '8152000008' : '7151000000', $negative ? 'EXPENSE000' : 'REVENUE000'];
            }
        }
    }
    $rawPlan = $month['pgcc']['accounts'];
    $prototype = array_values(array_filter($rawPlan, static fn (array $row): bool => $row['cCta'] === 'interest000'))[0];
    $month['pgcc']['accounts'] = array_values(array_filter($rawPlan, static fn (array $row): bool => $row['indCta'] === 'S'
        || in_array($row['cCta'], ['interest000', 'principal000', 'funding000', 'correspondent000'], true)));
    foreach ($roles as $code => [$nature, $normal, $tax, $ref, $superior]) {
        $account = [...$prototype, 'cCtaInterna' => substr($code, 0, -3), 'cCta' => $code, 'codNat' => $nature, 'natCta' => $normal,
            'codTrib' => $tax, 'cCtaRef' => $ref, 'cCtaSup' => $superior, 'nomeCta' => 'Synthetic realization '.$code];
        $account['mapping_evidence'] = tvmBookProof(['institution_type' => 'COMMERCIAL_BANK', 'mapping' => array_intersect_key($account,
            array_flip(['cCta', 'cCtaRef', 'codTrib', 'natCta', 'codNat']))]);
        $account['mapping_evidence']['valid_from'] = '2026-01-01';
        $account['mapping_evidence']['valid_to'] = '2026-12-31';
        unset($account['mapping_evidence']['source_hash']);
        $account['mapping_evidence']['source_hash'] = CanonicalJson::hash($account['mapping_evidence']);
        $month['pgcc']['accounts'][] = $account;
    }
    unset($month['pgcc']['source_hash']);
    $month['pgcc']['source_hash'] = CanonicalJson::hash($month['pgcc']);
    $month['book']['pgcc_source_hash'] = $month['prerequisites']['pgcc_source_hash'] = $month['pgcc']['source_hash'];
    $journals = $totals = [];
    $bookedByDate = $providedRounding === null ? [] : array_column($providedRounding['allocations'], 'booked_amount', 'date');
    foreach ($roles as $code => $_) {
        $totals[$code] = ['D' => BigDecimal::of('0.00'), 'C' => BigDecimal::of('0.00'), 'adjustD' => BigDecimal::of('0.00'), 'adjustC' => BigDecimal::of('0.00'), 'links' => []];
    }
    $makeLine = static fn (string $code, string $nature, string $amount, ?string $id = null): array => ['cCta' => $code, 'nature' => $nature, 'amount' => $amount, 'financial_entry_id' => $id];
    $addJournal = function (string $id, string $date, array $lines) use (&$journals, &$totals): void {
        usort($lines, static fn (array $a, array $b): int => [$a['cCta'], $a['nature'], $a['financial_entry_id']] <=> [$b['cCta'], $b['nature'], $b['financial_entry_id']]);
        $journals[] = ['event_id' => $id, 'parent_id' => 'tvm1', 'date' => $date, 'lines' => $lines];
        foreach ($lines as $line) {
            $totals[$line['cCta']][$line['nature']] = $totals[$line['cCta']][$line['nature']]->plus($line['amount']);
            if ($line['financial_entry_id'] !== null) {
                $totals[$line['cCta']]['links'][$line['financial_entry_id']] = true;
            }
        }
    };
    // Source trades and independent component declarations describe the supplied synthetic book.
    foreach ($memory['daily_memory'] as $day) {
        foreach ($position['operations'] as $event) {
            if ($event['date'] !== $day['date']) {
                continue;
            }
            if ($event['kind'] === 'BUY') {
                $addJournal('BUY:'.$event['id'], $day['date'], [$makeLine($asset, 'D', $event['book_amount']), $makeLine('cash000', 'C', $event['book_amount'])]);

                continue;
            }
            $lines = [$makeLine($asset, 'C', $event['book_amount']), $makeLine('cash000', 'D', $event['sale_proceeds'] ?? $event['book_amount'])];
            foreach ($event['kind'] === 'SELL' ? ['new_produced_return_at_sale', 'new_fair_value_change_at_sale'] : [] as $key) {
                $component = $byPart['SALE:'.$event['id'].':'.$key] ?? null;
                if ($component !== null) {
                    $entry = $component['entry'];
                    $lines[] = $makeLine($component['account'], $entry['bucket'] === 'DEDUCTION' ? 'D' : 'C', $entry['computed_amount'], $entry['id']);
                }
            }
            $addJournal($event['kind'].':'.$event['id'], $day['date'], $lines);
            $prior = $byPart[($event['kind'] === 'SELL' ? 'SALE:' : 'RELEASE:').$event['id'].':deferred_fair_value_realized'] ?? null;
            if ($prior !== null) {
                $amount = $prior['entry']['computed_amount'];
                $counter = $sign ? 'fvloss000' : 'fvnew000';
                $originAdjustment = $sign ? 'adjustC' : 'adjustD';
                $counterAdjustment = $sign ? 'adjustD' : 'adjustC';
                $addJournal('FAIR_VALUE_RECLASS:'.$event['id'], $day['date'], [$makeLine('FV000', $sign ? 'C' : 'D', $amount, $prior['entry']['id']), $makeLine($counter, $sign ? 'D' : 'C', $amount)]);
                $totals['FV000'][$originAdjustment] = $totals['FV000'][$originAdjustment]->plus($amount);
                $totals[$counter][$counterAdjustment] = $totals[$counter][$counterAdjustment]->plus($amount);
            }
        }
        $curve = BigDecimal::of($day['appropriation']);
        if (! $curve->isZero()) {
            $component = $byPart[$curve->isNegative() ? 'CURVE_DEBIT' : 'CURVE_CREDIT'];
            $amount = (string) ($providedRounding === null ? $curve->abs()->toScale(2) : BigDecimal::of($bookedByDate[$day['date']])->abs());
            if (BigDecimal::of($amount)->isZero()) {
                continue;
            }
            $addJournal('CURVE:'.$day['date'], $day['date'], [$makeLine($asset, $curve->isNegative() ? 'C' : 'D', $amount),
                $makeLine($component['account'], $curve->isNegative() ? 'D' : 'C', $amount, $component['entry']['id'])]);
            if (isset($day['appropriation_before_operation_id'])) {
                $curveJournal = array_pop($journals);
                $index = array_search('SELL:'.$day['appropriation_before_operation_id'], array_column($journals, 'event_id'), true);
                expect($index)->not->toBeFalse();
                array_splice($journals, $index, 0, [$curveJournal]);
            }
        }
    }
    if (isset($publicYield['gross_rounding_reconciliation'])) {
        $journal = $publicYield['gross_rounding_reconciliation']['journal'];
        $addJournal($journal['event_id'], $journal['date'], $journal['lines']);
    }
    if (is_array($publicYield)) {
        $totals[$publicYield['result_account_code']]['adjustC'] = BigDecimal::of($publicYield['gross_result_credit']);
    }
    $month['book']['accounts'] = array_slice($month['book']['accounts'], 0, 4);
    foreach ($totals as $code => $sum) {
        $nature = $roles[$code][0];
        $opening = $code === $asset ? $memory['monthly']['vSaldoContIni'] : ($code === 'cash000' ? '10000.00' : '0.00');
        $normal = $nature === '5' ? 'D' : ($nature === '4' ? 'C' : 'D');
        $final = BigDecimal::of($opening)->plus($sum['D'])->minus($sum['C']);
        $tax = $nature === '1' ? BigDecimal::of('0.00') : ($normal === 'C' ? $sum['C']->minus($sum['adjustC'])->plus($sum['adjustD']) : $sum['D']->minus($sum['adjustD'])->plus($sum['adjustC']));
        $month['book']['accounts'][] = ['cCta' => $code, 'natSaldoInic' => $normal, 'vSaldoInic' => $opening,
            'vMovDebt' => (string) $sum['D'], 'vMovCred' => (string) $sum['C'], 'vAjusteDebt' => (string) ($nature === '1' ? $sum['D'] : $sum['adjustD']),
            'vAjusteCred' => (string) $sum['adjustC'], 'natSaldoFinal' => $final->isZero() ? $normal : ($final->isNegative() ? 'C' : 'D'),
            'vSaldoFinal' => (string) $final->abs()->toScale(2), 'natVApur' => $normal, 'vApur' => (string) $tax->toScale(2), 'financial_entry_ids' => array_keys($sum['links']),
            'previous_balance' => ['period' => (new DateTimeImmutable($period.'-01'))->modify('-1 month')->format('Y-m'), 'amount' => $opening, 'nature' => $normal, 'source_hash' => str_repeat('e', 64)]];
    }
    $month['book']['pgcc_accounts'] = array_values(array_map(static fn (array $row): array => array_intersect_key($row, array_flip(['cCta', 'indCta', 'codNat', 'valid_from', 'valid_to'])),
        array_filter($month['pgcc']['accounts'], static fn (array $row): bool => $row['indCta'] === 'A')));
    $month['book']['book_source_hash'] = CanonicalJson::hash(['accounts' => $month['book']['accounts'], 'journals' => $journals]);
    $detail = ['parent_id' => 'tvm1', 'asset_account' => $asset, 'cash_account' => 'cash000', 'component_accounts' => $mapping, 'daily_memory' => $memory, 'journals' => $journals];
    if (isset($publicYield['gross_rounding_reconciliation'])) {
        $detail['gross_rounding_reconciliation'] = $publicYield['gross_rounding_reconciliation'];
    }
    if ($providedRounding !== null) {
        $detail['daily_journal_rounding'] = $providedRounding;
    }
    if ($fair) {
        $detail['fair_value_bridge'] = ['accounting_method' => 'PROVIDED_REALIZATION_RECLASSIFICATION_NO_AUTOMATIC_POSTING', 'normative_homologation_claimed' => false,
            'subaccounts' => [['cCta' => 'FV000', 'reclassification_counter_account' => $sign ? 'fvloss000' : 'fvnew000',
                'opening_deferred_value' => $parent['analysis']['recognition_inventory']['opening']['deferred_fair_value_realized'],
                'closing_deferred_value' => $parent['analysis']['recognition_inventory']['closing']['deferred_fair_value_realized'], 'releases' => $releases]]];
        if (count($parent['analysis']['subaccounts']) > 1 && $openingFair->isZero()) {
            $detail['fair_value_bridge']['subaccounts'] = [];
            foreach ($parent['analysis']['subaccounts'] as $label) {
                $ids = array_column(array_filter($input['realizations'], static fn (array $sale): bool => ($sale['history']['fair_value_subaccount'] ?? null) === $label), 'operation_id');
                $detail['fair_value_bridge']['subaccounts'][] = ['cCta' => $label,
                    'reclassification_counter_account' => $roles[$label][0] === '5' ? 'fvloss000' : 'fvnew000',
                    'opening_deferred_value' => '0.00000000', 'closing_deferred_value' => '0.00000000',
                    'releases' => array_values(array_filter($releases, static fn (array $release): bool => in_array($release['operation_id'], $ids, true)))];
            }
        }
    }
    $month['securities_realization_book'] = [$detail];
    $month['financial_scenario'] = $case.'-financial.json';
    $closing = $catalog['dere_monthly_closing_projection'];
    $pgcc = (new DerePgccQualifier)->resolve($month['pgcc'], $closing['pgcc_catalogs']['reference'], $closing['pgcc_catalogs']['tax_codes'], $known, false, $closing['pgcc_qualification_policy']);
    expect($pgcc['status'])->toBe('RESOLVED');
    $month = tvmBookSeal($operation, $month, $pgcc);
    if (isset($publicYield['gross_rounding_reconciliation'])) {
        $proof = &$month['securities_realization_book'][0]['independent_evidence'];
        $proof['value']['gross_rounding_reconciliation'] = $publicYield['gross_rounding_reconciliation'];
        unset($proof['source_hash']);
        $proof['source_hash'] = CanonicalJson::hash($proof);
        unset($proof);
    }
    if ($fair) {
        $proof = &$month['securities_realization_book'][0]['independent_evidence'];
        $proof['value']['fair_value_bridge'] = $detail['fair_value_bridge'];
        unset($proof['source_hash']);
        $proof['source_hash'] = CanonicalJson::hash($proof);
        unset($proof);
    }
    if ($providedRounding !== null) {
        $proof = &$month['securities_realization_book'][0]['independent_evidence'];
        $proof['value']['daily_journal_rounding'] = $providedRounding;
        unset($proof['source_hash']);
        $proof['source_hash'] = CanonicalJson::hash($proof);
        unset($proof);
    }
    $rules = ['closing' => $closing, 'balance' => $catalog['dere_trial_balance_projection'], 'deductions' => $catalog['dere_deduction_projection'],
        'pgcc_projection' => $catalog['dere_pgcc_projection'], 'contributor_projection' => $catalog['dere_contributor_projection']];

    return [$operation, $month, $known, $rules, $ledger, $manifest];
}
