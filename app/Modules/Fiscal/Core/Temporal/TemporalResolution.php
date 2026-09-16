<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Temporal;

final readonly class TemporalResolution
{
    /** @param list<TemporalRecord> $candidates */
    public function __construct(
        public TemporalResolutionStatus $status,
        public ?TemporalRecord $record,
        public array $candidates = [],
    ) {}
}
