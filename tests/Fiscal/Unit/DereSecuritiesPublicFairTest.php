<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicFairFixtures.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('reconciles public produced yield in D2101 and realized fair-value gains and losses separately in D1101', function (string $case): void {
    [$operation, $month, $known, $rules, $ledger, $manifest, $fixture] = tvmPublicFairFixture($case);
    expect($operation['base']['net_base'])->toBe($fixture['expected']['base']);
    $parent = $operation['base']['realization_bindings']['tvm1'];
    expect($parent['revenue'])->toBe($fixture['expected']['fair_revenue'])->and($parent['deductions'])->toBe($fixture['expected']['fair_deductions']);
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['status'])->toBe('PROJECTED', $result['reason'])->and($result['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])
        ->and($result['securities_realization_binding']['scope'])->toBe('PUBLIC_D2101_CURVE_AND_D1101_REALIZATION_BOOK_JOURNALS_SEGREGATED')
        ->and($result['production_ready'])->toBeFalse();
    if (getenv('TVM_PUBLIC_FAIR_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-public-fair-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-public-fair-2026.10.07-28-draft13/'.$case.'-'.$suffix.'.json';
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
})->with(['gain', 'loss', 'full-gain', 'opposing-fair', 'exclusive-fund']);

it('keeps reciprocal public roles valid in either input order', function (): void {
    [, , $known, , $ledger, , $fixture] = tvmPublicFairFixture('gain');
    $ledger['entries'] = array_reverse($ledger['entries']);
    $ledger = sealTvmCurrentLedger($ledger);
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.07-28-draft13.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = tvmCurrentBase($ledger, $catalog['financial_operation']['base'], $known);
    expect($result['status'])->toBe('RESOLVED')->and($result['net_base'])->toBe($fixture['expected']['base']);
});

it('refuses unpaired public entries conflicting analysis and unqualified fair-value loss', function (string $mutation, string $reason): void {
    [, , $known, , $ledger] = tvmPublicFairFixture('loss');
    switch ($mutation) {
        case 'parent-missing': array_splice($ledger['entries'], 4, 1);
            break;
        case 'yield-missing': array_pop($ledger['entries']);
            break;
        case 'wrong-link': $ledger['entries'][4]['securities_realization']['public_yield_entry_id'] = 'unknown';
            break;
        case 'analysis': $ledger['entries'][5]['public_offer_debt']['sale_recognition']['analysis_input']['complete_evidence']['qualified'] = false;
            break;
        case 'loss-authorization': $ledger['entries'][4]['securities_realization']['deduction_evidence'] = [];
            break;
    }
    $ledger = sealTvmCurrentLedger($ledger);
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.07-28-draft13.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = tvmCurrentBase($ledger, $catalog['financial_operation']['base'], $known);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($reason);
})->with([
    ['parent-missing', 'RTC_FINANCIAL_ART195_FAIR_VALUE_COMPANION_SCOPE_UNPROVEN'],
    ['yield-missing', 'RTC_TVM_CURRENT_PUBLIC_CURVE_COMPANION_SCOPE_UNPROVEN'],
    ['wrong-link', 'RTC_TVM_CURRENT_PUBLIC_CURVE_COMPANION_SCOPE_UNPROVEN'],
    ['analysis', 'RTC_FINANCIAL_ART195_FAIR_VALUE_COMPANION_SCOPE_UNPROVEN'],
    ['loss-authorization', 'RTC_TVM_CURRENT_LOSS_AUTHORIZATION_UNPROVEN'],
]);

it('does not project a public fair-value closing without its qualified accounting bridge', function (string $mutation, string $reason): void {
    [$operation, $month, $known, $rules] = tvmPublicFairFixture('gain');
    switch ($mutation) {
        case 'origin': unset($operation['base']['realization_bindings']['tvm1']['public_yield_binding']);
            break;
        case 'component-account': $id = array_key_first($month['securities_realization_book'][0]['component_accounts']);
            $month['securities_realization_book'][0]['component_accounts'][$id] = 'curve000';
            break;
        case 'journals': array_pop($month['securities_realization_book'][0]['journals']);
            break;
        case 'proof': unset($month['securities_realization_book'][0]['independent_evidence']);
            break;
        case 'subledger': unset($month['securities_realization_book'][0]['fair_value_bridge']);
            break;
    }
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_TVM_BOOK_'.$reason);
})->with([
    ['origin', 'PUBLIC_CURVE_ORIGIN_OR_ASSET_CONFLICT'],
    ['component-account', 'COMPONENT_PGCC_CODE_OR_NATURE_CONFLICT'],
    ['journals', 'BOOK_JOURNAL_COMPONENT_OR_BALANCE_CONFLICT'],
    ['proof', 'INDEPENDENT_BOOK_BRIDGE_UNPROVEN'],
    ['subledger', 'FAIR_VALUE_SUBLEDGER_AND_METHOD_UNPROVEN'],
]);
