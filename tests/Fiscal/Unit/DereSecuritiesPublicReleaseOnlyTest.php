<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicIntermediateFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function publicReleaseOnlyFixture(string $name): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-release-only-2026.10.08-28-draft27/'.$name.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
}

function publicReleaseOnlySave(string $name, array $data): void
{
    if (getenv('TVM_RELEASE_ONLY_CAPTURE') !== '1') {
        return;
    }
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-release-only-2026.10.08-28-draft27/'.$name.'.json';
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
    $fixture = publicReleaseOnlyFixture('prior-'.$case);
    [$base, , , , $scenario] = tvmPublicIntermediatePriorFixture($fixture, '2026.10.08-28-draft26');
    $binding = array_values(array_filter($base['entries'], static fn (array $entry): bool => $entry['id'] === 'yield1'))[0]['d2101_binding'];
    expect($binding['xml_title']['vJurosRec'])->toBe($case === 'coupon' ? '30.00' : '0.00')
        ->and($binding['calculation']['closing_acquisition_cost'])->toBe(match ($case) {
            'amortization' => '8000.00000000', 'coupon' => '10000.00000000',
        });
    expect($base['realization_bindings']['tvm1']['analysis']['realizations'])->toBe([])
        ->and($base['realization_bindings']['tvm1']['analysis']['public_other_release_without_sale_reconciled'])->toBeTrue();
    publicReleaseOnlySave('prior-'.$case.'-financial', $scenario);
})->with(['amortization', 'coupon']);

it('transports the fiscal closing after prior movements to a subsequent partial or complete realization', function (string $case): void {
    $fixture = publicReleaseOnlyFixture($case);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft27');
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
    publicReleaseOnlySave($case.'-financial', $scenario);
    publicReleaseOnlySave($case.'-month', $month);
})->with(['amortization-partial', 'amortization-complete', 'coupon-partial', 'coupon-complete']);

it('rejects unavailable policy missing duplicate or inconsistent standalone release coverage', function (string $mutation, string $reason): void {
    $fixture = publicReleaseOnlyFixture('prior-amortization');
    [, , $known, , , $input] = tvmPublicIntermediatePriorFixture($fixture, '2026.10.08-28-draft26');
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft26.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis'];
    if ($mutation === 'policy') {
        unset($rule['public_other_release_without_sale_policy']);
    } elseif ($mutation === 'missing') {
        $input['recognition_inventory']['other_releases'] = [];
    } elseif ($mutation === 'duplicate') {
        $input['recognition_inventory']['other_releases'][] = $input['recognition_inventory']['other_releases'][0];
    } elseif ($mutation === 'fiscal') {
        $input['recognition_inventory']['other_releases'][0]['fiscal_components']['assessed_produced_return'] = '10.00000000';
    } elseif ($mutation === 'fair') {
        $input['recognition_inventory']['other_releases'][0]['components']['deferred_fair_value_realized'] = '1';
    } else {
        $input['recognition_inventory']['other_releases'][0]['components']['prior_produced_return'] = '21';
    }
    $input['inventory_evidence']['value']['inventory'] = $input['recognition_inventory'];
    $input['inventory_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['inventory_evidence'], ['source_hash' => true]));
    $input['complete_evidence']['value']['recognition_inventory'] = $input['recognition_inventory'];
    $input['complete_evidence']['value']['inventory_evidence_hash'] = $input['inventory_evidence']['source_hash'];
    $input['complete_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['complete_evidence'], ['source_hash' => true]));
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $rule, $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason);
})->with([
    ['policy', 'SALE_COVERAGE_INCOMPLETE'],
    ['missing', 'PUBLIC_OTHER_RELEASE_WITHOUT_SALE_SCOPE_UNPROVEN'],
    ['duplicate', 'PUBLIC_OTHER_RELEASE_WITHOUT_SALE_SCOPE_UNPROVEN'],
    ['fiscal', 'HISTORICAL_FISCAL_RELEASE_CONFLICT'],
    ['fair', 'PUBLIC_OTHER_RELEASE_WITHOUT_SALE_SCOPE_UNPROVEN'],
    ['amount', 'OTHER_RECOGNITION_RELEASE_CONFLICT'],
]);

it('does not qualify an additional purchase or an empty movement list as a standalone release', function (string $case): void {
    $fixture = publicReleaseOnlyFixture('prior-amortization');
    [, , $known, , , $input] = tvmPublicIntermediatePriorFixture($fixture, '2026.10.08-28-draft26');
    $control = publicReleaseOnlyFixture('control-'.$case);
    $input['position'] = $control['position'];
    $input['recognition_inventory'] = $control['recognition_inventory'];
    $input['classification_evidence']['value']['position_input_hash'] = CanonicalJson::hash($input['position']);
    $input['classification_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['classification_evidence'], ['source_hash' => true]));
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft26.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($input['position'], $rule['daily_memory_calculation']['series'], $known, $rule['daily_memory_calculation']);
    expect($memory['status'])->toBe('RESOLVED', $memory['reason']);
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $rule, $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_PUBLIC_OTHER_RELEASE_WITHOUT_SALE_SCOPE_UNPROVEN');
})->with(['buy', 'empty']);
