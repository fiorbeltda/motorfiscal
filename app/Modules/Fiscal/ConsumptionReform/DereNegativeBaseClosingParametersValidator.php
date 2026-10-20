<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

/** Validates local D1199 parameters; does not prove D9199 origin, stock, expiry or consumption. */
final readonly class DereNegativeBaseClosingParametersValidator
{
    public const CONTRACT = 'DERE-BCN-CLOSING-PARAMETERS-1.3.0-49';

    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function validate(array $parameters, string $period): array
    {
        if (! $this->period($period)) {
            return $this->stop('PERIOD_INVALID');
        }
        if (array_diff(array_keys($parameters), ['infoBCN', 'detBCNeg']) !== []
            || ! is_array($parameters['infoBCN'] ?? null) || ! array_is_list($parameters['infoBCN'])
            || count($parameters['infoBCN']) < 1 || count($parameters['infoBCN']) > 99
            || (array_key_exists('detBCNeg', $parameters) && (! is_array($parameters['detBCNeg'])
                || ! array_is_list($parameters['detBCNeg']) || count($parameters['detBCNeg']) > 99))) {
            return $this->stop('GROUP_SHAPE_INVALID');
        }
        $roots = [];
        foreach ($parameters['infoBCN'] as $row) {
            if (! is_array($row) || array_diff(array_keys($row), ['codBCNRaiz', 'usarBCNAcum', 'metodoAproveit']) !== []
                || ! is_string($row['codBCNRaiz'] ?? null)
                || ! in_array($row['codBCNRaiz'], ['1005I', '1005C', '1010I', '1010C'], true)) {
                return $this->stop('FINANCIAL_ROOT_UNSUPPORTED_OR_INVALID');
            }
            $root = $row['codBCNRaiz'];
            if (array_key_exists($root, $roots)) {
                return $this->stop('DUPLICATE_ROOT');
            }
            if (! in_array($row['usarBCNAcum'] ?? null, ['0', '1'], true)
                || ($row['usarBCNAcum'] === '0' && array_key_exists('metodoAproveit', $row))
                || ($row['usarBCNAcum'] === '1' && ! in_array($row['metodoAproveit'] ?? null, ['0', '1'], true))) {
                return $this->stop('METHOD_CONFLICT');
            }
            $roots[$root] = $row['usarBCNAcum'] === '1' ? $row['metodoAproveit'] : null;
        }
        $seen = [];
        $manualRoots = [];
        foreach ($parameters['detBCNeg'] ?? [] as $row) {
            if (! is_array($row) || array_diff(array_keys($row), ['codBCN', 'vUsarBCN']) !== []
                || ! is_string($row['codBCN'] ?? null) || ! preg_match('/^[1-9][0-9]{3}[IC][0-9]{8}$/', $row['codBCN'])) {
                return $this->stop('ORIGIN_KEY_INVALID');
            }
            $key = $row['codBCN'];
            $root = substr($key, 0, 5);
            $origin = substr($key, 5, 4).'-'.substr($key, 9, 2);
            if (! $this->period($origin) || $origin >= $period) {
                return $this->stop('ORIGIN_PERIOD_INVALID_OR_NOT_PRIOR');
            }
            if (($roots[$root] ?? null) !== '1') {
                return $this->stop('DETAIL_WITHOUT_MANUAL_ROOT');
            }
            if (isset($seen[$key])) {
                return $this->stop('DUPLICATE_ORIGIN_KEY');
            }
            if (array_key_exists('vUsarBCN', $row) && (! is_string($row['vUsarBCN'])
                || ! preg_match('/^(0|[1-9][0-9]{0,14})\.[0-9]{2}$/', $row['vUsarBCN']))) {
                return $this->stop('MANUAL_CAP_INVALID');
            }
            $seen[$key] = true;
            $manualRoots[$root] = true;
        }
        foreach ($roots as $root => $method) {
            if ($method === '1' && ! isset($manualRoots[$root])) {
                return $this->stop('MANUAL_ROOT_WITHOUT_DETAIL');
            }
        }

        return ['status' => 'PARAMETERS_VALIDATED', 'contract' => self::CONTRACT, 'period' => $period,
            'parameters' => $parameters, 'stock_qualified' => false, 'consumption_calculated' => false,
            'production_ready' => false];
    }

    private function period(string $period): bool
    {
        return preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/', $period) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_PARAMETERS_'.$reason,
            'stock_qualified' => false, 'consumption_calculated' => false, 'production_ready' => false];
    }
}
