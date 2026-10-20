<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

/** Explicit source-bound aliases, not arbitrary free-text matching or payment evidence. */
final readonly class CollectionBasisReference119
{
    /** @return array<string, array{article: string, aliases: list<string>}> */
    public static function references(): array
    {
        return [
            'SUPPLIER' => ['article' => 'LC214 art. 27, II',
                'aliases' => ['LC214_ART27_II', 'LC214 art. 27, II', 'LC214.2025.ART27.II']],
            'ACQUIRER' => ['article' => 'LC214 art. 36',
                'aliases' => ['LC214_ART36', 'LC214 art. 36', 'LC214.2025.ART36']],
            'SPLIT_PAYMENT' => ['article' => 'LC214 arts. 31-35',
                'aliases' => ['LC214_ARTS31_35', 'LC214 arts. 31-35', 'LC214.2025.ARTS31-35']],
        ];
    }

    /** @return array<string, mixed> */
    public function qualify(string $method, mixed $declared): array
    {
        if (! isset(self::references()[$method])) {
            return $this->stop('RTC_COLLECTION119_METHOD_NOT_QUALIFIED');
        }
        if ($declared === null && $method === 'SUPPLIER') {
            return $this->bound($method, false);
        }
        if (! is_string($declared) || trim($declared) === '') {
            return $this->stop('RTC_COLLECTION_BASIS_MISSING');
        }
        foreach (self::references() as $referenceMethod => $reference) {
            if (in_array($declared, $reference['aliases'], true)) {
                return $referenceMethod === $method ? $this->bound($method, true)
                    : $this->stop('RTC_COLLECTION119_BASIS_METHOD_CONFLICT', 'INVALID');
            }
        }

        return $this->stop('RTC_COLLECTION119_BASIS_NOT_QUALIFIED');
    }

    /** @return array<string, mixed> */
    private function bound(string $method, bool $declared): array
    {
        return ['status' => 'RESOLVED', 'reason' => 'RTC_COLLECTION119_REFERENCE_BOUND',
            'canonical_reference' => self::references()[$method]['aliases'][2],
            'source_article' => self::references()[$method]['article'], 'method' => $method,
            'reference_declared' => $declared, 'payment_proven' => false,
            'legal_applicability_approved' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => $reason,
            'payment_proven' => false, 'legal_applicability_approved' => false, 'production_ready' => false];
    }
}
