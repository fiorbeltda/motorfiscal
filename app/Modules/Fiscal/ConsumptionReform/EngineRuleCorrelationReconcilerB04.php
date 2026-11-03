<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/**
 * B04: compares each ENGINE_DECISION_RULE with the literal official correlation
 * rows that share its NBS prefix. This is a review report only: convergence does
 * not confirm a rule, divergence does not invalidate it, and neither result
 * changes any rule, correlation or decision. Both kinds stay separate (4B.6.7).
 */
final readonly class EngineRuleCorrelationReconcilerB04
{
    public const VERSION = 'engine-rule-correlation-reconciliation-b04';

    /**
     * @param  array<string, array<string, mixed>>  $rules
     * @param  array<string, list<array<string, mixed>>>  $editions  edition label => literal correlation rows
     * @return array<string, mixed>
     */
    public function reconcile(array $rules, array $editions): array
    {
        foreach ($rules as $id => $rule) {
            if (($rule['kind'] ?? null) !== 'ENGINE_DECISION_RULE') {
                return ['status' => 'INVALID', 'reason' => 'B04_NON_ENGINE_RULE_IN_RULE_SET', 'rule' => $id, ...$this->limits()];
            }
        }
        foreach ($editions as $label => $rows) {
            foreach ($rows as $row) {
                if (($row['kind'] ?? null) !== 'OFFICIAL_CORRELATION' || ($row['decision_authority'] ?? null) !== false) {
                    return ['status' => 'INVALID', 'reason' => 'B04_CORRELATION_AUTHORITY_NOT_PROVISIONAL', 'edition' => $label, ...$this->limits()];
                }
            }
        }
        ksort($rules, SORT_STRING);
        $items = [];
        $totals = [];
        foreach ($rules as $id => $rule) {
            $cindops = $this->cindops($rule);
            $prefixes = array_values($rule['nbs_prefixes'] ?? []);
            $item = ['rule' => $id, 'cclasstrib' => $rule['cclasstrib'], 'cindops' => $cindops, 'nbs_prefixes' => $prefixes, 'editions' => []];
            foreach ($editions as $label => $rows) {
                $inPrefix = $prefixes === [] ? [] : array_values(array_filter($rows, function (array $row) use ($prefixes): bool {
                    foreach ($prefixes as $prefix) {
                        if (str_starts_with((string) $row['nbs'], (string) $prefix)) {
                            return true;
                        }
                    }

                    return false;
                }));
                $both = $class = $ind = 0;
                foreach ($inPrefix as $row) {
                    $c = $row['cclasstrib'] === $rule['cclasstrib'];
                    $i = in_array($row['cindop'], $cindops, true);
                    $both += (int) ($c && $i);
                    $class += (int) ($c && ! $i);
                    $ind += (int) ($i && ! $c);
                }
                $status = match (true) {
                    $prefixes === [] => 'NOT_COMPARABLE_NO_NBS_PREFIX',
                    $inPrefix === [] => 'NO_OFFICIAL_ROW_IN_PREFIX',
                    $both > 0 => 'CONVERGENT_LITERAL_ROW_EXISTS',
                    default => 'DIVERGENT_NO_LITERAL_MATCH',
                };
                $item['editions'][$label] = ['status' => $status, 'rows_in_prefix' => count($inPrefix),
                    'matching_rows' => $both, 'cclasstrib_only_rows' => $class, 'cindop_only_rows' => $ind];
                $totals[$label][$status] = ($totals[$label][$status] ?? 0) + 1;
            }
            $items[] = $item;
        }
        foreach ($totals as &$counts) {
            ksort($counts, SORT_STRING);
        }
        unset($counts);

        $report = ['version' => self::VERSION, 'rules' => count($rules), 'editions' => array_keys($editions),
            'totals' => $totals, 'items' => $items];

        return ['status' => 'REPORTED', 'reason' => 'B04_REVIEW_REPORT_ONLY', 'report' => $report,
            'report_hash' => CanonicalJson::hash($report), ...$this->limits()];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<string>
     */
    private function cindops(array $rule): array
    {
        $values = isset($rule['cindop']) ? [(string) $rule['cindop']] : [];
        foreach (($rule['cindop_by_presence'] ?? []) as $value) {
            $values[] = (string) $value;
        }
        $values = array_values(array_unique($values));
        sort($values, SORT_STRING);

        return $values;
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['decision_authority' => false, 'rules_changed' => false, 'correlations_changed' => false,
            'rule_confirmed_by_convergence' => false, 'rule_invalidated_by_divergence' => false, 'production_ready' => false];
    }
}
