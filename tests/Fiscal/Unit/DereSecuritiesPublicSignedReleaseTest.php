<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicIntermediateFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function publicSignedReleaseFixture(string $name): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-signed-release-2026.10.08-28-draft29/'.$name.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
}

function publicSignedReleaseSave(string $name, array $data): void
{
    if (getenv('TVM_SIGNED_RELEASE_CAPTURE') !== '1') {
        return;
    }
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-signed-release-2026.10.08-28-draft29/'.$name.'.json';
    if (is_file($path)) {
        expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));

        return;
    }
    $stream = fopen($path, 'x');
    expect($stream)->not->toBeFalse();
    fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    fclose($stream);
}

it('captures standalone amortization and coupon without sale using actual prior fiscal allocations', function (string $case): void {
    $fixture = publicSignedReleaseFixture('prior-'.$case);
    [$base, , , , $scenario] = tvmPublicIntermediatePriorFixture($fixture, '2026.10.08-28-draft28');
    $binding = array_values(array_filter($base['entries'], static fn (array $entry): bool => $entry['id'] === 'yield1'))[0]['d2101_binding'];
    expect($binding['xml_title']['vJurosRec'])->toBe(str_ends_with($case, 'coupon') ? '30.00' : '0.00')
        ->and($binding['calculation']['closing_acquisition_cost'])->toBe(str_ends_with($case, 'amortization') ? '8000.00000000' : '10000.00000000');
    expect($base['realization_bindings']['tvm1']['analysis']['realizations'])->toBe([])
        ->and($base['realization_bindings']['tvm1']['analysis']['public_other_release_without_sale_reconciled'])->toBeTrue();
    expect($binding['gross_nature'])->toBe(str_contains($case, 'negative') ? 'D' : 'C')
        ->and($binding['taxable_amount'])->toBe(str_contains($case, 'negative') ? '0.00' : '45.00')
        ->and($fixture['title']['holding_route'])->toBe(str_starts_with($case, 'fund') ? 'EXCLUSIVE_FUND_95' : 'DIRECT');
    publicSignedReleaseSave('prior-'.$case.'-financial', $scenario);
})->with(['direct-negative-amortization', 'direct-negative-coupon', 'fund-negative-amortization', 'fund-negative-coupon', 'fund-positive-amortization', 'fund-positive-coupon']);

it('transports the fiscal closing after prior movements to a subsequent partial or complete realization', function (string $case): void {
    $fixture = publicSignedReleaseFixture($case);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft29');
    $parent = $operation['base']['realization_bindings']['tvm1'];
    expect($parent['analysis']['replayed_fiscal_history']['public_realized_closing_replayed'])->toBeTrue()
        ->and($parent['analysis']['replayed_fiscal_history']['opening_fiscal_components'])->toBe($fixture['expected']['opening_fiscal_components'])
        ->and($operation['base']['net_base'])->toBe($fixture['expected']['base'])
        ->and($parent['financial_entries'])->toBe([])->and($parent['deductions'])->toBe('0.00');
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])
        ->and($projected['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])
        ->and($projected['production_ready'])->toBeFalse();
    $scenario = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'SYNTHETIC-public-other-movements-'.$case;
    $scenario['knowledge_at'] = $known->format(DATE_ATOM);
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $scenario['facts']['rtc.financial_ledger'] = [$ledger];
    publicSignedReleaseSave($case.'-financial', $scenario);
    publicSignedReleaseSave($case.'-month', $month);
})->with(['direct-negative-amortization-partial', 'direct-negative-amortization-complete', 'direct-negative-coupon-partial', 'direct-negative-coupon-complete', 'fund-negative-amortization-partial', 'fund-negative-amortization-complete', 'fund-negative-coupon-partial', 'fund-negative-coupon-complete', 'fund-positive-amortization-partial', 'fund-positive-amortization-complete', 'fund-positive-coupon-partial', 'fund-positive-coupon-complete']);

it('rejects unavailable historical fiscal components and positive public loss reclassification', function (string $mutation, string $reason): void {
    $fixture = publicSignedReleaseFixture('direct-negative-amortization-partial');
    [, , $known, , $ledger] = tvmPublicDeferralFixture('direct-negative-amortization-partial', $fixture, '2026.10.08-28-draft29');
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $sale = &$input['realizations'][0];
    $sale['history']['previously_assessed_produced_return'] = $mutation === 'overconsume' ? '81.00000000' : '-160.00000000';
    $sale['history']['previously_unoffset_public_title_loss'] = $mutation === 'overconsume' ? '-141.00000000' : '100.00000000';
    $sale['history_evidence']['value']['history'] = $sale['history'];
    $sale['history_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['history_evidence'], ['source_hash' => true]));
    $sale['component_evidence']['value']['history_evidence_hash'] = $sale['history_evidence']['source_hash'];
    $sale['component_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($sale['component_evidence'], ['source_hash' => true]));
    unset($sale);
    $input['complete_evidence']['value']['realizations'] = $input['realizations'];
    $input['complete_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['complete_evidence'], ['source_hash' => true]));
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft29.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $catalog['financial_operation']['base']['securities_realization']['analysis'], $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason);
})->with([
    ['overconsume', 'HISTORICAL_FISCAL_COMPONENT_REUSED_OR_UNAVAILABLE'],
    ['reclassify', 'PRIOR_ASSESSED_AND_EXCLUDED_RETURN_CONFLICT'],
]);

it('rejects an exclusive fund below the public offer eligibility threshold', function (): void {
    $fixture = publicSignedReleaseFixture('fund-negative-amortization-partial');
    [, , $known, , $ledger] = tvmPublicDeferralFixture('fund-negative-amortization-partial', $fixture, '2026.10.08-28-draft29');
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    $input['title']['public_offer_portfolio_percent'] = '94.00';
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft29.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $catalog['financial_operation']['base']['securities_realization']['analysis'], $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_PUBLIC_FUND_ELIGIBILITY_UNPROVEN');
});
