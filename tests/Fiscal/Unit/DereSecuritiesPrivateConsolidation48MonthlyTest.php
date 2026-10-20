<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPrivateConsolidation48Fixtures.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('projects preserved private books under the consolidated knowledge', function (array $metadata): void {
    [$operation, $month, $known, $rules, $ledger, $manifest] = privateConsolidation48Fixture($metadata);
    $root = dirname(__DIR__, 3);
    $original = json_decode(file_get_contents($root.'/'.$metadata['monthly_input']), true, 512, JSON_THROW_ON_ERROR);
    expect($month['book']['accounts'])->toBe($original['book']['accounts']);
    foreach ($original['securities_realization_book'] as $i => $row) {
        foreach (['journals', 'daily_memory', 'component_accounts', 'asset_account', 'cash_account'] as $key) {
            expect($month['securities_realization_book'][$i][$key])->toBe($row[$key]);
        }
    }
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['status'])->toBe('PROJECTED', $result['reason']);
    expect($result['production_ready'])->toBeFalse();
    if (getenv('TVM_PRIVATE_CONSOLIDATION48_CAPTURE') === '1') {
        $scenario = json_decode(file_get_contents($root.'/'.$metadata['financial_input']), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-private-consolidated48-'.$metadata['case'];
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        $folder = $root.'/resources/fiscal/scenarios/tvm-private-consolidated-2026.10.09-28-draft48';
        if (! is_dir($folder)) {
            mkdir($folder);
        }
        foreach (['financial' => $scenario, 'month' => $month] as $kind => $value) {
            $path = $folder.'/'.$metadata['case'].'-'.$kind.'.json';
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
    $index = json_decode(file_get_contents($root.'/storage/app/phase4b-private-consolidation48-draft-20261009-28/private-cases.json'), true, 512, JSON_THROW_ON_ERROR);

    return array_map(static fn (array $row): array => [$row], $index['cases']);
});
