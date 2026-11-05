<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/agent_a/arithmetic156_fixture.php';

it('exposes explicit legacy coexistence and extinction per native family and period', function (string $profile, int $year): void {
    [$input, $knowledge] = arithmetic156($profile, $year);
    $result = evaluate156($input, $knowledge);
    $trace = array_column($result['trace'], null, 'stage');
    if ($profile === 'import-service') {
        // Document emission remains blocked, so there is no final combined legacy decision.
        expect($result['decision'])->toBe([])->and($result['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');

        return;
    }
    $decision = $trace['SYNTHETIC_ARITHMETIC156']['candidate_decision'];
    $legacy = $decision['legacy_taxes'];
    expect($legacy['pis_cofins_status'])->toBe($year === 2026 ? 'COEXISTS' : 'EXTINCT_FOR_CONSUMPTION')
        ->and($legacy['iss_status'])->toBe($year === 2033 ? 'EXTINCT' : 'COEXISTS')
        ->and($legacy['iss_factor'])->toBe(match ($year) {
            2026, 2027 => '1.00', 2030 => '0.80', 2033 => '0.00'
        })
        ->and($decision['base']['taxable'])->toBe($profile === 'health-glosa' ? '900.00' : '1000.00');
    // Legacy metadata cannot silently become monetary PIS/Cofins assessment.
    expect($legacy)->not->toHaveKeys(['pis_amount', 'cofins_amount']);
})->with(array_keys(profiles156()))->with([2026, 2027, 2030, 2033]);

it('blocks synthetic arithmetic in the integrated runner rather than publishing a combined decision', function (string $profile): void {
    [$input, $knowledge] = arithmetic156($profile, 2026);
    $result = evaluate156($input, $knowledge, true);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['decision'])->toBe([])
        ->and($result['reason_codes'])->toContain('RTC_SYNTHETIC_TEST_ONLY');
})->with(['saas', 'education-presential', 'health-presential']);
