<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('projects explicit empty-title settlement and writes no monthly files for invalid return', function (string $profile, string $case): void {
    $base = storage_path('app/phase4b-dere9121-58-no-movement-integration-20261013/');
    $request = json_decode((string) file_get_contents($base.$profile.'-request.json'), true, 512, JSON_THROW_ON_ERROR);
    $requestPath = tempnam(sys_get_temp_dir(), 'd9121-58-request-');
    if ($requestPath === false) {
        throw new RuntimeException('Unable to create test request.');
    }
    $candidate = $requestPath.'.xml';
    $report = $requestPath.'.report.json';
    $args = ['snapshot' => $base.'financial-snapshot.json', 'month' => $base.$profile.'-month.json',
        '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.13-28-draft58.json',
        '--report' => $report, '--candidate-output' => $candidate];
    try {
        if ($case === 'future') {
            $request['current_public_offer_return']['xml'] = str_replace('2026-10-13T10:00:01.0000000+00:00', '2099-10-13T10:00:01.0000000+00:00', $request['current_public_offer_return']['xml']);
        } elseif ($case === 'nonzero') {
            $request['current_public_offer_return']['xml'] = str_replace('<vApurTot>0.00</vApurTot>', '<vApurTot>1.00</vApurTot>', $request['current_public_offer_return']['xml']);
        } elseif ($case === 'missing-xml') {
            unset($request['current_public_offer_return']['xml']);
        }
        file_put_contents($requestPath, json_encode($request, JSON_THROW_ON_ERROR));
        if ($case !== 'without-request') {
            $args['--negative-base-settlement-request'] = $requestPath;
        }
        $exit = Artisan::call('fiscal:dere:project-monthly-closing', $args);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        if (in_array($case, ['valid', 'without-request'], true)) {
            expect($exit)->toBe(0)->and($result['production_ready'])->toBeFalse()
                ->and(count($result['event_outputs']))->toBe(6)->and(is_file($report))->toBeTrue();
            if ($case === 'valid') {
                expect($result['negative_base_settlement']['ibs_nominal_proposal'])->toBe('0.64')
                    ->and($result['negative_base_settlement']['cbs_nominal_proposal'])->toBe('6.15')
                    ->and($result['negative_base_settlement']['received_return_verified'])->toBeFalse();
            } else {
                expect($result)->not->toHaveKey('negative_base_settlement');
            }
        } else {
            expect($exit)->toBe(1)->and(is_file($candidate))->toBeFalse()->and(is_file($report))->toBeFalse();
            foreach (['D1001', 'D1011', 'D1101', 'D1121', 'D2101'] as $event) {
                expect(is_file($candidate.'.'.$event.'.xml'))->toBeFalse();
            }
        }
    } finally {
        foreach ([$requestPath, $candidate, $report, $candidate.'.D1001.xml', $candidate.'.D1011.xml', $candidate.'.D1101.xml', $candidate.'.D1121.xml', $candidate.'.D2101.xml'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
})->with(['qualified-direct-only', 'qualified-fund-only', 'qualified-both-codes'])
    ->with(['valid', 'without-request', 'future', 'nonzero', 'missing-xml']);
