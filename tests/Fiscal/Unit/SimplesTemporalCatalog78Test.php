<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;

/** @return array<string, mixed> */
function simples78TemporalCandidate(): array
{
    $root = dirname(__DIR__, 3);
    $catalog = json_decode((string) file_get_contents($root.'/resources/fiscal/catalogs/rtc-transition-2026.10.01-2.json'), true, 512, JSON_THROW_ON_ERROR);
    $policy = json_decode((string) file_get_contents($root.'/storage/app/phase4b-simples78-integration-preparation-20261015/component-policy.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog['simples_option_windows'][0]['filing_to'] = $policy['filing_through'];
    $catalog['simples_option_windows'][0]['cancellation_through'] = $policy['cancellation_through'];
    $catalog['simples_option_windows'][0]['available_at'] = $policy['available_at'];
    $catalog['simples_option_windows'][0]['qualification_policy'] = $policy;
    $catalog['simples_option_supersession_notices'][0]['full_act_verified'] = true;
    $catalog['simples_option_supersession_notices'][0]['full_act_verified_at'] = $policy['available_at'];

    return $catalog;
}

it('does not retrospectively resolve a superseding act before its native source observation', function (): void {
    $catalog = new TransitionCatalog(simples78TemporalCandidate());
    $on = LocalDate::fromString('2027-01-15');
    $before = $catalog->resolveSimplesOption($on, new DateTimeImmutable('2026-10-15T11:40:27.560995+00:00'));
    $at = $catalog->resolveSimplesOption($on, new DateTimeImmutable('2026-10-15T11:40:27.560996+00:00'));
    expect($before['reason'])->toBe('RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED')
        ->and($at['status'])->toBe('RESOLVED')
        ->and($at['row']['filing_to'])->toBe('2026-10-30');
});

it('does not use a new election row before source availability even before the supersession notice', function (): void {
    $result = (new TransitionCatalog(simples78TemporalCandidate()))->resolveSimplesOption(
        LocalDate::fromString('2027-01-15'), new DateTimeImmutable('2026-09-30T12:00:00-03:00'));
    expect($result['status'])->toBe('NOT_FOUND')->and($result['reason'])->toBe('RTC_SIMPLES_OPTION_WINDOW_NOT_PROVEN');
});

it('preserves conflict revocation and supersession guards in the new election row', function (string $condition): void {
    $catalog = simples78TemporalCandidate();
    if ($condition === 'conflict') {
        $catalog['simples_option_windows'][] = $catalog['simples_option_windows'][0];
    } else {
        $catalog['simples_option_windows'][0][$condition] = '2027-01-01T00:00:00-03:00';
    }
    $result = (new TransitionCatalog($catalog))->resolveSimplesOption(LocalDate::fromString('2027-01-15'), new DateTimeImmutable('2027-01-15T12:00:00-03:00'));
    expect($result['status'])->toBe($condition === 'conflict' ? 'CONFLICT' : 'NOT_FOUND');
})->with(['conflict', 'revoked_at', 'superseded_at']);

it('leaves the old notice and the second semester window unchanged', function (): void {
    $root = dirname(__DIR__, 3);
    $old = json_decode((string) file_get_contents($root.'/resources/fiscal/catalogs/rtc-transition-2026.10.01-2.json'), true, 512, JSON_THROW_ON_ERROR);
    $known = new DateTimeImmutable('2027-08-01T12:00:00-03:00');
    expect((new TransitionCatalog($old))->resolveSimplesOption(LocalDate::fromString('2027-01-15'), $known)['reason'])
        ->toBe('RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED');
    $on = LocalDate::fromString('2027-08-01');
    expect((new TransitionCatalog(simples78TemporalCandidate()))->resolveSimplesOption($on, $known))
        ->toBe((new TransitionCatalog($old))->resolveSimplesOption($on, $known));
});
