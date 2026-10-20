<?php

declare(strict_types=1);

require_once __DIR__.'/TvmSharedAssetHistoricalFixtures.php';
require_once __DIR__.'/TvmPublicFairFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;

function consolidated46HistoricalFixture(string $positiveMode, string $negativeMode, bool $shared, string $version = '2026.10.09-28-draft46', bool $sharedAsset = true): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $single = [];
    $sourceFixtures = [];
    foreach (['positive-'.$positiveMode, 'negative-'.$negativeMode] as $case) {
        $fixture = json_decode(file_get_contents($root.'scenarios/tvm-realization-consolidated-'.$version.'/'.($sharedAsset ? 'shared-assets' : 'separate-assets').'/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
        $sourceFixtures[] = $fixture;
        $single[] = tvmPublicDeferralFixture($case, $fixture, $version);
    }
    [$operation, $month, $known, $rules, $ledger, $manifest] = $single[0];
    $second = historicalMultiRename($single[1][4], $shared);
    $second['entries'][4]['document_id'] .= '.SECOND';
    $parent = &$second['entries'][4]['securities_realization'];
    $input = &$parent['analysis_input'];
    foreach ($input['realizations'] as &$sale) {
        $sale['component_evidence']['value']['history_evidence_hash'] = $sale['history_evidence']['source_hash'];
        $sale['component_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['component_evidence'], ['source_hash' => true]));
    }
    unset($sale);
    $input['complete_evidence']['value']['realizations'] = $input['realizations'];
    $input['complete_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['complete_evidence'], ['source_hash' => true]));
    $second['entries'][5]['public_offer_debt']['sale_recognition']['analysis_input'] = $input;
    $proof = &$second['entries'][5]['public_offer_debt']['sale_recognition']['independent_evidence'];
    $proof['value']['analysis_input_hash'] = CanonicalJson::hash($input);
    $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
    unset($input, $parent, $proof);
    $ledger['entries'][] = $second['entries'][4];
    $ledger['entries'][] = $second['entries'][5];
    $ledger = sealTvmCurrentLedger($ledger);
    $catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $operation['base'] = tvmCurrentBase($ledger, $catalog['financial_operation']['base'], $known);
    expect($operation['base']['status'])->toBe('RESOLVED', $operation['base']['reason']);
    $other = historicalMultiRename($single[1][1], $shared);
    $plans = array_column($month['pgcc']['accounts'], null, 'cCta');
    foreach ($other['pgcc']['accounts'] as $account) {
        $account['cCtaInterna'] = $account['indCta'] === 'A' ? substr($account['cCta'], 0, -3) : $account['cCtaInterna'];
        $plans[$account['cCta']] ??= $account;
    }
    $month['pgcc']['accounts'] = array_values($plans);
    $month['pgcc']['source_hash'] = CanonicalJson::hash(array_diff_key($month['pgcc'], ['source_hash' => true]));
    $accounts = array_column($month['book']['accounts'], null, 'cCta');
    foreach (array_slice($other['book']['accounts'], 4) as $account) {
        $code = $account['cCta'];
        if (! isset($accounts[$code])) {
            $accounts[$code] = $account;

            continue;
        }
        expect(in_array($code, ['cash000', 'curve000', 'debt000'], true))->toBeTrue();
        foreach (['vMovDebt', 'vMovCred', 'vAjusteDebt', 'vAjusteCred'] as $field) {
            $accounts[$code][$field] = (string) BigDecimal::of($accounts[$code][$field])->plus($account[$field])->toScale(2);
        }
        if ($code === 'debt000') {
            $accounts[$code]['vSaldoInic'] = (string) BigDecimal::of($accounts[$code]['vSaldoInic'])->plus($account['vSaldoInic'])->toScale(2);
            $previous = ['period' => '2026-07', 'amount' => $accounts[$code]['vSaldoInic'], 'nature' => 'D',
                'title_components' => array_map(static fn (array $fixture): array => ['title' => $fixture['title'], 'opening' => $fixture['position']['opening'],
                    'source_snapshot_hash' => CanonicalJson::hash($fixture['recognition_history']['fiscal_sources'][0]['snapshot'])], $sourceFixtures)];
            $previous['source_hash'] = CanonicalJson::hash($previous);
            $accounts[$code]['previous_balance'] = $previous;
        }
        $normal = in_array($code, ['cash000', 'debt000'], true) ? 'D' : 'C';
        $net = BigDecimal::of($accounts[$code]['vSaldoInic']);
        if ($accounts[$code]['natSaldoInic'] !== $normal) {
            $net = $net->negated();
        }
        $net = $normal === 'D' ? $net->plus($accounts[$code]['vMovDebt'])->minus($accounts[$code]['vMovCred'])
            : $net->plus($accounts[$code]['vMovCred'])->minus($accounts[$code]['vMovDebt']);
        $accounts[$code]['natSaldoFinal'] = $net->isNegative() ? ($normal === 'D' ? 'C' : 'D') : $normal;
        $accounts[$code]['vSaldoFinal'] = (string) $net->abs()->toScale(2);
        $accounts[$code]['vApur'] = '0.00';
        $accounts[$code]['financial_entry_ids'] = array_values(array_unique([...($accounts[$code]['financial_entry_ids'] ?? []), ...($account['financial_entry_ids'] ?? [])]));
    }
    $month['book']['accounts'] = array_values($accounts);
    $month['securities_realization_book'][] = $other['securities_realization_book'][0];
    $month['book']['pgcc_source_hash'] = $month['prerequisites']['pgcc_source_hash'] = $month['pgcc']['source_hash'];
    $month['book']['pgcc_accounts'] = array_values(array_map(static fn (array $account): array => array_intersect_key($account, array_flip(['cCta', 'indCta', 'codNat', 'valid_from', 'valid_to'])), array_filter($month['pgcc']['accounts'], static fn (array $account): bool => $account['indCta'] === 'A')));
    $month['book']['book_source_hash'] = CanonicalJson::hash(['accounts' => $month['book']['accounts'], 'journals' => array_column($month['securities_realization_book'], 'journals')]);
    $pgcc = (new DerePgccQualifier)->resolve($month['pgcc'], $rules['closing']['pgcc_catalogs']['reference'], $rules['closing']['pgcc_catalogs']['tax_codes'], $known, false, $rules['closing']['pgcc_qualification_policy']);
    expect($pgcc['status'])->toBe('RESOLVED', $pgcc['reason']);
    foreach ($month['securities_realization_book'] as &$row) {
        $row['independent_evidence'] = tvmBookProof(['supplier_id' => $ledger['supplier_id'], 'period' => '2026-08', 'parent_id' => $row['parent_id'],
            'qualification_hash' => CanonicalJson::hash($operation['base']['realization_bindings'][$row['parent_id']]), 'asset_account' => $row['asset_account'], 'cash_account' => $row['cash_account'], 'component_accounts' => $row['component_accounts'], 'journals' => $row['journals'], 'daily_memory_hash' => CanonicalJson::hash($row['daily_memory']), 'book_source_hash' => $month['book']['book_source_hash'], 'pgcc_source_hash' => $pgcc['source_hash'], 'book_content_hash' => CanonicalJson::hash($month['book']), 'pgcc_content_hash' => CanonicalJson::hash($pgcc)], '2026-08');
    }
    unset($row);
    $month['debt_yield']['financial_entry_ids'] = ['yield1', 'yield2'];
    $month['debt_yield']['previous_title_balances']['yield2'] = $other['debt_yield']['previous_title_balances']['yield2'];
    $month['debt_yield']['evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'], 'detail' => array_diff_key($month['debt_yield'], ['evidence' => true]), 'operation_hash' => CanonicalJson::hash($operation), 'book_input_hash' => CanonicalJson::hash($month['book']), 'pgcc_source_hash' => $month['book']['pgcc_source_hash']], 'MULTIPLE.PUBLIC.MONTH', '2026-08');

    return [$operation, $month, $known, $rules, $ledger, $manifest];
}

function consolidated46RealizationFixture(string $case): array
{
    $version = '2026.10.09-28-draft46';
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $path): array => json_decode(file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
    if (in_array($case, ['private-buy-sell', 'private-opposing'], true)) {
        $name = $case === 'private-buy-sell' ? 'first-gain' : 'first-offsetting';
        $catalog = $read('catalogs/rtc-codes-'.$version.'.json');
        [$ledger] = tvmCurrentFirstFixture($name, null, $catalog['financial_operation']['base']);

        return tvmFairFixture($name, $ledger, $version);
    }
    if ($case === 'public-prior-fair') {
        return tvmPublicDeferralFixture('full-gain', $read('scenarios/tvm-public-deferral-2026.10.08-28-draft15/full-gain-current.json'), $version);
    }
    if ($case === 'public-fund') {
        return tvmPublicFairFixture('exclusive-fund', $read('scenarios/tvm-public-fair-2026.10.07-28-draft13/exclusive-fund.json'), $version);
    }
    if ($case === 'third-sale-rounding') {
        return tvmPublicDeferralFixture('positive-partial-complete', $read('scenarios/tvm-third-sale-integrated-2026.10.08-28-draft38/positive-partial-complete-current.json'), $version);
    }

    return match ($case) {
        'historical-separate-assets' => consolidated46HistoricalFixture('partial', 'partial', true, $version, false),
        'shared-asset-partial' => consolidated46HistoricalFixture('partial', 'partial', true),
        'shared-asset-complete' => consolidated46HistoricalFixture('complete', 'complete', true),
        'shared-asset-asymmetric' => consolidated46HistoricalFixture('partial', 'complete', false),
        default => throw new InvalidArgumentException('Unknown consolidated realization fixture'),
    };
}
