<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final class InssTaxBaseEngine
{
    private const MINIMUM_BASE_RATES = [
        'PASSENGER_TRANSPORT' => '30.00',
        'HOSPITAL_CLEANING' => '65.00',
        'OTHER_CLEANING' => '80.00',
        'OTHER' => '50.00',
        'GENERAL_WITH_INHERENT_EQUIPMENT' => '50.00',
        'CONSTRUCTION_ASPHALT_PAVING' => '10.00',
        'CONSTRUCTION_EARTHWORK_LANDFILL_DREDGING' => '15.00',
        'CONSTRUCTION_BRIDGES_VIADUCTS' => '45.00',
        'CONSTRUCTION_DRAINAGE' => '50.00',
        'CONSTRUCTION_OTHER_NON_MANUAL_EQUIPMENT' => '35.00',
    ];

    public function calculate(FactBag $facts): InssTaxBaseDecision
    {
        $missing = $facts->missing(['invoice.gross_amount', 'inss.material_equipment_profile']);
        if ($missing !== []) {
            return new InssTaxBaseDecision('ACTION_REQUIRED', null, null, null, null, ['INSS_BASE_FACTS_MISSING'], $missing);
        }

        $gross = DecimalValue::fromString((string) $facts->value('invoice.gross_amount'));
        if ($gross->isNegative()) {
            return new InssTaxBaseDecision('ACTION_REQUIRED', $gross->value(), null, null, null, ['INSS_INVOICE_AMOUNT_INVALID']);
        }

        $profile = (string) $facts->value('inss.material_equipment_profile');
        $exclusion = DecimalValue::fromString('0');
        $reasonCodes = [];

        if ($profile === 'VALUES_IN_CONTRACT_AND_INVOICE') {
            $missing = $facts->missing(['inss.material_equipment_amount', 'inss.material_equipment_evidence_confirmed']);
            if ($missing !== []) {
                return $this->actionRequired($gross, ['INSS_MATERIAL_EQUIPMENT_EVIDENCE_MISSING'], $missing);
            }
            if ($facts->value('inss.material_equipment_evidence_confirmed') !== true) {
                return $this->actionRequired($gross, ['INSS_MATERIAL_EQUIPMENT_EVIDENCE_NOT_CONFIRMED']);
            }
            $exclusion = DecimalValue::fromString((string) $facts->value('inss.material_equipment_amount'));
            $reasonCodes[] = 'INSS_PROVEN_MATERIAL_EQUIPMENT_EXCLUSION';
        } elseif (in_array($profile, ['CONTRACT_WITHOUT_VALUES', 'INHERENT_EQUIPMENT_WITHOUT_VALUES'], true)) {
            $missing = $facts->missing([
                'inss.material_equipment_amount',
                'inss.material_equipment_evidence_confirmed',
                'inss.minimum_base_category',
            ]);
            if ($missing !== []) {
                return $this->actionRequired($gross, ['INSS_MINIMUM_BASE_FACTS_MISSING'], $missing);
            }
            if ($facts->value('inss.material_equipment_evidence_confirmed') !== true) {
                return $this->actionRequired($gross, ['INSS_MATERIAL_EQUIPMENT_EVIDENCE_NOT_CONFIRMED']);
            }
            $category = (string) $facts->value('inss.minimum_base_category');
            $minimumRate = self::MINIMUM_BASE_RATES[$category] ?? null;
            $allowedCategories = $profile === 'CONTRACT_WITHOUT_VALUES'
                ? ['PASSENGER_TRANSPORT', 'HOSPITAL_CLEANING', 'OTHER_CLEANING', 'OTHER']
                : [
                    'GENERAL_WITH_INHERENT_EQUIPMENT',
                    'CONSTRUCTION_ASPHALT_PAVING',
                    'CONSTRUCTION_EARTHWORK_LANDFILL_DREDGING',
                    'CONSTRUCTION_BRIDGES_VIADUCTS',
                    'CONSTRUCTION_DRAINAGE',
                    'CONSTRUCTION_OTHER_NON_MANUAL_EQUIPMENT',
                ];
            if ($minimumRate === null || ! in_array($category, $allowedCategories, true)) {
                return $this->actionRequired($gross, ['INSS_MINIMUM_BASE_CATEGORY_CONFLICT']);
            }
            $claimedExclusion = DecimalValue::fromString((string) $facts->value('inss.material_equipment_amount'));
            if ($claimedExclusion->isNegative() || $claimedExclusion->compareTo($gross) > 0) {
                return $this->actionRequired($gross, ['INSS_MATERIAL_EQUIPMENT_AMOUNT_INVALID']);
            }
            $claimedBase = $gross->minus($claimedExclusion);
            $minimumBase = $gross
                ->multipliedBy(DecimalValue::fromString($minimumRate))
                ->dividedBy(DecimalValue::fromString('100'), 8)
                ->rounded(2);
            $baseBeforeOtherDeductions = $claimedBase->compareTo($minimumBase) < 0 ? $minimumBase : $claimedBase;
            $exclusion = $gross->minus($baseBeforeOtherDeductions);
            $reasonCodes[] = 'INSS_STATUTORY_MINIMUM_BASE_APPLIED';
        } elseif (in_array($profile, ['NONE', 'NOT_DISCRIMINATED_IN_INVOICE', 'NO_CONTRACT_PROVISION_NOT_INHERENT'], true)) {
            $reasonCodes[] = $profile === 'NONE'
                ? 'INSS_GROSS_BASE_NO_MATERIAL_EQUIPMENT_EXCLUSION'
                : 'INSS_GROSS_BASE_EXCLUSION_NOT_ALLOWED';
        } else {
            return $this->actionRequired($gross, ['INSS_MATERIAL_EQUIPMENT_PROFILE_INVALID']);
        }

        if ($exclusion->isNegative() || $exclusion->compareTo($gross) > 0) {
            return $this->actionRequired($gross, ['INSS_MATERIAL_EQUIPMENT_AMOUNT_INVALID']);
        }

        $otherDeductions = $facts->has('inss.allowed_base_deductions')
            ? DecimalValue::fromString((string) $facts->value('inss.allowed_base_deductions'))
            : DecimalValue::fromString('0');
        $baseBeforeOtherDeductions = $gross->minus($exclusion);
        if ($otherDeductions->isNegative() || $otherDeductions->compareTo($baseBeforeOtherDeductions) > 0) {
            return $this->actionRequired($gross, ['INSS_BASE_DEDUCTION_INVALID']);
        }
        if ($otherDeductions->compareTo(DecimalValue::fromString('0')) > 0
            && $facts->value('inss.deduction_evidence_confirmed') !== true) {
            return $this->actionRequired($gross, ['INSS_DEDUCTION_EVIDENCE_REQUIRED'], ['inss.deduction_evidence_confirmed']);
        }

        if ($otherDeductions->compareTo(DecimalValue::fromString('0')) > 0) {
            $reasonCodes[] = 'INSS_OTHER_DOCUMENTED_DEDUCTIONS_APPLIED';
        }

        return new InssTaxBaseDecision(
            'RESOLVED',
            $gross->rounded(2)->value(),
            $exclusion->rounded(2)->value(),
            $otherDeductions->rounded(2)->value(),
            $baseBeforeOtherDeductions->minus($otherDeductions)->rounded(2)->value(),
            $reasonCodes,
        );
    }

    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $missingFacts
     */
    private function actionRequired(DecimalValue $gross, array $reasonCodes, array $missingFacts = []): InssTaxBaseDecision
    {
        return new InssTaxBaseDecision(
            'ACTION_REQUIRED',
            $gross->rounded(2)->value(),
            null,
            null,
            null,
            $reasonCodes,
            $missingFacts,
        );
    }
}
