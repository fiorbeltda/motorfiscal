<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/**
 * B30: typed base-of-calculation adjustments of LC 214 art. 12 §§1-3 (wording of LC 227/2026).
 *
 * Inclusions (§1): price-adjustment increases (I), interest/fines/charges (II), conditional discounts (III: never
 * reduce the base; added back when the gross was declared net of them), freight charged when the transport is done
 * by the supplier or on its account (IV), taxes and public prices other than the §2 exclusions (V) and other amounts
 * charged, including insurance and fees (VI).
 * Exclusions (§2): unconditional discounts (III and §3, including the supplier's own non-onerous loyalty programme),
 * third-party reimbursements with documents in the third party's name (IV), legacy taxes until 2032 (V) and the
 * art. 149-A contribution (VI). The legacy kinds already accepted by the engine keep their meaning.
 */
final readonly class BaseCompositionB30
{
    public const KEY = 'base_composition_b30';

    public const POLICY = ['version' => 'B30', 'source' => 'LC214 art. 12 §§1º-3º',
        'inclusions' => ['PRICE_ADJUSTMENT_INCREASE', 'INTEREST_FINE_CHARGE', 'CONDITIONAL_DISCOUNT', 'FREIGHT_CHARGED',
            'TAXES_AND_PUBLIC_PRICES', 'OTHER_CHARGED_AMOUNT'],
        'exclusions' => ['COSIP_EXCLUSION'], 'negative_base' => 'REFUSED'];

    /** Taxes that §2 excludes and therefore can never be included under §1, V. */
    private const EXCLUDED_TAXES = ['IBS', 'CBS', 'IPI', 'ICMS', 'ISS', 'PIS', 'COFINS', 'COSIP'];

    /** @param array<string, mixed> $codes */
    public static function enabled(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null)
            && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY);
    }

    public static function handles(mixed $kind): bool
    {
        return in_array($kind, [...self::POLICY['inclusions'], ...self::POLICY['exclusions']], true);
    }

    /**
     * @param  array<string, mixed>  $adjustment
     * @return array{effect: 'PLUS'|'MINUS'|'NONE', source_article: string}|array{error: string}
     */
    public function classify(array $adjustment): array
    {
        return match ($adjustment['kind']) {
            'PRICE_ADJUSTMENT_INCREASE' => ['effect' => 'PLUS', 'source_article' => 'LC214 art. 12, §1º, I'],
            'INTEREST_FINE_CHARGE' => ['effect' => 'PLUS', 'source_article' => 'LC214 art. 12, §1º, II'],
            'CONDITIONAL_DISCOUNT' => match ($adjustment['already_deducted_from_gross'] ?? null) {
                true => ['effect' => 'PLUS', 'source_article' => 'LC214 art. 12, §1º, III'],
                false => ['effect' => 'NONE', 'source_article' => 'LC214 art. 12, §1º, III'],
                default => ['error' => 'RTC_CONDITIONAL_DISCOUNT_GROSS_STATE_UNPROVEN'],
            },
            'FREIGHT_CHARGED' => in_array($adjustment['transport_responsibility'] ?? null, ['SUPPLIER', 'ON_SUPPLIER_ACCOUNT'], true)
                ? ['effect' => 'PLUS', 'source_article' => 'LC214 art. 12, §1º, IV']
                : ['error' => 'RTC_FREIGHT_NOT_SUPPLIER_TRANSPORT'],
            'TAXES_AND_PUBLIC_PRICES' => is_string($adjustment['tax'] ?? null) && trim($adjustment['tax']) !== ''
                && ! in_array(strtoupper($adjustment['tax']), self::EXCLUDED_TAXES, true)
                ? ['effect' => 'PLUS', 'source_article' => 'LC214 art. 12, §1º, V']
                : ['error' => 'RTC_INCLUDED_TAX_IS_EXCLUDED_OR_UNNAMED'],
            'OTHER_CHARGED_AMOUNT' => is_string($adjustment['description'] ?? null) && trim($adjustment['description']) !== ''
                ? ['effect' => 'PLUS', 'source_article' => 'LC214 art. 12, §1º, VI']
                : ['error' => 'RTC_OTHER_CHARGED_AMOUNT_UNDESCRIBED'],
            'COSIP_EXCLUSION' => ['effect' => 'MINUS', 'source_article' => 'LC214 art. 12, §2º, VI'],
            default => ['error' => 'RTC_ADJUSTMENT_KIND_UNKNOWN'],
        };
    }

    /**
     * §3: a loyalty-programme discount is unconditional only if granted non-onerously by the supplier itself.
     *
     * @param  array<string, mixed>  $adjustment
     */
    public function loyaltyConflict(array $adjustment): bool
    {
        return ($adjustment['loyalty_program'] ?? false) === true
            && ($adjustment['loyalty_granted_non_onerously_by_supplier'] ?? null) !== true;
    }
}
