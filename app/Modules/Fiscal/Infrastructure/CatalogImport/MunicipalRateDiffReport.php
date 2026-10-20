<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class MunicipalRateDiffReport
{
    public function __construct(
        public int $added,
        public int $changed,
        public int $removed,
        public int $unchanged,
    ) {}

    /** @return array{ADDED: int, CHANGED: int, REMOVED: int, UNCHANGED: int} */
    public function toArray(): array
    {
        return [
            'ADDED' => $this->added,
            'CHANGED' => $this->changed,
            'REMOVED' => $this->removed,
            'UNCHANGED' => $this->unchanged,
        ];
    }
}
