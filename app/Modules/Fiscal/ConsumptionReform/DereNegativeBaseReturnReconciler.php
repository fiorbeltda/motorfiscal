<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

/** Reconciles normalized D9199 balances locally; reception, signatures and expiry require separate proof. */
final readonly class DereNegativeBaseReturnReconciler
{
    public const CONTRACT = 'DERE-BCN-RETURN-RECONCILIATION-1.3.0-49';

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function reconcile(array $input, string $supplier, string $period): array
    {
        if (! preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $period)
            || ! preg_match('/^[0-9]{14}$/', $supplier)
            || ($input['supplier_id'] ?? null) !== $supplier || ($input['period'] ?? null) !== $period) {
            return $this->stop('SCOPE_CONFLICT');
        }
        if (! is_array($input['groups'] ?? null) || ! array_is_list($input['groups'])
            || count($input['groups']) < 1 || count($input['groups']) > 4) {
            return $this->stop('GROUPS_INVALID');
        }
        $roots = [];
        $stock = [];
        foreach ($input['groups'] as $group) {
            if (! is_array($group) || ! is_string($group['codBCNRaiz'] ?? null)
                || ! in_array($group['codBCNRaiz'], ['1005I', '1005C', '1010I', '1010C'], true)
                || ($group['codBC'] ?? null) !== substr($group['codBCNRaiz'], 0, 4)
                || isset($roots[$group['codBCNRaiz']])) {
                return $this->stop('ROOT_OR_FORMING_BASE_CONFLICT');
            }
            $root = $group['codBCNRaiz'];
            $roots[$root] = true;
            foreach (['positive_base', 'generated_negative', 'vSaldoAnt', 'vUtilPer', 'vSaldoFinal'] as $field) {
                if (! $this->money($group[$field] ?? null)) {
                    return $this->stop('GROUP_AMOUNT_INVALID');
                }
            }
            $positive = DecimalValue::fromString($group['positive_base']);
            $generated = DecimalValue::fromString($group['generated_negative']);
            $used = DecimalValue::fromString($group['vUtilPer']);
            $zero = DecimalValue::fromString('0.00');
            if (($positive->compareTo($zero) > 0 && $generated->compareTo($zero) > 0)
                || $used->compareTo($positive) > 0) {
                return $this->stop('CURRENT_BASE_OR_CONSUMPTION_CONFLICT');
            }
            if (! is_array($group['detBCN'] ?? null) || ! array_is_list($group['detBCN']) || count($group['detBCN']) > 100) {
                return $this->stop('DETAIL_COVERAGE_INVALID');
            }
            $totalPrevious = $totalUsed = $totalFinal = $totalGenerated = $zero;
            $keys = [];
            $currentKeys = 0;
            foreach ($group['detBCN'] as $row) {
                if (! is_array($row) || ! is_string($row['codBCN'] ?? null)
                    || ! preg_match('/^[1-9][0-9]{3}[IC][0-9]{8}$/', $row['codBCN'])
                    || substr($row['codBCN'], 0, 5) !== $root
                    || ! is_string($row['perOrigem'] ?? null)
                    || ! preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $row['perOrigem'])
                    || str_replace('-', '', $row['perOrigem']) !== substr($row['codBCN'], 5, 6)
                    || $row['perOrigem'] > $period || isset($keys[$row['codBCN']])) {
                    return $this->stop('ORIGIN_IDENTITY_OR_PERIOD_CONFLICT');
                }
                $keys[$row['codBCN']] = true;
                foreach (['vOrigemIni', 'vSaldoAnt', 'vUtilPer', 'vSaldoFinal'] as $field) {
                    if (! $this->money($row[$field] ?? null)) {
                        return $this->stop('DETAIL_AMOUNT_INVALID');
                    }
                }
                $initial = DecimalValue::fromString($row['vOrigemIni']);
                $previous = DecimalValue::fromString($row['vSaldoAnt']);
                $use = DecimalValue::fromString($row['vUtilPer']);
                $final = DecimalValue::fromString($row['vSaldoFinal']);
                if ($initial->compareTo($zero) <= 0 || $previous->compareTo($initial) > 0) {
                    return $this->stop('ORIGIN_BALANCE_EXCEEDED');
                }
                if ($row['perOrigem'] === $period) {
                    $currentKeys++;
                    if ($currentKeys > 1 || $previous->compareTo($zero) !== 0 || $use->compareTo($zero) !== 0
                        || $final->compareTo($initial) !== 0) {
                        return $this->stop('CURRENT_ORIGIN_CONFLICT');
                    }
                    $totalGenerated = $totalGenerated->plus($initial);
                } elseif ($use->compareTo($previous) > 0 || $final->compareTo($previous->minus($use)) !== 0) {
                    return $this->stop('DETAIL_CONSERVATION_CONFLICT');
                }
                $totalPrevious = $totalPrevious->plus($previous);
                $totalUsed = $totalUsed->plus($use);
                $totalFinal = $totalFinal->plus($final);
                $stock[] = ['codBCNRaiz' => $root, 'codBCN' => $row['codBCN'], 'perOrigem' => $row['perOrigem'],
                    'vOrigemIni' => $row['vOrigemIni'], 'available_after_return' => $row['vSaldoFinal']];
            }
            if ($totalPrevious->compareTo(DecimalValue::fromString($group['vSaldoAnt'])) !== 0
                || $totalUsed->compareTo($used) !== 0
                || $totalFinal->compareTo(DecimalValue::fromString($group['vSaldoFinal'])) !== 0
                || $totalGenerated->compareTo($generated) !== 0
                || $totalFinal->compareTo($totalPrevious->plus($generated)->minus($used)) !== 0) {
                return $this->stop('AGGREGATE_CONSERVATION_OR_COVERAGE_CONFLICT');
            }
        }

        return ['status' => 'LOCAL_RETURN_RECONCILED', 'contract' => self::CONTRACT,
            'supplier_id' => $supplier, 'period' => $period, 'normalized_return_hash' => CanonicalJson::hash($input),
            'stock_after_return' => $stock, 'received_return_verified' => false, 'expiry_qualified' => false,
            'eligible_for_consumption' => false, 'production_ready' => false];
    }

    private function money(mixed $amount): bool
    {
        return is_string($amount) && preg_match('/^(0|[1-9][0-9]{0,14})\.[0-9]{2}$/', $amount) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_RETURN_'.$reason,
            'received_return_verified' => false, 'expiry_qualified' => false,
            'eligible_for_consumption' => false, 'production_ready' => false];
    }
}
