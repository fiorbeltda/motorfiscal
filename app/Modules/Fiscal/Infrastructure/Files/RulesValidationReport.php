<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Files;

final readonly class RulesValidationReport
{
    /** @param list<array<string, mixed>> $files */
    public function __construct(
        public bool $valid,
        public array $files,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['valid' => $this->valid, 'files' => $this->files];
    }
}
