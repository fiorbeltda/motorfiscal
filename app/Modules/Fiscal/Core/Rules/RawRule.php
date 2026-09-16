<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

final readonly class RawRule
{
    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload) {}
}
