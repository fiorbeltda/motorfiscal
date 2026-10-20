<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Support\Facades\Artisan;

it('selects public D9121 settlement and rejects inconsistent requests before writing six monthly XMLs', function (string $case): void {
    $root = dirname(__DIR__, 3);
    $e = $root.'/storage/app/phase4b-dere9121-55-command-integration-20261013/';
    $request = json_decode((string) file_get_contents($e.'command-settlement-request.json'), true, 512, JSON_THROW_ON_ERROR);
    $options = $request;
    unset($options['current_public_offer_return']);
    $args = ['snapshot' => $root.'/storage/app/phase4b-dere9121-54-preparation-20261013/financial-snapshot.json',
        'month' => $e.'command-month-input.json', '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.13-28-draft54.json'];
    $requestPath = tempnam(sys_get_temp_dir(), 'd9121-55-request-');
    $optionsPath = tempnam(sys_get_temp_dir(), 'd9121-55-options-');
    $candidate = $requestPath.'.xml';
    $report = $requestPath.'.report.json';
    $args['--candidate-output'] = $candidate;
    $args['--report'] = $report;
    try {
        if (in_array($case, ['snapshot_hash', 'monthly_input_hash', 'baseline_projection_hash', 'operation_hash', 'gross_tax_hash'], true)) {
            $request[$case] = str_repeat('0', 64);
        } elseif ($case === 'real-request') {
            $request['synthetic'] = false;
        } elseif ($case === 'prior-gap') {
            $request['prior_period'] = '2026-07';
        } elseif ($case === 'parameters-mismatch') {
            $options['parameters']['infoBCN'][0]['usarBCNAcum'] = '0';
        } elseif ($case === 'events-mismatch') {
            $options['prior_closing_events'] = [];
        } elseif ($case === 'future-return') {
            $request['current_public_offer_return']['xml'] = str_replace('2026-10-13T10:00:01.0000000+00:00', '2099-10-13T10:00:01.0000000+00:00', $request['current_public_offer_return']['xml']);
        } elseif ($case === 'return-pgcc') {
            $request['current_public_offer_return']['pgcc_source_hash'] = str_repeat('0', 64);
        } elseif ($case === 'return-sequence') {
            $request['current_public_offer_return']['expected_sequence'] = '01';
        } elseif ($case === 'return-total') {
            $request['current_public_offer_return']['xml'] = str_replace('<vApurTot>98.17</vApurTot>', '<vApurTot>99.17</vApurTot>', $request['current_public_offer_return']['xml']);
        } elseif ($case === 'missing-return-xml') {
            unset($request['current_public_offer_return']['xml']);
        } elseif ($case === 'missing-return') {
            unset($request['current_public_offer_return']);
        } elseif ($case === 'unsupported-bundle') {
            $old = $root.'/storage/app/phase4b-negative-base50-closing-draft-20261009/';
            $args['snapshot'] = $old.'command-financial-snapshot.json';
            $args['month'] = $old.'command-month-input.json';
            $args['--bundle-manifest'] = 'bundles/consumption-phase4b-2026.10.09-28-draft50.json';
        }
        file_put_contents($requestPath, json_encode($request, JSON_THROW_ON_ERROR));
        file_put_contents($optionsPath, json_encode($options, JSON_THROW_ON_ERROR));
        if ($case !== 'without-request') {
            $args['--negative-base-settlement-request'] = $requestPath;
        }
        if (in_array($case, ['both-valid', 'parameters-mismatch', 'events-mismatch'], true)) {
            $args['--negative-base-request'] = $optionsPath;
        }
        $exit = Artisan::call('fiscal:dere:project-monthly-closing', $args);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        if (in_array($case, ['derived-valid', 'both-valid', 'without-request'], true)) {
            expect($exit)->toBe(0)->and($result['production_ready'])->toBeFalse()
                ->and(count($result['event_validations']))->toBe(6)->and(is_file($report))->toBeTrue();
            if ($case === 'without-request') {
                expect($result)->not->toHaveKey('negative_base_settlement')
                    ->and($result)->not->toHaveKey('negative_base_request_hash');
            } else {
                $settlement = $result['negative_base_settlement'];
                expect($result['negative_base_settlement_request_hash'])->toBe(CanonicalJson::hash($request))
                    ->and($settlement['ibs_nominal_proposal'])->toBe('0.74')
                    ->and($settlement['cbs_nominal_proposal'])->toBe('7.02')
                    ->and($settlement['eligible_for_posting'])->toBeFalse()
                    ->and($result['negative_base_options_request_origin'])->toBe($case === 'both-valid' ? 'EXPLICIT_MATCHING_REQUEST' : 'DERIVED_FROM_SETTLEMENT_REQUEST')
                    ->and($result['negative_base_request_input'])->toBe($case === 'both-valid' ? $options : $request);
                $before = json_decode((string) file_get_contents($e.'monthly-report.json'), true, 512, JSON_THROW_ON_ERROR);
                $before['projection']['negative_base_options_projection']['request_hash'] = CanonicalJson::hash($case === 'both-valid' ? $options : $request);
                expect($result['projection'])->toBe($before['projection']);
            }
        } else {
            expect($exit)->toBe(1)->and(is_file($candidate))->toBeFalse()->and(is_file($report))->toBeFalse();
            foreach (['D1101', 'D1121', 'D1011', 'D1001', 'D2101'] as $name) {
                expect(is_file($candidate.'.'.$name.'.xml'))->toBeFalse();
            }
            if (in_array($case, ['parameters-mismatch', 'events-mismatch'], true)) {
                expect($result['error'])->toContain('identical parameters and prior closing events');
            } elseif ($case === 'unsupported-bundle') {
                expect($result['error'])->toContain('does not qualify a negative-base settlement request');
            }
        }
    } finally {
        foreach ([$requestPath, $optionsPath, $candidate, $report, $candidate.'.D1101.xml', $candidate.'.D1121.xml', $candidate.'.D1011.xml', $candidate.'.D1001.xml', $candidate.'.D2101.xml'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
})->with(['derived-valid', 'both-valid', 'without-request', 'snapshot_hash', 'monthly_input_hash', 'baseline_projection_hash', 'operation_hash', 'gross_tax_hash', 'real-request', 'prior-gap', 'parameters-mismatch', 'events-mismatch', 'unsupported-bundle', 'future-return', 'return-pgcc', 'return-sequence', 'return-total', 'missing-return-xml', 'missing-return']);
