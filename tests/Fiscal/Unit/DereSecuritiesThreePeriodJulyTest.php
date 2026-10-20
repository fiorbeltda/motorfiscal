<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmGrossRoundingFixtures.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('captures the July first-acquisition source for a three-period rounding chain', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $path = 'scenarios/tvm-three-period-rounding-2026.10.08-28-draft35/';
    $fixture = json_decode(file_get_contents($root.$path.'july-'.$case.'-current.json'), true, 512, JSON_THROW_ON_ERROR);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmGrossRoundingFixture('july-'.$case, $fixture, '2026.10.08-28-draft35');
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])
        ->and($projected['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])
        ->and($operation['base']['net_base'])->toBe($fixture['expected']['base'])
        ->and($projected['production_ready'])->toBeFalse();
    $scenario = json_decode(file_get_contents($root.'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'SYNTHETIC-three-period-rounding-july-'.$case;
    $scenario['knowledge_at'] = $known->format(DATE_ATOM);
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $scenario['competence_date'] = '2026-07-31';
    $scenario['facts']['rtc.operation_date'] = '2026-07-31';
    $scenario['facts']['rtc.financial_ledger'] = [$ledger];
    if (getenv('TVM_THREE_PERIOD_JULY_CAPTURE') === '1') {
        foreach (['financial' => $scenario, 'month' => $month] as $kind => $data) {
            $file = $root.$path.'july-'.$case.'-'.$kind.'.json';
            if (is_file($file)) {
                expect(CanonicalJson::encode(json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));
            } else {
                $stream = fopen($file, 'x');
                expect($stream)->not->toBeFalse();
                fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                fclose($stream);
            }
        }
    }
})->with(['negative', 'positive', 'zero', 'exact']);
