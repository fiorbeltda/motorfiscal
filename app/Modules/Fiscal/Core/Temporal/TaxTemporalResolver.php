<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Temporal;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

final class TaxTemporalResolver
{
    /** @param list<TemporalRecord> $records */
    public function resolve(array $records, LocalDate $effectiveOn, DateTimeImmutable $knownAt): TemporalResolution
    {
        $candidates = array_values(array_filter(
            $records,
            fn (TemporalRecord $record): bool => $record->wasEffectiveOn($effectiveOn) && $record->wasKnownAt($knownAt),
        ));

        if ($candidates === []) {
            return new TemporalResolution(TemporalResolutionStatus::NOT_FOUND, null);
        }

        if (count($candidates) > 1) {
            return new TemporalResolution(TemporalResolutionStatus::CONFLICT, null, $candidates);
        }

        return new TemporalResolution(TemporalResolutionStatus::RESOLVED, $candidates[0], $candidates);
    }
}
