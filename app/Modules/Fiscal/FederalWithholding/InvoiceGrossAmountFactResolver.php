<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Facts\FactResolutionResult;
use App\Modules\Fiscal\Core\Facts\FactResolver;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;

final class InvoiceGrossAmountFactResolver implements FactResolver
{
    public function supports(string $factKey): bool
    {
        return $factKey === 'invoice.gross_amount';
    }

    public function dependencies(string $factKey): array
    {
        return $this->supports($factKey) ? ['operation.amount'] : [];
    }

    public function resolve(string $factKey, FiscalDecisionContext $context): FactResolutionResult
    {
        $amount = $context->facts->value('operation.amount');
        if (! is_string($amount)) {
            return new FactResolutionResult;
        }

        return new FactResolutionResult([
            new FactValue(
                'invoice.gross_amount',
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
