<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class ImportIssue
{
    public function __construct(
        public string $code,
        public string $message,
        public ?string $entry = null,
        public ?int $line = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'entry' => $this->entry,
            'line' => $this->line,
        ];
    }
}
