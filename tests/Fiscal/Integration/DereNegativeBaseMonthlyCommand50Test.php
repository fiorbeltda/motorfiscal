<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Support\Facades\Artisan;

it('projects a snapshot-bound negative-base request through the monthly command and rejects altered bindings', function (string $case): void {
    $root = dirname(__DIR__, 3);
    $e = $root.'/storage/app/phase4b-negative-base50-closing-draft-20261009/';
    $request = json_decode(file_get_contents($e.'command-negative-base-request.json'), true, 512, JSON_THROW_ON_ERROR);
    $args = ['snapshot' => $e.'command-financial-snapshot.json', 'month' => $e.'command-month-input.json',
        '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.09-28-draft50.json'];
    $path = tempnam(sys_get_temp_dir(), 'bcn50-command-request-');
    expect($path)->not->toBeFalse();
    try {
        if ($case === 'snapshot-binding') {
            $request['snapshot_hash'] = str_repeat('0', 64);
        } elseif ($case === 'month-binding') {
            $request['monthly_input_hash'] = str_repeat('0', 64);
        } elseif ($case === 'baseline-binding') {
            $request['baseline_projection_hash'] = str_repeat('0', 64);
        } elseif ($case === 'unsupported-bundle') {
            $args['snapshot'] = $root.'/storage/app/phase4b-private-consolidation48-draft-20261009-28/first-gain-snapshot.json';
            $args['month'] = $root.'/resources/fiscal/scenarios/tvm-private-consolidated-2026.10.09-28-draft48/first-gain-month.json';
            $args['--bundle-manifest'] = 'bundles/consumption-phase4b-2026.10.09-28-draft48.json';
        }
        file_put_contents($path, json_encode($request, JSON_THROW_ON_ERROR));
        if ($case !== 'without-request') {
            $args['--negative-base-request'] = $path;
        }
        $exit = Artisan::call('fiscal:dere:project-monthly-closing', $args);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        if (in_array($case, ['snapshot-binding', 'month-binding'], true)) {
            expect($exit)->toBe(1)->and($result['status'])->toBe('ERROR')
                ->and($result['error'])->toContain('differs from the replayed snapshot or monthly input');
        } elseif ($case === 'baseline-binding') {
            expect($exit)->toBe(1)->and($result['reason'])->toBe('RTC_DERE_BCN_OPTIONS_MONTHLY_BASELINE_OR_REQUEST_SCOPE_UNPROVEN');
        } elseif ($case === 'unsupported-bundle') {
            expect($exit)->toBe(1)->and($result['error'])->toContain('does not qualify a negative-base closing request');
        } else {
            expect($exit)->toBe(0)->and($result['production_ready'])->toBeFalse()
                ->and($result['event_validations']['D1199']['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE');
            if ($case === 'valid') {
                expect($result['negative_base_request_hash'])->toBe(CanonicalJson::hash($request))
                    ->and($result['negative_base_request_input'])->toBe($request)
                    ->and($result['projection']['negative_base_options_projection']['gross_debits_recalculated'])->toBeFalse();
            } else {
                expect($result)->not->toHaveKey('negative_base_request_hash')
                    ->and($result['projection'])->not->toHaveKey('negative_base_options_projection');
            }
        }
    } finally {
        unlink($path);
    }
})->with(['valid', 'snapshot-binding', 'month-binding', 'baseline-binding', 'unsupported-bundle', 'without-request']);
