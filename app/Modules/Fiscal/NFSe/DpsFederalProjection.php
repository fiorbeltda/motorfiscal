<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\DecimalValue;

/** Maps withholding decisions without reusing withheld PIS/Cofins as own assessed tax. */
final readonly class DpsFederalProjection
{
    /**
     * @param  array<string, mixed>  $federal
     * @param  array<string, mixed>  $identity
     * @return array{status: string, reason: string, missing_fields: list<string>, fields: array<string, string>}
     */
    public function project(array $federal, array $identity): array
    {
        if (($federal['status'] ?? null) === 'NOT_REQUESTED') {
            return $this->result('PROJECTED', 'DPS_FEDERAL_NOT_REQUESTED');
        }
        $cst = $identity['pis_cofins_cst'] ?? null;
        if (! is_string($cst) || ! preg_match('/^[0-9]{2}$/', $cst)
            || ! is_string($identity['pis_cofins_evidence_id'] ?? null) || trim($identity['pis_cofins_evidence_id']) === '') {
            return $this->result('ACTION_REQUIRED', 'DPS_PIS_COFINS_OWN_CLASSIFICATION_REQUIRED',
                ['pis_cofins_cst', 'pis_cofins_evidence_id']);
        }
        $fields = ['piscofins.CST' => $cst];
        if (! is_array($federal['pcc']['decisions'] ?? null)
            || ! is_array($federal['irrf'] ?? null) || ! is_array($federal['inss'] ?? null)
            || ! is_array($federal['additional_decisions'] ?? [])) {
            return $this->result('ACTION_REQUIRED', 'DPS_FEDERAL_WITHHOLDING_NOT_FINAL');
        }
        $decisions = [
            [...$federal['irrf'], 'tax' => 'IRRF'], [...$federal['inss'], 'tax' => 'INSS'],
            ...$federal['pcc']['decisions'], ...($federal['additional_decisions'] ?? []),
        ];
        $mask = 0;
        $amounts = [];
        $total = DecimalValue::fromString('0');
        foreach ($decisions as $tax) {
            if (! is_array($tax) || ! in_array($tax['tax'] ?? null, ['PIS', 'COFINS', 'CSLL', 'IRRF', 'INSS'], true)
                || ! $this->finalStatus($tax['status'] ?? null)) {
                return $this->result('ACTION_REQUIRED', 'DPS_FEDERAL_WITHHOLDING_NOT_FINAL');
            }
            $bit = match ($tax['tax'] ?? null) {
                'PIS' => 1,
                'COFINS' => 2,
                'CSLL' => 4,
                default => 0,
            };
            if (($tax['status'] ?? null) === 'WITHHOLD') {
                $mask |= $bit;
                if (! $this->amount($tax['amount'] ?? null)) {
                    return $this->result('INVALID', 'DPS_FEDERAL_AMOUNT_INVALID');
                }
                $amount = DecimalValue::fromString($tax['amount']);
                $total = $total->plus($amount);
                $amounts[$tax['tax']] = ($amounts[$tax['tax']] ?? DecimalValue::fromString('0'))->plus($amount);
            }
        }
        if (isset($federal['total_withholding'])
            && (! $this->amount($federal['total_withholding'])
                || $total->compareTo(DecimalValue::fromString($federal['total_withholding'])) !== 0)) {
            return $this->result('INVALID', 'DPS_FEDERAL_TOTAL_RECONCILIATION_FAILED');
        }
        $fields['piscofins.tpRetPisCofins'] = [0 => '0', 1 => '5', 2 => '6', 3 => '4',
            4 => '8', 5 => '9', 6 => '7', 7 => '3'][$mask];
        foreach (['INSS' => 'vRetCP', 'IRRF' => 'vRetIRRF', 'CSLL' => 'vRetCSLL'] as $tax => $field) {
            if (isset($amounts[$tax])) {
                $fields[$field] = $amounts[$tax]->rounded(2)->value();
            }
        }

        return $this->result('PROJECTED', 'DPS_FEDERAL_WITHHOLDINGS_PROJECTED', [], $fields);
    }

    private function finalStatus(mixed $status): bool
    {
        return in_array($status, ['WITHHOLD', 'NOT_APPLICABLE', 'EXEMPT', 'IMMUNE', 'ZERO_RATE',
            'SUSPENDED', 'DISPENSED_THRESHOLD'], true);
    }

    private function amount(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9]{1,15}\.[0-9]{2}$/', $value) === 1;
    }

    /**
     * @param  list<string>  $missing
     * @param  array<string, string>  $fields
     * @return array{status: string, reason: string, missing_fields: list<string>, fields: array<string, string>}
     */
    private function result(string $status, string $reason, array $missing = [], array $fields = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_fields' => $missing, 'fields' => $fields];
    }
}
