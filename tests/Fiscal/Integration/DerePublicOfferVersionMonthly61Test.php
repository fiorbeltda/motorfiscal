<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('outputs the matched active public version and refuses invalid chains before any XML write', function (string $case): void {
    $base = storage_path('app/phase4b-dere9121-61-version-integration-20261013/');
    $request = json_decode((string) file_get_contents($base.'command-request.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($case === 'alteration') {
        $request['current_public_offer_return']['events'] = array_slice($request['current_public_offer_return']['events'], 0, 2);
    } elseif ($case === 'excluded') {
        $request['current_public_offer_return']['events'] = array_slice($request['current_public_offer_return']['events'], 0, 3);
    } elseif ($case === 'economics') {
        $capture = json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-59-version-preparation-20261013/version-native-v2-result.json')), true, 512, JSON_THROW_ON_ERROR);
        $request['current_public_offer_return']['events'] = $capture['events'];
    } elseif ($case === 'sequence-reset') {
        $request['current_public_offer_return']['events'][3]['return_xml'] = str_replace('<seqEvento>03</seqEvento>', '<seqEvento>00</seqEvento>', $request['current_public_offer_return']['events'][3]['return_xml']);
    } elseif ($case === 'expectation') {
        $request['current_public_offer_return']['expected_sequence'] = '00';
    } elseif ($case === 'dual-xml') {
        $request['current_public_offer_return']['xml'] = '<ignored/>';
    }
    $file = tempnam(sys_get_temp_dir(), 'dere61-version-');
    if ($file === false) {
        throw new RuntimeException('Cannot create test request.');
    }
    $candidate = $file.'.xml';
    $report = $file.'.report.json';
    try {
        file_put_contents($file, json_encode($request, JSON_THROW_ON_ERROR));
        $exit = Artisan::call('fiscal:dere:project-monthly-closing', ['snapshot' => $base.'financial-snapshot.json', 'month' => $base.'command-month.json',
            '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.13-28-draft61.json', '--negative-base-settlement-request' => $file,
            '--candidate-output' => $candidate, '--report' => $report]);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        if (in_array($case, ['reinclusion', 'alteration'], true)) {
            $events = $request['current_public_offer_return']['events'];
            $active = $events[array_key_last($events)];
            expect($exit)->toBe(0)->and($result['production_ready'])->toBeFalse()
                ->and(count($result['event_outputs']))->toBe(6)
                ->and((string) file_get_contents($candidate.'.D2101.xml'))->toBe($active['origin_xml'])
                ->and($result['projection']['active_public_event_output']['sequence'])->toBe($case === 'alteration' ? '01' : '03')
                ->and($result['negative_base_settlement']['ibs_nominal_proposal'])->toBe('0.74')
                ->and($result['negative_base_settlement']['cbs_nominal_proposal'])->toBe('7.02');
        } else {
            expect($exit)->toBe(1)->and(is_file($candidate))->toBeFalse()->and(is_file($report))->toBeFalse();
            foreach (['D1001', 'D1011', 'D1101', 'D1121', 'D2101'] as $event) {
                expect(is_file($candidate.'.'.$event.'.xml'))->toBeFalse();
            }
        }
    } finally {
        foreach ([$file, $candidate, $report, $candidate.'.D1001.xml', $candidate.'.D1011.xml', $candidate.'.D1101.xml', $candidate.'.D1121.xml', $candidate.'.D2101.xml'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
})->with(['reinclusion', 'alteration', 'excluded', 'economics', 'sequence-reset', 'expectation', 'dual-xml']);
