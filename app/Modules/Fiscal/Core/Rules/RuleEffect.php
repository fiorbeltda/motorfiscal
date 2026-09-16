<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

final readonly class RuleEffect
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public EffectType $type,
        public array $payload,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['type' => $this->type->value, ...$this->payload];
    }
}
