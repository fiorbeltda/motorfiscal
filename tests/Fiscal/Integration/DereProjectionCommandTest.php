<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Illuminate\Support\Facades\Artisan;

it('replays the captured financial calculation and rejects a changed body or a different selected bundle', function (string $case, string $expected): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $snapshot = (new RtcScenarioRunService(new FiscalEngineFactory))->execute($root,
        'bundles/consumption-phase4b-2026.10.02-14.json',
        $root.'/scenarios/consumption-phase4b-2026.10.02-14/financial-borrower-credit-loan-2026.json')->snapshot();
    if ($case === 'tampered') {
        $snapshot['result']['trace'][0]['competence_date'] = '2026-09-29';
    }
    $path = tempnam(sys_get_temp_dir(), 'dere-snapshot-');
    expect($path)->not->toBeFalse();
    try {
        file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR));
        $exit = Artisan::call('fiscal:dere:project-xml', ['snapshot' => $path,
            'allocation' => $root.'/scenarios/dere-local-2026.10.02-14/qualified-allocation.json',
            '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.02-'.($case === 'different-bundle' ? '13' : '14').'.json']);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($result['status'])->toBe($expected)->and($exit)->toBe($case === 'valid' ? 0 : 1);
        if ($case === 'valid') {
            expect($result['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')
                ->and($result['production_ready'])->toBeFalse()->and($result['projection']['transaction_count'])->toBe(2);
        }
    } finally {
        unlink($path);
    }
})->with([['valid', 'ACTION_REQUIRED'], ['tampered', 'ERROR'], ['different-bundle', 'ERROR']]);
