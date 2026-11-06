<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/agent_b/temporal_matrix_b39_fixture.php';

// B39 (4B.1.1 / 4B.8.12): matriz de resultados esperados por família × ano, 2026-2033, no bundle mais recente.
// Cada ano é avaliado depois da própria operação; nenhuma célula posterior a 2026 pode sair calculada por estimativa.

const B39_EXPECTED_2026 = [
    'SAAS_STANDARD' => ['1000.00', '9.00', '1.00'],
    'HOTEL_B35' => ['1000.00', '5.40', '0.60'],
    'TRAVEL_AGENCY_B36' => ['350.00', '1.89', '0.21'],
    'REAL_ESTATE_LEASE_B37' => ['700.00', '1.89', '0.21'],
    'HEALTH_PLAN_BROKER_B38' => ['1000.00', '3.60', '0.40'],
];

it('calculates every family in 2026 and stops 2027-2033 at the unproven cIndOp table, never estimating', function (): void {
    $matrix = temporalB39Matrix();
    expect(array_keys($matrix))->toEqualCanonicalizing(array_keys(B39_EXPECTED_2026));
    foreach ($matrix as $family => $cells) {
        expect(array_column($cells, 'year'))->toBe(B39_YEARS);
        [$base, $cbs, $ibs] = B39_EXPECTED_2026[$family];
        expect($cells[0]['status'])->toBe('VALID')
            ->and([$cells[0]['taxable_base'], $cells[0]['cbs'], $cells[0]['ibs']])->toBe([$base, $cbs, $ibs]);
        foreach (array_slice($cells, 1) as $cell) {
            expect($cell['status'])->toBe('ACTION_REQUIRED')->and($cell)->not->toHaveKey('cbs');
            $expected = $family === 'HEALTH_PLAN_BROKER_B38'
                ? 'RTC_UNIFORM_NATIONAL_REFERENCE_RATE_ROUTE_NOT_MATERIALIZED' : 'RTC_CINDOP126_OUTSIDE_LOCAL_COVERAGE';
            expect($cell['reasons'])->toBe([$expected]);
        }
    }
});

it('shows the next blocker of each year once the 2026-only cIndOp reference is set aside (synthetic, in memory)', function (): void {
    foreach (temporalB39Matrix(true) as $family => $cells) {
        expect($cells[0]['status'])->toBe('VALID');
        foreach (array_slice($cells, 1) as $cell) {
            $expected = match (true) {
                $family === 'HEALTH_PLAN_BROKER_B38' => 'RTC_UNIFORM_NATIONAL_REFERENCE_RATE_ROUTE_NOT_MATERIALIZED',
                $cell['year'] <= '2028' => 'RTC_RATE_NOT_PROVEN:CBS',
                default => 'RTC_RATE_NOT_PROVEN:IBS_STATE',
            };
            expect($cell['status'])->toBe('ACTION_REQUIRED')->and($cell['reasons'])->toBe([$expected]);
        }
    }
});

it('writes the matrix as replayable evidence', function (): void {
    $dir = storage_path('app/phase4b-b39-development-20261106');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $report = ['task' => 'B39', 'bundle' => B39_BUNDLE, 'years' => B39_YEARS,
        'actual' => temporalB39Matrix(), 'next_blocker_synthetic' => temporalB39Matrix(true),
        'note' => 'Cada ano avaliado 30 dias depois da operação. next_blocker_synthetic remove em memória a referência cIndOp local de 2026 só para revelar o bloqueio seguinte; não tem efeito legal.'];
    file_put_contents($dir.'/temporal-matrix-b39.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    expect(json_decode((string) file_get_contents($dir.'/temporal-matrix-b39.json'), true)['actual'])->toBe($report['actual']);
});
