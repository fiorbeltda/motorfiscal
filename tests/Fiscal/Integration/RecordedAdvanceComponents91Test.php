<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('retains all43 record-binding cases under the installed component without accepting native exit0 alone', function (): void {
    $process = new Process([PHP_BINARY, '-d', 'memory_limit=512M', base_path('storage/app/phase4b-advance91-integration-20261015/native89-installed.php')], base_path());
    $process->setTimeout(180)->mustRun();
    $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    expect($report['status'])->toBe('STAGED89_LOCAL_RECORD_BINDING_ONLY')->and($report['passed'])->toBe(43)
        ->and($report['cases'])->toHaveCount(43)->and($report['production_ready'])->toBeFalse();
    foreach ($report['cases'] as $case) {
        expect($case['status'])->toBe('PASS');
    }
});

it('retains all28 final-reconciliation cases with14 independent decimal answers and context-bound tokens', function (): void {
    $folder = base_path('storage/app/phase4b-advance91-integration-20261015');
    $process = new Process([PHP_BINARY, '-d', 'memory_limit=512M', $folder.'/native90-installed.php'], base_path());
    $process->setTimeout(180)->mustRun();
    $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    expect($report['status'])->toBe('STAGED90_CAPTURED_VALUE_RECONCILIATION_ONLY')->and($report['native_cases_passed'])->toBe(28)
        ->and($report['prerequisite89_cases'])->toBe(43)->and($report['cases'])->toHaveCount(28)
        ->and($report['production_ready'])->toBeFalse();
    $oracles = json_decode((string) file_get_contents($folder.'/decimal90-independent-oracle.json'), true, 512, JSON_THROW_ON_ERROR);
    $answers = array_column($oracles, 'expected', 'case');
    $tokens = [];
    foreach ($report['cases'] as $case) {
        if (! isset($case['taxes'])) {
            expect($case['status'])->toBe('PASS');

            continue;
        }
        foreach ($answers[$case['case']] as $tax => $values) {
            foreach ($values as $key => $value) {
                expect($case['taxes'][$tax][$key])->toBe($value);
            }
        }
        $tokens[] = $case['proposal_token'];
    }
    expect($tokens)->toHaveCount(14)->and(array_unique($tokens))->toHaveCount(14);
});
