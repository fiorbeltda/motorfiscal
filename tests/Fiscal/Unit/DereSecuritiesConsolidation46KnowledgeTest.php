<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmRealizationConsolidation46Fixtures.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use Brick\Math\BigDecimal;

it('qualifies and projects original realization requirements under one consolidated knowledge version', function (string $case): void {
    [$operation, $month, $known, $rules, $ledger, $manifest] = consolidated46RealizationFixture($case);
    $root = dirname(__DIR__, 3);
    $matrix = json_decode(file_get_contents($root.'/storage/app/phase4b-realization-consolidation-preparation-20261009-28/requirements-evidence-matrix.json'), true, 512, JSON_THROW_ON_ERROR);
    $expected = $matrix['cases'][$case]['observations'];
    expect($manifest['version'])->toBe('2026.10.09-28-draft46')->and($operation['base']['net_base'])->toBe($expected['base']);
    $sales = [];
    foreach ($operation['base']['realization_bindings'] as $parent) {
        foreach ($parent['analysis']['realizations'] as $sale) {
            $sales[] = $sale;
        }
    }
    expect($sales)->toHaveCount(count($expected['sales']));
    foreach ($sales as $index => $sale) {
        foreach ($expected['sales'][$index] as $key => $value) {
            if ($key === 'components') {
                foreach ($value as $part => $amount) {
                    expect(BigDecimal::of($sale['components'][$part])->isEqualTo($amount))->toBeTrue();
                }
            } else {
                expect(BigDecimal::of($sale[$key])->isEqualTo($value))->toBeTrue();
            }
        }
    }
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])->and($projected['production_ready'])->toBeFalse();
    if (getenv('TVM_REALIZATION_CONSOLIDATED_CAPTURE') === '1') {
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-consolidated-realization-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['competence_date'] = $ledger['period_end'];
        $scenario['facts']['rtc.operation_date'] = $ledger['period_end'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $kind => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-realization-consolidated-2026.10.09-28-draft46/'.$case.'-'.$kind.'.json';
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
})->with(['private-buy-sell', 'private-opposing', 'public-prior-fair', 'public-fund', 'third-sale-rounding', 'historical-separate-assets', 'shared-asset-partial', 'shared-asset-complete', 'shared-asset-asymmetric']);
