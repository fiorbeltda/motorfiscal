<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Trace;

final readonly class TraceEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $sequence,
        public string $type,
        public array $payload,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'type' => $this->type,
            'payload' => $this->payload,
        ];
    }
}
