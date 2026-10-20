<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;

final class LaborAssignmentClassifier
{
    public function classify(FactBag $facts): LaborAssignmentDecision
    {
        if (! $facts->has('inss.execution_mode')) {
            return new LaborAssignmentDecision('ACTION_REQUIRED', false, ['inss.execution_mode'], ['INSS_EXECUTION_MODE_MISSING']);
        }
        $mode = $facts->value('inss.execution_mode');
        if (in_array($mode, ['CONTRACT_FOR_RESULT', 'TOTAL_CONSTRUCTION', 'PARTIAL_CONSTRUCTION', 'SUBCONTRACT'], true)) {
            return new LaborAssignmentDecision('CONTRACT_FOR_RESULT', false, [], ['INSS_CONTRACT_FOR_RESULT_CLASSIFIED']);
        }
        if ($mode !== 'LABOR_ASSIGNMENT') {
            return new LaborAssignmentDecision('ACTION_REQUIRED', false, [], ['INSS_EXECUTION_MODE_INVALID']);
        }

        $required = ['labor.work_location', 'labor.continuous_need', 'labor.workers_at_disposal'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return new LaborAssignmentDecision('ACTION_REQUIRED', false, $missing, ['LABOR_ASSIGNMENT_FACTS_MISSING']);
        }
        $locationEligible = in_array(
            $facts->value('labor.work_location'),
            ['CONTRACTING_PARTY_PREMISES', 'THIRD_PARTY_PREMISES_INDICATED_BY_CONTRACTOR'],
            true,
        );
        if ($locationEligible
            && $facts->value('labor.continuous_need') === true
            && $facts->value('labor.workers_at_disposal') === true) {
            return new LaborAssignmentDecision('LABOR_ASSIGNMENT_CONFIRMED', true, [], ['LABOR_ASSIGNMENT_REQUIREMENTS_MET']);
        }

        return new LaborAssignmentDecision('NOT_LABOR_ASSIGNMENT', false, [], ['LABOR_ASSIGNMENT_REQUIREMENTS_NOT_MET']);
    }
}
