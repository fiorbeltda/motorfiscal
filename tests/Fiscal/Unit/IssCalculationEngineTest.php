<?php

declare(strict_types=1);

use App\Modules\Fiscal\Classification\ServiceFamily;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Formula\FormulaRegistry;
use App\Modules\Fiscal\Core\Formula\PercentageOfBaseFormula;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\ISS\IssCalculationEngine;

function calculationFacts(string $amount, ?string $deduction = null, string $validationStatus = 'APPROVED'): FactBag
{
    $definitions = new FactDefinitionRegistry([
        new FactDefinition('operation.amount', FactValueType::DECIMAL),
        new FactDefinition('operation.allowed_deduction', FactValueType::DECIMAL, false),
        new FactDefinition('operation.materials_amount', FactValueType::DECIMAL),
        new FactDefinition('operation.deduction_evidence_id', FactValueType::STRING),
        new FactDefinition('operation.deduction_municipal_rule_id', FactValueType::STRING),
        new FactDefinition('operation.materials_produced_offsite', FactValueType::BOOLEAN),
        new FactDefinition('operation.materials_separate_sale', FactValueType::BOOLEAN),
        new FactDefinition('operation.materials_icms_document_id', FactValueType::STRING),
        new FactDefinition('operation.deduction_validation_status', FactValueType::ENUM, allowedValues: ['APPROVED', 'REJECTED', 'REVIEW_REQUIRED']),
    ]);
    $facts = new FactBag($definitions);
    $date = LocalDate::fromString('2026-09-15');
    $recorded = new DateTimeImmutable('2026-09-16T12:00:00-03:00');
    $facts->put(new FactValue('operation.amount', $amount, FactSourceType::FIXTURE, $date, null, $recorded));
    if ($deduction !== null) {
        $facts->put(new FactValue('operation.allowed_deduction', $deduction, FactSourceType::FIXTURE, $date, null, $recorded));
        $facts->put(new FactValue('operation.materials_amount', $deduction, FactSourceType::FIXTURE, $date, null, $recorded));
        $facts->put(new FactValue('operation.deduction_evidence_id', 'EVIDENCE-TEST', FactSourceType::FIXTURE, $date, null, $recorded));
        $facts->put(new FactValue('operation.deduction_municipal_rule_id', 'MUNICIPAL-RULE-TEST', FactSourceType::FIXTURE, $date, null, $recorded));
        $facts->put(new FactValue('operation.materials_produced_offsite', true, FactSourceType::FIXTURE, $date, null, $recorded));
        $facts->put(new FactValue('operation.materials_separate_sale', true, FactSourceType::FIXTURE, $date, null, $recorded));
        $facts->put(new FactValue('operation.materials_icms_document_id', 'NF-ICMS-TEST', FactSourceType::FIXTURE, $date, null, $recorded));
        $facts->put(new FactValue('operation.deduction_validation_status', $validationStatus, FactSourceType::FIXTURE, $date, null, $recorded));
    }

    return $facts;
}

it('calculates a construction base with decimal precision', function (): void {
    $result = (new IssCalculationEngine(new FormulaRegistry([new PercentageOfBaseFormula])))->calculate(
        ServiceFamily::CONSTRUCTION,
        calculationFacts('10000.00', '1250.25'),
        DecimalValue::fromString('5.00'),
        'PERCENTAGE_OF_BASE',
    );

    expect($result->valid)->toBeTrue()
        ->and($result->taxableBase)->toBe('8749.75')
        ->and($result->taxAmount)->toBe('437.49');
});

it('never applies a construction deduction to other families', function (): void {
    $result = (new IssCalculationEngine(new FormulaRegistry([new PercentageOfBaseFormula])))->calculate(
        ServiceFamily::SOFTWARE,
        calculationFacts('100.00', '10.00'),
        DecimalValue::fromString('2.00'),
        'PERCENTAGE_OF_BASE',
    );

    expect($result->valid)->toBeFalse()
        ->and($result->reasonCodes)->toContain('DEDUCTION_NOT_SUPPORTED_FOR_SERVICE');
});

it('requires review instead of auto-deducting unapproved construction materials', function (): void {
    $result = (new IssCalculationEngine(new FormulaRegistry([new PercentageOfBaseFormula])))->calculate(
        ServiceFamily::CONSTRUCTION,
        calculationFacts('100.00', '10.00', 'REVIEW_REQUIRED'),
        DecimalValue::fromString('5.00'),
        'PERCENTAGE_OF_BASE',
    );

    expect($result->valid)->toBeFalse()
        ->and($result->actionRequired)->toBeTrue()
        ->and($result->reasonCodes)->toContain('DEDUCTION_REQUIRES_MANUAL_REVIEW');
});
