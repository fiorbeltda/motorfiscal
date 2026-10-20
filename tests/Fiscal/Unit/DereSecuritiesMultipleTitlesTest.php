<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use Brick\Math\BigDecimal;

require_once __DIR__.'/../Support/TvmFairFixtures.php';

function tvmMultiRename(mixed $value): mixed
{
    if (is_string($value)) {
        return str_replace(['tvm1', 'FV000', 'fvloss000'], ['tvm2', 'FV2000', 'fvloss2000'], $value);
    }
    if (! is_array($value)) {
        return $value;
    }
    $result = [];
    foreach ($value as $key => $child) {
        $result[is_string($key) ? tvmMultiRename($key) : $key] = tvmMultiRename($child);
    }

    return $result;
}

function tvmMultipleFixture(): array
{
    $root = dirname(__DIR__, 3);
    $version = '2026.10.07-28-draft6';
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base'];
    $single = [];
    foreach (['gain', 'loss'] as $case) {
        $fixture = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-multiple-titles-'.$version.'/'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);
        [$ledger] = tvmCurrentFirstFixture($case, $fixture, $rule);
        $single[] = tvmFairFixture($case, $ledger, $version);
    }
    [$operation, $month, $known, $rules, $ledger, $manifest] = $single[0];
    $second = tvmMultiRename($single[1][4]['entries'][4]);
    $second['document_id'] .= '.SECOND';
    $ledger['entries'][] = $second;
    // The second title has no prior snapshot. Rebuild each input/evidence hash after renaming its parent.
    $detail = &$ledger['entries'][5]['securities_realization'];
    foreach ($detail['analysis_input']['realizations'] as &$sale) {
        unset($sale['history_evidence']['source_hash']);
        $sale['history_evidence']['source_hash'] = CanonicalJson::hash($sale['history_evidence']);
        $sale['component_evidence']['value']['history_evidence_hash'] = $sale['history_evidence']['source_hash'];
        unset($sale['component_evidence']['source_hash']);
        $sale['component_evidence']['source_hash'] = CanonicalJson::hash($sale['component_evidence']);
    }
    unset($sale);
    $complete = &$detail['analysis_input']['complete_evidence'];
    $complete['value']['realizations'] = $detail['analysis_input']['realizations'];
    unset($complete['source_hash']);
    $complete['source_hash'] = CanonicalJson::hash($complete);
    unset($complete);
    $loss = &$detail['deduction_evidence'][0]['independent_evidence'];
    $loss['value']['analysis_input_hash'] = CanonicalJson::hash($detail['analysis_input']);
    unset($loss['source_hash']);
    $loss['source_hash'] = CanonicalJson::hash($loss);
    unset($detail, $loss);
    $ledger = sealTvmCurrentLedger($ledger);
    $operation['base'] = tvmCurrentBase($ledger, $rule, $known);
    expect($operation['base']['status'])->toBe('RESOLVED');
    $secondMonth = tvmMultiRename($single[1][1]);
    $plan = array_column($month['pgcc']['accounts'], null, 'cCta');
    foreach ($secondMonth['pgcc']['accounts'] as $account) {
        if (isset($plan[$account['cCta']])) {
            continue;
        }
        $account['cCtaInterna'] = substr($account['cCta'], 0, -3);
        $proof = &$account['mapping_evidence'];
        unset($proof['source_hash']);
        $proof['source_hash'] = CanonicalJson::hash($proof);
        unset($proof);
        $plan[$account['cCta']] = $account;
    }
    $month['pgcc']['accounts'] = array_values($plan);
    unset($month['pgcc']['source_hash']);
    $month['pgcc']['source_hash'] = CanonicalJson::hash($month['pgcc']);
    $book = array_column($month['book']['accounts'], null, 'cCta');
    foreach (array_slice($secondMonth['book']['accounts'], 4) as $account) {
        $code = $account['cCta'];
        if (! isset($book[$code])) {
            $book[$code] = $account;

            continue;
        }
        expect($code)->toBe('cash000');
        foreach (['vMovDebt', 'vMovCred', 'vAjusteDebt', 'vAjusteCred'] as $field) {
            $book[$code][$field] = (string) BigDecimal::of($book[$code][$field])->plus($account[$field])->toScale(2);
        }
        $net = BigDecimal::of($book[$code]['vSaldoInic'])->plus($book[$code]['vMovDebt'])->minus($book[$code]['vMovCred']);
        $book[$code]['natSaldoFinal'] = $net->isNegative() ? 'C' : 'D';
        $book[$code]['vSaldoFinal'] = (string) $net->abs()->toScale(2);
    }
    $month['book']['accounts'] = array_values($book);
    $month['securities_realization_book'][] = $secondMonth['securities_realization_book'][0];
    $month['book']['pgcc_source_hash'] = $month['prerequisites']['pgcc_source_hash'] = $month['pgcc']['source_hash'];
    $month['book']['pgcc_accounts'] = array_values(array_map(static fn (array $account): array => array_intersect_key($account,
        array_flip(['cCta', 'indCta', 'codNat', 'valid_from', 'valid_to'])), array_filter($month['pgcc']['accounts'], static fn (array $account): bool => $account['indCta'] === 'A')));
    $month['book']['book_source_hash'] = CanonicalJson::hash(['accounts' => $month['book']['accounts'], 'journals' => array_column($month['securities_realization_book'], 'journals')]);
    $pgcc = (new DerePgccQualifier)->resolve($month['pgcc'], $rules['closing']['pgcc_catalogs']['reference'], $rules['closing']['pgcc_catalogs']['tax_codes'],
        $known, false, $rules['closing']['pgcc_qualification_policy']);
    expect($pgcc['status'])->toBe('RESOLVED');
    foreach ($month['securities_realization_book'] as &$row) {
        $row['independent_evidence'] = tvmBookProof(['supplier_id' => $ledger['supplier_id'], 'period' => '2026-09', 'parent_id' => $row['parent_id'],
            'qualification_hash' => CanonicalJson::hash($operation['base']['realization_bindings'][$row['parent_id']]),
            'asset_account' => $row['asset_account'], 'cash_account' => $row['cash_account'], 'component_accounts' => $row['component_accounts'],
            'journals' => $row['journals'], 'daily_memory_hash' => CanonicalJson::hash($row['daily_memory']),
            'book_source_hash' => $month['book']['book_source_hash'], 'pgcc_source_hash' => $pgcc['source_hash'],
            'book_content_hash' => CanonicalJson::hash($month['book']), 'pgcc_content_hash' => CanonicalJson::hash($pgcc), 'fair_value_bridge' => $row['fair_value_bridge']]);
    }
    unset($row);
    $month['financial_scenario'] = 'two-titles-financial.json';

    return [$operation, $month, $known, $rules, $ledger, $manifest];
}

