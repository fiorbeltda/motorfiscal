<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

final readonly class CrossBorderTaxPoint165
{
    public const KEY = 'cross_border_tax_point_a165';

    public const POLICY = ['version' => 'A165', 'contract' => 'RTC-CROSS-BORDER-TAX-POINT-A165-V1',
        'markets' => ['IMPORT', 'EXPORT'], 'source_hash' => SplitLegalEvidence162::LAW_HASH,
        'articles' => ['LC214 art10', 'LC214 art64 par5 I a', 'LC214 art80'],
        'parent_policy' => TaxPointContext86::CONTRACT, 'posting' => false];

    /** @param array<string, mixed> $policy */
    public static function valid(array $policy): bool
    {
        return CanonicalJson::hash($policy) === CanonicalJson::hash(self::POLICY);
    }
}
