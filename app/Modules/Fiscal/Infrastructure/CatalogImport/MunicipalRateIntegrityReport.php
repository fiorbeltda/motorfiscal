<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class MunicipalRateIntegrityReport
{
    /** @param list<array<string, string>> $samples */
    public function __construct(
        public int $overlaps,
        public int $gaps,
        public array $samples,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['overlaps' => $this->overlaps, 'gaps' => $this->gaps, 'samples' => $this->samples];
    }
}