it('reconciles two independent title realizations with one shared cash account and separate gains and losses', function (): void {
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmMultipleFixture();
    $projection = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($operation['base']['net_base'])->toBe('680.00')->and($projection['status'])->toBe('PROJECTED')
        ->and($projection['securities_realization_binding']['parents'])->toHaveCount(2);
    $book = array_column($month['book']['accounts'], null, 'cCta');
    expect($book['cash000']['vMovDebt'])->toBe('19980.00')->and($book['cash000']['vMovCred'])->toBe('20000.00')
        ->and($book['cash000']['vSaldoFinal'])->toBe('9980.00');
    if (getenv('TVM_MULTI_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-two-private-titles-shared-cash';
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-multiple-titles-2026.10.07-28-draft6/two-titles-'.$suffix.'.json';
            if (is_file($path)) {
                expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));
            } else {
                $stream = fopen($path, 'x');
                expect($stream)->not->toBeFalse();
                fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                fclose($stream);
            }
        }
    }
});

it('rejects incomplete parent coverage and cross-title loss account substitution', function (string $tamper, string $reason): void {
    [$operation, $month, $known, $rules] = tvmMultipleFixture();
    if ($tamper === 'coverage') {
        array_pop($month['securities_realization_book']);
    } else {
        $key = array_key_first($month['securities_realization_book'][1]['component_accounts']);
        $month['securities_realization_book'][1]['component_accounts'][$key] = 'fvnew000';
    }
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['reason'])->toBe('RTC_DERE_TVM_BOOK_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([['coverage', 'PARENT_BOOK_COVERAGE_INCOMPLETE'], ['mapping', 'COMPONENT_PGCC_CODE_OR_NATURE_CONFLICT']]);

it('requires each title loss authorization even when another title has positive proceeds', function (): void {
    [, , $known, , $ledger] = tvmMultipleFixture();
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.07-28-draft6.json'), true, 512, JSON_THROW_ON_ERROR);
    $ledger['entries'][5]['securities_realization']['deduction_evidence'] = [];
    $ledger = sealTvmCurrentLedger($ledger);
    $result = tvmCurrentBase($ledger, $catalog['financial_operation']['base'], $known);
    expect($result['reason'])->toBe('RTC_TVM_CURRENT_LOSS_AUTHORIZATION_UNPROVEN');
});
