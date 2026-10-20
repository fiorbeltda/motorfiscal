<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Facts\FactResolutionResult;
use App\Modules\Fiscal\Core\Facts\FactResolver;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;

final class PaymentGrossAmountFactResolver implements FactResolver
{
    public function supports(string $factKey): bool
    {
        return $factKey === 'payment.gross_amount';
    }

    public function dependencies(string $factKey): array
    {
        return $this->supports($factKey) ? ['operation.amount'] : [];
    }

    public function resolve(string $factKey, FiscalDecisionContext $context): FactResolutionResult
    {
        $payerType = $context->facts->value('payer.person_type');
        if (is_string($payerType) && str_contains($payerType, 'PUBLIC_ADMINISTRATION')) {
            // Public withholding is based on this payment event, which may be a partial payment or advance.
            return new FactResolutionResult;
        }
        $amount = $context->facts->value('operation.amount');
        if (! is_string($amount)) {
            return new FactResolutionResult;
        }

        return new FactResolutionResult([
            new FactValue(
                'payment.gross_amount',
                $amount,
                FactSourceType::DERIVED,
                $context->competenceDate,
                $context->competenceDate,
                $context->knowledgeAt,
                confidence: 'HIGH',
            ),
        ]);
    }
}
