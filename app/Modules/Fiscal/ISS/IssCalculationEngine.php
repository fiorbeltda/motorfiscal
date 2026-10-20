<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Classification\ServiceFamily;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Formula\FormulaRegistry;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class IssCalculationEngine
{
    public function __construct(private FormulaRegistry $formulas) {}

    /** @param array<string, string> $argumentBindings */
    public function calculate(
        ServiceFamily $family,
        FactBag $facts,
        DecimalValue $rate,
        string $formulaId,
        array $argumentBindings = ['base' => 'iss.taxable_base', 'rate' => 'municipal.rate'],
    ): IssCalculationResult {
        $amountValue = $facts->value('operation.amount');
        if (! is_string($amountValue)) {
            return new IssCalculationResult(false, null, null, null, $rate->value(), null, ['OPERATION_AMOUNT_MISSING'], ['operation.amount'], true);
        }

        $gross = DecimalValue::fromString($amountValue);
        if ($gross->isNegative()) {
            return new IssCalculationResult(false, $gross->value(), null, null, $rate->value(), null, ['OPERATION_AMOUNT_NEGATIVE']);
        }

        $deduction = DecimalValue::fromString('0');
        $deductionValue = $facts->value('operation.allowed_deduction');
        if ($deductionValue !== null) {
            if ($family !== ServiceFamily::CONSTRUCTION) {
                return new IssCalculationResult(false, $gross->value(), (string) $deductionValue, null, $rate->value(), null, ['DEDUCTION_NOT_SUPPORTED_FOR_SERVICE']);
            }
            $deduction = DecimalValue::fromString((string) $deductionValue);
        }

        if ($deduction->isNegative() || $deduction->compareTo($gross) > 0) {
            return new IssCalculationResult(false, $gross->value(), $deduction->value(), null, $rate->value(), null, ['DEDUCTION_OUT_OF_RANGE']);
        }
        if ($deduction->compareTo(DecimalValue::fromString('0')) > 0) {
            $materialsValue = $facts->value('operation.materials_amount');
            $requiredSupport = [
                'operation.materials_amount',
                'operation.deduction_evidence_id',
                'operation.deduction_municipal_rule_id',
                'operation.materials_produced_offsite',
                'operation.materials_separate_sale',
                'operation.materials_icms_document_id',
                'operation.deduction_validation_status',
            ];
            $missing = $facts->missing($requiredSupport);
            if ($missing !== [] || ! is_string($materialsValue)) {
                return new IssCalculationResult(false, $gross->value(), $deduction->value(), null, $rate->value(), null, ['DEDUCTION_SUPPORT_MISSING'], $missing, true);
            }
            if ($facts->value('operation.materials_produced_offsite') !== true || $facts->value('operation.materials_separate_sale') !== true) {
                return new IssCalculationResult(false, $gross->value(), $deduction->value(), null, $rate->value(), null, ['DEDUCTION_REQUIRES_MANUAL_REVIEW'], [], true);
            }
            if ($facts->value('operation.deduction_validation_status') !== 'APPROVED') {
                return new IssCalculationResult(
                    false,
                    $gross->value(),
                    $deduction->value(),
                    null,
                    $rate->value(),
                    null,
                    [$facts->value('operation.deduction_validation_status') === 'REJECTED' ? 'DEDUCTION_NOT_PERMITTED' : 'DEDUCTION_REQUIRES_MANUAL_REVIEW'],
                    [],
                    $facts->value('operation.deduction_validation_status') !== 'REJECTED',
                );
            }
            $materials = DecimalValue::fromString($materialsValue);
            if ($materials->isNegative() || $deduction->compareTo($materials) > 0) {
                return new IssCalculationResult(false, $gross->value(), $deduction->value(), null, $rate->value(), null, ['DEDUCTION_EXCEEDS_SUPPORTED_MATERIALS']);
            }
        }

        $base = $gross->minus($deduction)->rounded(2);
        $availableArguments = [
            'iss.taxable_base' => $base->value(),
            'municipal.rate' => $rate->value(),
        ];
        $formulaArguments = [];
        foreach ($argumentBindings as $argument => $source) {
            if (! array_key_exists($source, $availableArguments)) {
                return new IssCalculationResult(false, $gross->value(), $deduction->value(), $base->value(), $rate->value(), null, ['FORMULA_ARGUMENT_BINDING_INVALID']);
            }
            $formulaArguments[$argument] = $availableArguments[$source];
        }
        try {
            $tax = $this->formulas->get($formulaId)->calculate($formulaArguments);
        } catch (\InvalidArgumentException) {
            return new IssCalculationResult(false, $gross->value(), $deduction->value(), $base->value(), $rate->value(), null, ['FORMULA_INVALID']);
        }

        return new IssCalculationResult(
            true,
            $gross->rounded(2)->value(),
            $deduction->rounded(2)->value(),
            $base->value(),
            $rate->value(),
            $tax,
            ['ISS_CALCULATED'],
        );
    }
}
