<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;

final readonly class ConstructionPrevidenciaryProfile
{
    /** @return array{status: string, reason: string, missing: list<string>, details: array<string, mixed>} */
    public function classify(FactBag $facts): array
    {
        $mode = $facts->value('inss.execution_mode');
        if (! in_array($mode, ['TOTAL_CONSTRUCTION', 'PARTIAL_CONSTRUCTION', 'SUBCONTRACT'], true)) {
            return $this->result('NOT_APPLICABLE', 'CONSTRUCTION_PROFILE_NOT_REQUIRED');
        }
        $missing = $facts->missing(['construction.previdenciary_object_type', 'construction.cno_required']);
        if ($missing !== []) {
            return $this->result('ACTION_REQUIRED', 'CONSTRUCTION_CLASSIFICATION_MISSING', $missing);
        }
        if ($facts->value('construction.previdenciary_object_type') === 'ISOLATED_SERVICE') {
            if ($facts->value('construction.cno_required') === true) {
                return $this->result('ACTION_REQUIRED', 'CONSTRUCTION_ISOLATED_SERVICE_CNO_CONFLICT');
            }

            return $this->result('RESOLVED', 'CONSTRUCTION_ISOLATED_SERVICE_NO_OWN_CNO');
        }
        if ($facts->hasDefinition('construction.total_contract_qualified')) {
            if ($mode === 'TOTAL_CONSTRUCTION') {
                $missing = $facts->missing(['construction.total_contract_qualified', 'construction.total_scope_confirmed']);
                if ($missing !== []) {
                    return $this->result('ACTION_REQUIRED', 'CONSTRUCTION_TOTAL_CONTRACT_EVIDENCE_MISSING', $missing);
                }
                if ($facts->value('construction.total_contract_qualified') !== true
                    || $facts->value('construction.total_scope_confirmed') !== true) {
                    return $this->result('ACTION_REQUIRED', 'CONSTRUCTION_TOTAL_CONTRACT_NOT_QUALIFIED');
                }
            }
            if ($mode === 'SUBCONTRACT') {
                $missing = $facts->missing(['construction.parent_contract_reference', 'construction.parent_work_reference']);
                if ($missing !== []) {
                    return $this->result('ACTION_REQUIRED', 'CONSTRUCTION_SUBCONTRACT_PARENT_MISSING', $missing);
                }
            }
            if (in_array($mode, ['PARTIAL_CONSTRUCTION', 'SUBCONTRACT'], true)) {
                $missing = $facts->missing(['construction.work_responsible_party']);
                if ($missing !== []) {
                    return $this->result('ACTION_REQUIRED', 'CONSTRUCTION_WORK_RESPONSIBLE_PARTY_MISSING', $missing);
                }
            }
        }
        if ($facts->value('construction.cno_required') === false) {
            $missing = $facts->missing(['construction.cno_exemption_legal_basis']);

            return $missing === []
                ? $this->result('RESOLVED', 'CONSTRUCTION_CNO_STATUTORY_EXEMPTION', [], ['cno_exemption_legal_basis' => $facts->value('construction.cno_exemption_legal_basis')])
                : $this->result('ACTION_REQUIRED', 'CONSTRUCTION_CNO_EXEMPTION_BASIS_MISSING', $missing);
        }
        $missing = $facts->missing(['construction.cno', 'construction.cno_holder']);
        if ($missing !== []) {
            return $this->result('ACTION_REQUIRED', 'CONSTRUCTION_CNO_EVIDENCE_MISSING', $missing);
        }
        $expectedHolder = $mode === 'TOTAL_CONSTRUCTION' ? 'PROVIDER'
            : ($facts->hasDefinition('construction.work_responsible_party')
                ? $facts->value('construction.work_responsible_party') : 'CONTRACTING_PARTY');
        if ($facts->value('construction.cno_holder') !== $expectedHolder) {
            return $this->result('ACTION_REQUIRED', 'CONSTRUCTION_CNO_HOLDER_CONFLICT');
        }

        return $this->result('RESOLVED', 'CONSTRUCTION_CNO_IDENTIFIED', [], [
            'cno' => $facts->value('construction.cno'),
            'cno_holder' => $expectedHolder,
            'execution_mode' => $mode,
        ]);
    }

    /**
     * @param  list<string>  $missing
     * @param  array<string, mixed>  $details
     * @return array{status: string, reason: string, missing: list<string>, details: array<string, mixed>}
     */
    private function result(string $status, string $reason, array $missing = [], array $details = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing' => $missing, 'details' => $details];
    }
}
