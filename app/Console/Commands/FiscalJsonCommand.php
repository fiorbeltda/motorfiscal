<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

abstract class FiscalJsonCommand extends Command
{
    /** @param array<string, mixed> $payload */
    protected function outputJson(array $payload): void
    {
        $this->line((string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }
}
