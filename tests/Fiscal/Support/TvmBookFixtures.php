<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\ConsumptionReform\DereSecuritiesRealizationBindingQualifier;
use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;

function tvmBookProof(array $value, string $period = '2026-09'): array
{
    $proof = ['qualified' => true, 'evidence_id' => 'SYNTHETIC.BOOK.JOURNALS', 'supplier_id' => '11111111000191',
        'valid_from' => $period.'-01', 'valid_to' => (new DateTimeImmutable($period.'-01'))->format('Y-m-t'), 'observed_on' => '2026-10-06',
        'scope' => 'SYNTHETIC_INDEPENDENT_ACCOUNTING_JOURNAL_AND_TAX_COMPONENT_MAPPING', 'value' => $value];
    $proof['source_hash'] = CanonicalJson::hash($proof);

    return $proof;
}

function tvmBookSeal(array $operation, array $month, array $pgcc): array
{
    $row = &$month['securities_realization_book'][0];
    $period = substr($operation['base']['period_end'], 0, 7);
    $row['independent_evidence'] = tvmBookProof(['supplier_id' => $operation['base']['supplier_id'], 'period' => $period, 'parent_id' => 'tvm1',
        'qualification_hash' => CanonicalJson::hash($operation['base']['realization_bindings']['tvm1']),
        'asset_account' => $row['asset_account'], 'cash_account' => $row['cash_account'], 'component_accounts' => $row['component_accounts'],
        'journals' => $row['journals'], 'daily_memory_hash' => CanonicalJson::hash($row['daily_memory']),
        'book_source_hash' => $month['book']['book_source_hash'], 'pgcc_source_hash' => $pgcc['source_hash'],
        'book_content_hash' => CanonicalJson::hash($month['book']), 'pgcc_content_hash' => CanonicalJson::hash($pgcc)], $period);
    unset($row);

    return $month;
}

