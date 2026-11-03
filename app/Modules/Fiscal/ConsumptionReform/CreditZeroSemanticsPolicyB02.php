<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/**
 * B02 activation gate. The explained zero-credit output changes decision hashes,
 * so it may only be enabled by a new catalog version carrying this exact policy
 * block. No catalog published up to checkpoint 143 carries it, which keeps every
 * accepted replay on the historical output.
 */
final readonly class CreditZeroSemanticsPolicyB02
{
    public const KEY = 'credit_zero_semantics_b02';

    public const POLICY = [
        'version' => 'B02',
        'mode' => 'EXPLAIN_ZERO_ANNOTATIONS_ONLY',
        'status_or_amount_changed' => false,
        'added_component_keys' => ['zero_semantics_version', 'zero_causes', 'amount_semantics'],
    ];

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        $policy = $codes[self::KEY] ?? null;

        return is_array($policy) && CanonicalJson::hash($policy) === CanonicalJson::hash(self::POLICY);
    }
}
