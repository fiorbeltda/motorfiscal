<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentObligationEngine;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;

it('separates general and specific RTC declaration dates from deployment and ISS obligations', function (string $service, string $on, bool $mandatory): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.01-2.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new DocumentObligationEngine)->resolve($service, 'REGULAR', LocalDate::fromString($on), new DateTimeImmutable('2026-10-01T17:00:00Z'), $catalog);
    expect($result['status'])->toBe('RESOLVED')
        ->and($result['required_by_schedule'])->toBe($mandatory)
        ->and($result['iss_document_obligations'])->toBe('INDEPENDENT')
        ->and($result['layout_deployment'])->toBe('INDEPENDENT')
        ->and($result['full_act_review_required'])->toBeTrue();
})->with([
    ['010101', '2026-09-30', false], ['010101', '2026-10-01', true],
    ['010501', '2026-10-01', false], ['010501', '2026-11-30', false], ['010501', '2026-12-01', true],
]);

it('does not apply an unavailable or overlapping documentary schedule', function (): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.01-2.json'), true, 512, JSON_THROW_ON_ERROR);
    $engine = new DocumentObligationEngine;
    $unavailable = $engine->resolve('010101', 'REGULAR', LocalDate::fromString('2026-10-01'), new DateTimeImmutable('2026-09-20T00:00:00Z'), $catalog);
    expect($unavailable['reason'])->toBe('RTC_DOCUMENT_OBLIGATION_NOT_PROVEN');
    $catalog['document_obligations'][] = $catalog['document_obligations'][0];
    $conflict = $engine->resolve('010101', 'REGULAR', LocalDate::fromString('2026-10-01'), new DateTimeImmutable('2026-10-01T17:00:00Z'), $catalog);
    expect($conflict['status'])->toBe('INVALID')->and($conflict['reason'])->toBe('RTC_DOCUMENT_OBLIGATION_CONFLICT');
});

it('withdraws stale Simples qualification only after the supersession notice is known', function (): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-transition-2026.10.01-2.json'), true, 512, JSON_THROW_ON_ERROR);
    $transition = new TransitionCatalog($catalog);
    $on = LocalDate::fromString('2027-01-15');
    $historical = $transition->resolveSimplesOption($on, new DateTimeImmutable('2026-09-27T00:00:00Z'));
    $current = $transition->resolveSimplesOption($on, new DateTimeImmutable('2027-01-15T00:00:00Z'));
    expect($historical['status'])->toBe('RESOLVED')->and($historical['row']['filing_to'])->toBe('2026-09-30')
        ->and($current['reason'])->toBe('RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED');
});
