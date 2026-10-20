<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicIntermediateFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function publicOtherMovementFixture(string $name): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-other-movements-2026.10.08-28-draft25/'.$name.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
}

function publicOtherMovementSave(string $name, array $data): void
{
    if (getenv('TVM_OTHER_MOVEMENTS_CAPTURE') !== '1') {
        return;
    }
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-other-movements-2026.10.08-28-draft25/'.$name.'.json';
    if (is_file($path)) {
        expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));

        return;
    }
    $stream = fopen($path, 'x');
    expect($stream)->not->toBeFalse();
    fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    fclose($stream);
}

it('captures amortization coupon and additional acquisition with actual prior fiscal allocations', function (string $case): void {
    $fixture = publicOtherMovementFixture('prior-'.$case);
    [$base, , , , $scenario] = tvmPublicIntermediatePriorFixture($fixture, '2026.10.08-28-draft24');
    $binding = array_values(array_filter($base['entries'], static fn (array $entry): bool => $entry['id'] === 'yield1'))[0]['d2101_binding'];
    expect($binding['xml_title']['vJurosRec'])->toBe($case === 'coupon' ? '30.00' : '0.00')
        ->and($binding['calculation']['closing_acquisition_cost'])->toBe(match ($case) {
            'amortization' => '4000.00000000', 'coupon' => '5000.00000000', 'purchase' => '11000.00000000',
        });
    publicOtherMovementSave('prior-'.$case.'-financial', $scenario);
})->with(['amortization', 'coupon', 'purchase']);

it('transports the fiscal closing after prior movements to a subsequent partial or complete realization', function (string $case): void {
    $fixture = publicOtherMovementFixture($case);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft25');
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
    publicOtherMovementSave($case.'-financial', $scenario);
    publicOtherMovementSave($case.'-month', $month);
})->with(['amortization-partial', 'amortization-complete', 'coupon-partial', 'coupon-complete', 'purchase-partial', 'purchase-complete']);

it('refuses missing or inconsistent fiscal allocation of prior amortization and coupons', function (string $mutation, string $reason): void {
    $fixture = publicOtherMovementFixture('prior-amortization');
    [, , $known, , , $input] = tvmPublicIntermediatePriorFixture($fixture, '2026.10.08-28-draft24');
    if ($mutation === 'missing') {
        $input['recognition_inventory']['other_releases'] = [];
    } else {
        $input['recognition_inventory']['other_releases'][0]['fiscal_components']['assessed_produced_return'] = '10.00000000';
    }
    $input['inventory_evidence']['value']['inventory'] = $input['recognition_inventory'];
    $input['inventory_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['inventory_evidence'], ['source_hash' => true]));
    $input['complete_evidence']['value']['recognition_inventory'] = $input['recognition_inventory'];
    $input['complete_evidence']['value']['inventory_evidence_hash'] = $input['inventory_evidence']['source_hash'];
    $input['complete_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($input['complete_evidence'], ['source_hash' => true]));
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft24.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $catalog['financial_operation']['base']['securities_realization']['analysis'], $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason);
})->with([['missing', 'OTHER_RECOGNITION_RELEASE_MISSING'], ['fiscal', 'HISTORICAL_FISCAL_RELEASE_CONFLICT']]);

it('rejects an opening that ignores the weighted acquisition cost of the prior additional purchase', function (): void {
    $fixture = publicOtherMovementFixture('purchase-partial');
    $scope = ['supplier_id' => $fixture['position']['supplier_id'], 'period' => '2026-09', 'title' => $fixture['title'], 'opening' => $fixture['position']['opening']];
    $scope['opening']['unit_cost'] = '10.00000000';
    $scope['opening']['acquisition_cost'] = '10000.00000000';
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft25.json'), true, 512, JSON_THROW_ON_ERROR);
    $policy = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $fixture['recognition_history'], new DateTimeImmutable($policy['published_at']), $policy);
    expect($result['reason'])->toBe('RTC_TVM_HISTORY_CURRENT_OPENING_POSITION_CONFLICT');
});
