<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicDeferralFixtures.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('realizes prior public fair value once in D1101 while retaining only current produced yield in D2101', function (string $case): void {
    [$operation, $month, $known, $rules, $ledger, $manifest, $fixture] = tvmPublicDeferralFixture($case);
    expect($operation['base']['net_base'])->toBe($fixture['expected']['base']);
    $parent = $operation['base']['realization_bindings']['tvm1'];
    expect($parent['revenue'])->toBe($fixture['expected']['fair_revenue'])->and($parent['deductions'])->toBe($fixture['expected']['fair_deductions'])
        ->and($parent['analysis']['historical_fiscal_snapshot_replay_performed'])->toBeTrue()
        ->and($parent['analysis']['recognition_inventory']['closing'])->toBe($fixture['recognition_inventory']['closing']);
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])
        ->and($projected['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])->and($projected['production_ready'])->toBeFalse();
    if (getenv('TVM_PUBLIC_DEFERRAL_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-public-deferral-current-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-public-deferral-2026.10.08-28-draft15/'.$case.'-'.$suffix.'.json';
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
})->with(['gain', 'loss', 'fund-gain', 'fund-loss', 'full-gain', 'full-loss']);

it('requires current authorization even when a public loss has already been proven as deferred', function (): void {
    [, , $known, , $ledger] = tvmPublicDeferralFixture('loss');
    $ledger['entries'][4]['securities_realization']['deduction_evidence'] = [];
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft15.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = tvmCurrentBase(sealTvmCurrentLedger($ledger), $catalog['financial_operation']['base'], $known);
    expect($result['reason'])->toBe('RTC_TVM_CURRENT_LOSS_AUTHORIZATION_UNPROVEN')->and($result['production_ready'])->toBeFalse();
});

it('refuses current public realization without the supplied reclassification and subledger proof', function (string $mutation): void {
    [$operation, $month, $known, $rules] = tvmPublicDeferralFixture('loss');
    $bridge = &$month['securities_realization_book'][0];
    switch ($mutation) {
        case 'journals': array_pop($bridge['journals']);
            break;
        case 'subledger': unset($bridge['fair_value_bridge']);
            break;
        case 'proof': unset($bridge['independent_evidence']);
            break;
    }
    unset($bridge);
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['production_ready'])->toBeFalse();
})->with(['journals', 'subledger', 'proof']);
