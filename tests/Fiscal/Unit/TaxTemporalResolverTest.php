<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Core\Temporal\TaxTemporalResolver;
use App\Modules\Fiscal\Core\Temporal\TemporalRecord;
use App\Modules\Fiscal\Core\Temporal\TemporalResolutionStatus;

function temporalRecord(string $id, string $effectiveFrom, ?string $effectiveTo, string $recordedAt, ?string $supersededAt = null): TemporalRecord
{
    return new TemporalRecord(
        $id,
        LocalDate::fromString($effectiveFrom),
        $effectiveTo === null ? null : LocalDate::fromString($effectiveTo),
        new DateTimeImmutable($recordedAt),
        $supersededAt === null ? null : new DateTimeImmutable($supersededAt),
        ['id' => $id],
    );
}

it('resolves what was effective and known at the requested instants', function (): void {
    $records = [
        temporalRecord('old', '2026-01-01', '2026-12-31', '2025-12-01T00:00:00Z', '2026-08-01T00:00:00Z'),
        temporalRecord('corrected', '2026-01-01', '2026-12-31', '2026-08-01T00:00:00Z'),
    ];
    $resolver = new TaxTemporalResolver;

    $beforeCorrection = $resolver->resolve($records, LocalDate::fromString('2026-06-01'), new DateTimeImmutable('2026-07-01T00:00:00Z'));
    $afterCorrection = $resolver->resolve($records, LocalDate::fromString('2026-06-01'), new DateTimeImmutable('2026-09-01T00:00:00Z'));

    expect($beforeCorrection->record?->id)->toBe('old')
        ->and($afterCorrection->record?->id)->toBe('corrected');
});

it('surfaces overlapping active knowledge as a conflict', function (): void {
    $resolution = (new TaxTemporalResolver)->resolve([
        temporalRecord('a', '2026-01-01', null, '2026-01-01T00:00:00Z'),
        temporalRecord('b', '2026-01-01', null, '2026-01-02T00:00:00Z'),
    ], LocalDate::fromString('2026-02-01'), new DateTimeImmutable('2026-03-01T00:00:00Z'));

    expect($resolution->status)->toBe(TemporalResolutionStatus::CONFLICT)
        ->and($resolution->candidates)->toHaveCount(2);
});
