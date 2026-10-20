<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicIntermediateFixtures.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function intermediateSaleSave(string $suffix, array $data): void
{
    if (getenv('TVM_INTERMEDIATE_SALE_CAPTURE') !== '1') {
        return;
    }
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-intermediate-sale-2026.10.08-28-draft23/'.$suffix.'.json';
    if (is_file($path)) {
        expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));

        return;
    }
    $stream = fopen($path, 'x');
    expect($stream)->not->toBeFalse();
    fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    fclose($stream);
}

it('captures an actual August disposal with replayed July fiscal history before transporting its closing', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-public-intermediate-sale-2026.10.08-28-draft23/prior-august-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [, , , , $scenario] = tvmPublicIntermediatePriorFixture($fixture, '2026.10.08-28-draft22');
    intermediateSaleSave('prior-august-financial', $scenario);
});

it('uses the actual August closing after disposal as September opening without applying retention twice', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $fixture = json_decode(file_get_contents($root.'scenarios/tvm-public-intermediate-sale-2026.10.08-28-draft23/'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicDeferralFixture($case, $fixture, '2026.10.08-28-draft23');
    $parent = $operation['base']['realization_bindings']['tvm1'];
    $history = $parent['analysis']['replayed_fiscal_history'];
    expect($history['public_realized_closing_replayed'])->toBeTrue()
        ->and($history['opening_fiscal_components'])->toBe($fixture['expected']['opening_fiscal_components'])
        ->and($parent['financial_entries'])->toBe([])->and($parent['deductions'])->toBe('0.00')
        ->and($operation['base']['net_base'])->toBe($fixture['expected']['base']);
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])->and($projected['production_ready'])->toBeFalse();
    $scenario = json_decode(file_get_contents($root.'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'SYNTHETIC-public-intermediate-sale-'.$case;
    $scenario['knowledge_at'] = $known->format(DATE_ATOM);
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $scenario['facts']['rtc.financial_ledger'] = [$ledger];
    intermediateSaleSave($case.'-financial', $scenario);
    intermediateSaleSave($case.'-month', $month);
})->with(['partial', 'complete']);
