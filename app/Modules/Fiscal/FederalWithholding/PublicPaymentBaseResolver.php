<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class PublicPaymentBaseResolver
{
    /** @return array{base: ?DecimalValue, reason: ?string, missing: list<string>} */
    public function resolve(FactBag $facts): array
    {
        $missing = $facts->missing(['payment.gross_amount', 'public.glosa_without_new_invoice', 'public.has_late_additions']);
        if ($missing !== []) {
            return ['base' => null, 'reason' => 'PUBLIC_PAYMENT_BASE_FACTS_MISSING', 'missing' => $missing];
        }
        $paid = DecimalValue::fromString((string) $facts->value('payment.gross_amount'));
        if ($paid->isNegative()) {
            return ['base' => null, 'reason' => 'PUBLIC_PAYMENT_NEGATIVE', 'missing' => []];
        }
        $glosa = DecimalValue::fromString('0');
        $additions = DecimalValue::fromString('0');
        $original = null;
        if ($facts->value('public.glosa_without_new_invoice') === true || $facts->value('public.has_late_additions') === true) {
            $missing = $facts->missing(['invoice.gross_amount']);
            if ($missing !== []) {
                return ['base' => null, 'reason' => 'PUBLIC_ORIGINAL_INVOICE_MISSING', 'missing' => $missing];
            }
            $original = DecimalValue::fromString((string) $facts->value('invoice.gross_amount'));
            if ($original->isNegative()) {
                return ['base' => null, 'reason' => 'PUBLIC_ORIGINAL_INVOICE_NEGATIVE', 'missing' => []];
            }
        }
        if ($facts->value('public.glosa_without_new_invoice') === true) {
            $missing = $facts->missing(['public.glosa_amount']);
            if ($missing !== []) {
                return ['base' => null, 'reason' => 'PUBLIC_GLOSA_EVIDENCE_MISSING', 'missing' => $missing];
            }
            $glosa = DecimalValue::fromString((string) $facts->value('public.glosa_amount'));
            if ($glosa->isNegative() || $original === null || $glosa->compareTo($original) > 0) {
                return ['base' => null, 'reason' => 'PUBLIC_GLOSA_PAYMENT_CONFLICT', 'missing' => []];
            }
        }
        if ($facts->value('public.has_late_additions') === true) {
            $missing = $facts->missing(['public.late_interest', 'public.late_penalties']);
            if ($missing !== []) {
                return ['base' => null, 'reason' => 'PUBLIC_LATE_ADDITIONS_MISSING', 'missing' => $missing];
            }
            $interest = DecimalValue::fromString((string) $facts->value('public.late_interest'));
            $penalties = DecimalValue::fromString((string) $facts->value('public.late_penalties'));
            if ($interest->isNegative() || $penalties->isNegative()) {
                return ['base' => null, 'reason' => 'PUBLIC_LATE_ADDITIONS_NEGATIVE', 'missing' => []];
            }
            $additions = $interest->plus($penalties);
        }
        if ($original !== null && $paid->compareTo($original->minus($glosa)->plus($additions)) !== 0) {
            return ['base' => null, 'reason' => 'PUBLIC_INVOICE_PAYMENT_RECONCILIATION_REQUIRED', 'missing' => []];
        }

        return ['base' => ($original ?? $paid)->plus($additions)->rounded(2), 'reason' => null, 'missing' => []];
    }
}
