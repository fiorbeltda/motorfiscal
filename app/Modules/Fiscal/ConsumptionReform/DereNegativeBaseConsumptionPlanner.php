<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;

/** Local arithmetic over reconciled prior stock; no reception, expiry-boundary or posting approval. */
final readonly class DereNegativeBaseConsumptionPlanner
{
    /** @param array<string, mixed> $priorReturn
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $positiveBases
     * @return array<string, mixed>
     */
    public function plan(array $priorReturn, array $parameters, array $positiveBases, string $supplier, string $period): array
    {
        $validated = (new DereNegativeBaseClosingParametersValidator)->validate($parameters, $period);
        if ($validated['status'] !== 'PARAMETERS_VALIDATED') {
            return $validated;
        }
        $previous = $priorReturn['period'] ?? null;
        if (! is_string($previous) || ! preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $previous)
            || (new DateTimeImmutable($previous.'-01'))->modify('+1 month')->format('Y-m') !== $period) {
            return $this->stop('PRIOR_MONTH_CHAIN_REQUIRED');
        }
        $reconciled = (new DereNegativeBaseReturnReconciler)->reconcile($priorReturn, $supplier, $previous);
        if ($reconciled['status'] !== 'LOCAL_RETURN_RECONCILED') {
            return $reconciled;
        }
        $stock = $reconciled['stock_after_return'];
        $roots = array_column($priorReturn['groups'], null, 'codBCNRaiz');
        foreach ($positiveBases as $root => $value) {
            if (! is_string($root) || ! isset($roots[$root]) || ! $this->money($value)) {
                return $this->stop('CURRENT_POSITIVE_BASE_SCOPE_OR_AMOUNT_INVALID');
            }
        }
        $zero = DecimalValue::fromString('0.00');
        $plans = $warnings = [];
        foreach ($parameters['infoBCN'] as $option) {
            $root = $option['codBCNRaiz'];
            if (! isset($roots[$root]) || ! array_key_exists($root, $positiveBases)) {
                return $this->stop('ROOT_STOCK_OR_CURRENT_BASE_MISSING');
            }
            $base = DecimalValue::fromString($positiveBases[$root]);
            $total = $zero;
            $allocations = [];
            if ($option['usarBCNAcum'] === '1') {
                $available = array_values(array_filter($stock, static fn (array $row): bool => $row['codBCNRaiz'] === $root));
                $seenPeriods = [];
                foreach ($available as $row) {
                    if (isset($seenPeriods[$row['perOrigem']])) {
                        return $this->stop('ORIGIN_VERSION_RECONCILIATION_REQUIRED');
                    }
                    $seenPeriods[$row['perOrigem']] = true;
                }
                $byKey = array_column($available, null, 'codBCN');
                if ($option['metodoAproveit'] === '0') {
                    usort($available, static fn (array $left, array $right): int => $left['perOrigem'] <=> $right['perOrigem']);
                    $requests = array_map(static fn (array $row): array => ['codBCN' => $row['codBCN']], $available);
                } else {
                    $requests = array_values(array_filter($parameters['detBCNeg'], static fn (array $row): bool => substr($row['codBCN'], 0, 5) === $root));
                }
                $requested = $zero;
                $candidates = [];
                foreach ($requests as $request) {
                    $key = $request['codBCN'];
                    if (! isset($byKey[$key])) {
                        return $this->stop('REQUESTED_ORIGIN_NOT_IN_PRIOR_STOCK');
                    }
                    $row = $byKey[$key];
                    $balance = DecimalValue::fromString($row['available_after_return']);
                    // Before the anniversary month, even its first possible expiry day is still in the future.
                    $anniversaryMonth = sprintf('%04d%s', (int) substr($row['perOrigem'], 0, 4) + 5, substr($row['perOrigem'], 4));
                    $cap = isset($request['vUsarBCN']) ? DecimalValue::fromString($request['vUsarBCN']) : $balance;
                    if ($cap->compareTo($balance) > 0) {
                        $warnings[] = ['code' => 'AVISO_CONFERIR_SALDO_BCN', 'codBCN' => $key,
                            'requested' => $request['vUsarBCN'], 'available' => $row['available_after_return']];
                        $cap = $balance;
                    }
                    if ($cap->compareTo($zero) > 0 && $base->compareTo($zero) > 0 && $period >= $anniversaryMonth) {
                        return $this->stop('EXPIRY_BOUNDARY_AND_CALENDAR_REQUIRED');
                    }
                    $requested = $requested->plus($cap);
                    $candidates[] = ['row' => $row, 'cap' => $cap];
                }
                $nonzero = array_filter($candidates, static fn (array $candidate): bool => $candidate['cap']->compareTo($zero) > 0);
                if ($option['metodoAproveit'] === '1' && count($nonzero) > 1 && $requested->compareTo($base) > 0) {
                    return $this->stop('MANUAL_ALLOCATION_PRIORITY_REQUIRED');
                }
                foreach ($candidates as $candidate) {
                    $remainingBase = $base->minus($total);
                    $use = $candidate['cap']->compareTo($remainingBase) > 0 ? $remainingBase : $candidate['cap'];
                    $total = $total->plus($use);
                    $row = $candidate['row'];
                    $balance = DecimalValue::fromString($row['available_after_return']);
                    $allocations[] = ['codBCN' => $row['codBCN'], 'opening_available' => $row['available_after_return'],
                        'used' => $use->value(), 'remaining' => $balance->minus($use)->value()];
                }
            }
            $plans[] = ['codBCNRaiz' => $root, 'positive_base' => $base->value(), 'deduction' => $total->value(),
                'assessed_base' => $base->minus($total)->value(), 'allocations' => $allocations];
        }

        return ['status' => 'LOCAL_CONSUMPTION_PLANNED', 'supplier_id' => $supplier, 'period' => $period,
            'prior_return_hash' => CanonicalJson::hash($priorReturn), 'parameters_hash' => CanonicalJson::hash($parameters),
            'current_bases_hash' => CanonicalJson::hash($positiveBases), 'plans' => $plans, 'warnings' => $warnings,
            'received_return_verified' => false, 'expiry_boundary_qualified' => false,
            'eligible_for_posting' => false, 'production_ready' => false];
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(0|[1-9][0-9]{0,14})\.[0-9]{2}$/', $value) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_CONSUMPTION_'.$reason,
            'eligible_for_posting' => false, 'production_ready' => false];
    }
}