function tvmBookFixture(string $case = 'first-gain'): array
{
    $root = dirname(__DIR__, 3);
    $snapshot = json_decode(file_get_contents($root.'/storage/app/phase4b-realization-base-draft-20261006-28/'.$case.'-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
    $operation = array_values(array_filter($snapshot['result']['trace'], static fn (array $row): bool => $row['stage'] === 'FINANCIAL_OPERATION_AND_BASE'))[0];
    $known = new DateTimeImmutable($snapshot['input']['knowledge_at']);
    $ledger = $snapshot['input']['facts']['rtc.financial_ledger'][0];
    $position = $ledger['entries'][4]['securities_realization']['analysis_input']['position'];
    $policy = $snapshot['knowledge']['documents']['rtc_code_catalog']['financial_operation']['base']['securities_realization']['analysis']['daily_memory_calculation'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], $known, $policy);
    expect($memory['status'])->toBe('RESOLVED');
    $rule = ['contract' => DereSecuritiesRealizationBindingQualifier::CONTRACT, 'status' => 'ACTIVE', 'published_at' => $known->format(DATE_ATOM),
        'source_hash' => $policy['source_hash'], 'table_source_hash' => '7296b225e15fb4b395a41a9413ada1190cc2229d64334bf78d7b69f0fb8019f9'];
    $mapping = $plan = $journals = $totals = [];
    $asset = $position['cCta'];
    $cash = 'cash000';
    foreach ([$asset, $cash] as $code) {
        $plan[] = ['cCta' => $code, 'codNat' => '1', 'indCta' => 'A', 'natCta' => 'D'];
        $totals[$code] = ['D' => BigDecimal::of('0.00'), 'C' => BigDecimal::of('0.00'), 'links' => []];
    }
    $componentByOperation = [];
    foreach ($operation['base']['realization_bindings']['tvm1']['financial_entries'] as $i => $entry) {
        $code = 'result'.$i.'000';
        $mapping[$entry['id']] = $code;
        $plan[] = ['cCta' => $code, 'codNat' => $entry['bucket'] === 'DEDUCTION' ? '5' : '4', 'indCta' => 'A',
            'natCta' => $entry['bucket'] === 'DEDUCTION' ? 'D' : 'C', 'codTrib' => $entry['realization_binding']['expected_codTrib']];
        $totals[$code] = ['D' => BigDecimal::of('0.00'), 'C' => BigDecimal::of('0.00'), 'links' => [$entry['id']]];
        $componentByOperation[explode(':', $entry['realization_component']['component_id'])[1]] = [$entry, $code];
    }
    // The independent source trades define the balanced journals; no output from the binder is reused.
    foreach ($position['operations'] as $event) {
        $lines = [['cCta' => $asset, 'nature' => $event['kind'] === 'BUY' ? 'D' : 'C', 'amount' => $event['book_amount'], 'financial_entry_id' => null],
            ['cCta' => $cash, 'nature' => $event['kind'] === 'BUY' ? 'C' : 'D',
                'amount' => $event['kind'] === 'BUY' ? $event['book_amount'] : $event['sale_proceeds'], 'financial_entry_id' => null]];
        if ($event['kind'] === 'SELL') {
            [$entry, $code] = $componentByOperation[$event['id']];
            $lines[] = ['cCta' => $code, 'nature' => $entry['bucket'] === 'DEDUCTION' ? 'D' : 'C',
                'amount' => $entry['computed_amount'], 'financial_entry_id' => $entry['id']];
        }
        usort($lines, static fn (array $a, array $b): int => [$a['cCta'], $a['nature'], $a['financial_entry_id']] <=> [$b['cCta'], $b['nature'], $b['financial_entry_id']]);
        $journals[] = ['event_id' => $event['kind'].':'.$event['id'], 'parent_id' => 'tvm1', 'date' => $event['date'], 'lines' => $lines];
        foreach ($lines as $line) {
            $totals[$line['cCta']][$line['nature']] = $totals[$line['cCta']][$line['nature']]->plus($line['amount']);
        }
    }
    $accounts = [];
    foreach ($totals as $code => $sum) {
        $accounts[] = ['cCta' => $code, 'natSaldoInic' => 'D', 'vSaldoInic' => $code === $asset ? $memory['monthly']['vSaldoContIni'] : '0.00',
            'vMovDebt' => (string) $sum['D'], 'vMovCred' => (string) $sum['C'], 'natSaldoFinal' => 'D',
            'vSaldoFinal' => $code === $asset ? $memory['monthly']['vSaldoContFin'] : '0.00', 'vApur' => '0.00', 'financial_entry_ids' => $sum['links']];
    }
    $pgcc = ['status' => 'RESOLVED', 'source_hash' => str_repeat('c', 64), 'accounts' => $plan];
    $month = ['book' => ['supplier_id' => $position['supplier_id'], 'closing_frequency' => 'M', 'book_source_hash' => str_repeat('b', 64), 'accounts' => $accounts],
        'securities_realization_book' => [['parent_id' => 'tvm1', 'asset_account' => $asset, 'cash_account' => $cash,
            'component_accounts' => $mapping, 'daily_memory' => $memory, 'journals' => $journals]]];

    return [$operation, tvmBookSeal($operation, $month, $pgcc), $pgcc, $known, $rule];
}

function tvmMonthlyBookFixture(string $case): array
{
    [$operation, $source, $unitPgcc] = tvmBookFixture($case);
    $root = dirname(__DIR__, 3);
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.06-28-draft3.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($root.'/resources/fiscal/bundles/consumption-phase4b-2026.10.06-28-draft3.json'), true, 512, JSON_THROW_ON_ERROR);
    $known = new DateTimeImmutable($manifest['knowledge_available_at']);
    $month = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/dere-variable-yield-2026.10.06-27/constant.json'), true, 512, JSON_THROW_ON_ERROR);
    unset($month['debt_yield'], $month['corpus_expectation']);
    $month['financial_scenario'] = $case.'-financial.json';
    $month['evidence_scope'] = 'SYNTHETIC_PRIVATE_REALIZATION_JOURNALS_NOT_TAXPAYER_BOOK_APPROVAL';
    $month['prerequisites']['D2101_required'] = false;
    $month['pgcc_identity']['generated_at'] = '2026-10-06T08:00:00.000-03:00';
    $month['pgcc_identity']['observed_on'] = $month['pgcc']['observed_on'] = '2026-10-06';
    $month['pgcc']['accounts'] = array_values(array_filter($month['pgcc']['accounts'], static fn (array $row): bool => ! in_array($row['cCta'], ['debt000', 'debtfund000'], true)));
    $prototype = $month['pgcc']['accounts'][3];
    foreach ($unitPgcc['accounts'] as $row) {
        $code = $row['cCta'];
        $account = $prototype;
        $internal = substr($code, 0, -3);
        $account['cCtaInterna'] = $internal;
        $account['cCta'] = $code;
        $account['nomeCta'] = 'Synthetic qualified private realization '.$internal;
        $account['natCta'] = $row['natCta'];
        $account['codNat'] = $row['codNat'];
        if ($code === 'debt000') {
            $account['cCtaRef'] = '1311033105';
            $account['cCtaSup'] = 'TVM000';
            $account['codTrib'] = '118915099';
        } elseif ($code === 'cash000') {
            $account['cCtaRef'] = '1111000008';
            $account['cCtaSup'] = 'CASH000';
            $account['codTrib'] = '118915099';
        } else {
            $account['cCtaRef'] = $row['codNat'] === '5' ? '8152000008' : '7157500009';
            $account['cCtaSup'] = $row['codNat'] === '5' ? 'EXPENSE000' : 'REVENUE000';
            $account['codTrib'] = $row['codTrib'];
        }
        $account['mapping_evidence'] = tvmBookProof(['institution_type' => 'COMMERCIAL_BANK', 'mapping' => array_intersect_key($account,
            array_flip(['cCta', 'cCtaRef', 'codTrib', 'natCta', 'codNat']))]);
        $account['mapping_evidence']['valid_from'] = '2026-01-01';
        $account['mapping_evidence']['valid_to'] = '2026-12-31';
        unset($account['mapping_evidence']['source_hash']);
        $account['mapping_evidence']['source_hash'] = CanonicalJson::hash($account['mapping_evidence']);
        $month['pgcc']['accounts'][] = $account;
    }
    $cashRoot = $month['pgcc']['accounts'][0];
    $cashRoot['cCtaInterna'] = 'CASH';
    $cashRoot['cCta'] = 'CASH000';
    $cashRoot['cCtaRef'] = '1100000002';
    $cashRoot['nomeCta'] = 'Disponibilidades';
    $cashRoot['mapping_evidence'] = tvmBookProof(['institution_type' => 'COMMERCIAL_BANK', 'mapping' => array_intersect_key($cashRoot,
        array_flip(['cCta', 'cCtaRef', 'natCta', 'codNat']))]);
    $cashRoot['mapping_evidence']['valid_from'] = '2026-01-01';
    $cashRoot['mapping_evidence']['valid_to'] = '2026-12-31';
    unset($cashRoot['mapping_evidence']['source_hash']);
    $cashRoot['mapping_evidence']['source_hash'] = CanonicalJson::hash($cashRoot['mapping_evidence']);
    $month['pgcc']['accounts'][] = $cashRoot;
    unset($month['pgcc']['source_hash']);
    $month['pgcc']['source_hash'] = CanonicalJson::hash($month['pgcc']);
    $month['book']['pgcc_source_hash'] = $month['prerequisites']['pgcc_source_hash'] = $month['pgcc']['source_hash'];
    $month['book']['accounts'] = array_slice($month['book']['accounts'], 0, 4);
    $month['book']['accounts'][0]['vMovCred'] = $month['book']['accounts'][0]['vSaldoFinal'] = '1000.00';
    $month['book']['accounts'][0]['vAjusteCred'] = '0.00';
    $month['book']['accounts'][0]['financial_entry_ids'] = ['interest'];
    foreach ($source['book']['accounts'] as $row) {
        $isAsset = $row['cCta'] === 'debt000';
        $isCash = $row['cCta'] === 'cash000';
        $sum = BigDecimal::of($row['vMovDebt'])->minus($row['vMovCred']);
        if ($isCash) {
            $row['vSaldoInic'] = '10000.00';
            $sum = $sum->plus('10000.00');
        }
        $row['natSaldoFinal'] = $sum->isNegative() ? 'C' : 'D';
        $row['vSaldoFinal'] = (string) $sum->abs()->toScale(2);
        if ($isAsset || $isCash) {
            $row['vAjusteDebt'] = $row['vMovDebt'];
            $row['vAjusteCred'] = '0.00';
            $row['natVApur'] = 'D';
        } else {
            $row['natVApur'] = $sum->isNegative() ? 'C' : 'D';
            $row['natSaldoInic'] = $row['natVApur'];
            $row['vApur'] = (string) $sum->abs()->toScale(2);
        }
        $row['previous_balance'] = ['period' => '2026-08', 'amount' => $row['vSaldoInic'], 'nature' => $row['natSaldoInic'], 'source_hash' => str_repeat('d', 64)];
        $month['book']['accounts'][] = $row;
    }
    $month['book']['pgcc_accounts'] = array_values(array_map(static fn (array $row): array => array_intersect_key($row, array_flip(['cCta', 'indCta', 'codNat', 'valid_from', 'valid_to'])),
        array_filter($month['pgcc']['accounts'], static fn (array $row): bool => $row['indCta'] === 'A')));
    $month['book']['book_source_hash'] = CanonicalJson::hash(['accounts' => $month['book']['accounts'], 'journals' => $source['securities_realization_book'][0]['journals']]);
    $month['securities_realization_book'] = $source['securities_realization_book'];
    $closing = $catalog['dere_monthly_closing_projection'];
    $pgcc = (new DerePgccQualifier)->resolve($month['pgcc'], $closing['pgcc_catalogs']['reference'], $closing['pgcc_catalogs']['tax_codes'], $known, false, $closing['pgcc_qualification_policy']);
    expect($pgcc['status'])->toBe('RESOLVED');
    $month = tvmBookSeal($operation, $month, $pgcc);
    $rules = ['closing' => $closing, 'balance' => $catalog['dere_trial_balance_projection'], 'deductions' => $catalog['dere_deduction_projection'],
        'pgcc_projection' => $catalog['dere_pgcc_projection'], 'contributor_projection' => $catalog['dere_contributor_projection']];

    return [$operation, $month, $known, $rules];
}
