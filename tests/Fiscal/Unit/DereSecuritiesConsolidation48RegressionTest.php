<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPrivateConsolidation48Fixtures.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('retains all 87 accepted consolidated cases under knowledge48', function (string $case): void {
    $root = dirname(__DIR__, 3);
    $folder = 'resources/fiscal/scenarios/tvm-realization-consolidated-2026.10.09-28-draft47/';
    $metadata = ['case' => $case, 'financial_input' => $folder.$case.'-financial.json', 'monthly_input' => $folder.$case.'-month.json'];
    [$operation, $month, $known, $rules, $ledger, $manifest] = privateConsolidation48Fixture($metadata, true);
    $previousCatalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.09-28-draft47.json'), true, 512, JSON_THROW_ON_ERROR);
    $previousScenario = json_decode(file_get_contents($root.'/'.$metadata['financial_input']), true, 512, JSON_THROW_ON_ERROR);
    $previous = tvmCurrentBase($previousScenario['facts']['rtc.financial_ledger'][0], $previousCatalog['financial_operation']['base'], new DateTimeImmutable($previousScenario['knowledge_at']));
    expect($previous['status'])->toBe('RESOLVED', $previous['reason']);
    expect($operation['base']['net_base'])->toBe($previous['net_base']);
    foreach ($previous['realization_bindings'] as $id => $binding) {
        expect($operation['base']['realization_bindings'][$id]['analysis'])->toBe($binding['analysis']);
    }
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['status'])->toBe('PROJECTED', $result['reason'])->and($result['production_ready'])->toBeFalse();
    if (getenv('TVM_REGRESSION48_CAPTURE') === '1') {
        $previousScenario['scenario_id'] = 'SYNTHETIC-consolidated48-'.$case;
        $previousScenario['knowledge_at'] = $known->format(DATE_ATOM);
        $previousScenario['rule_bundle_id'] = $manifest['bundle_id'];
        $previousScenario['catalog_versions'] = $manifest['catalog_versions'];
        $previousScenario['facts']['rtc.financial_ledger'] = [$ledger];
        $folder = $root.'/resources/fiscal/scenarios/tvm-realization-consolidated-2026.10.09-28-draft48';
        if (! is_dir($folder)) {
            mkdir($folder);
        }
        foreach (['financial' => $previousScenario, 'month' => $month] as $kind => $value) {
            $path = $folder.'/'.$case.'-'.$kind.'.json';
            if (is_file($path)) {
                expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($value));
            } else {
                $stream = fopen($path, 'x');
                expect($stream)->not->toBeFalse();
                fwrite($stream, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                fclose($stream);
            }
        }
    }
})->with(function (): array {
    $root = dirname(__DIR__, 3);
    $corpus = json_decode(file_get_contents($root.'/storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28/expanded-realization-corpus.json'), true, 512, JSON_THROW_ON_ERROR);

    $names = array_column($corpus['cases'], 'case');

    return array_combine($names, array_map(static fn (string $name): array => [$name], $names));
});
