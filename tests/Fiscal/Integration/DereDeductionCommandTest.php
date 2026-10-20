<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Illuminate\Support\Facades\Artisan;

it('replays the captured monthly deductions calculation and rejects a changed body or a different selected bundle', function (string $case, string $expected): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $snapshot = (new RtcScenarioRunService(new FiscalEngineFactory))->execute($root,
        'bundles/consumption-phase4b-2026.10.02-16.json',
        $root.'/scenarios/consumption-phase4b-2026.10.02-16/financial-borrower-credit-loan-2026.json')->snapshot();
    if ($case === 'tampered') {
        $snapshot['result']['trace'][0]['competence_date'] = '2026-09-29';
    }
    $path = tempnam(sys_get_temp_dir(), 'dere-deductions-snapshot-');
    expect($path)->not->toBeFalse();
    try {
        file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR));
        $exit = Artisan::call('fiscal:dere:project-deductions', ['snapshot' => $path,
            'deductions' => $root.'/scenarios/dere-local-2026.10.02-16/qualified-deductions-initial.json',
            '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.02-'.($case === 'different-bundle' ? '15' : '16').'.json']);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($result['status'])->toBe($expected)->and($exit)->toBe($case === 'valid' ? 0 : 1);
        if ($case === 'valid') {
            expect($result['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')
                ->and($result['production_ready'])->toBeFalse()->and($result['projection']['deductions']['linked_financial_deductions'])->toBe(1);
        }
    } finally {
        unlink($path);
    }
})->with([['valid', 'ACTION_REQUIRED'], ['tampered', 'ERROR'], ['different-bundle', 'ERROR']]);

it('preserves an existing monthly candidate file instead of overwriting it', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $snapshot = (new RtcScenarioRunService(new FiscalEngineFactory))->execute($root,
        'bundles/consumption-phase4b-2026.10.02-16.json',
        $root.'/scenarios/consumption-phase4b-2026.10.02-16/financial-borrower-credit-loan-2026.json')->snapshot();
    $path = tempnam(sys_get_temp_dir(), 'dere-deductions-snapshot-');
    $candidate = tempnam(sys_get_temp_dir(), 'dere-deductions-existing-');
    try {
        file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR));
        file_put_contents($candidate, 'preserved candidate');
        $exit = Artisan::call('fiscal:dere:project-deductions', ['snapshot' => $path,
            'deductions' => $root.'/scenarios/dere-local-2026.10.02-16/qualified-deductions-initial.json',
            '--candidate-output' => $candidate]);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($exit)->toBe(1)->and($result['error'])->toBe('Refusing to overwrite candidate XML.')
            ->and(file_get_contents($candidate))->toBe('preserved candidate');
    } finally {
        unlink($path);
        unlink($candidate);
    }
});
