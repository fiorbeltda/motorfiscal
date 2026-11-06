<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

/**
 * B31: rounding and calculator reconciliation for the NFS-e (NT009 Anexo VI, RN DPS):
 * - vBC: until 2026 it deducts ISSQN, PIS and Cofins; from 2027 to 2032 only ISSQN (RN for vBC). Legacy exclusions
 *   outside that set are refused; ICMS keeps the LC 214 art. 12 §2, V window (not an NFS-e tax).
 * - vIBSUF/vIBSMun/vIBSTot/vCBS must match the official Calculadora within R$ 0.01 (E1568, E1572, E1556, E1582).
 * The calculator result is a declared input (never fetched or authenticated here).
 */
final readonly class CalculatorReconciliationB31
{
    public const KEY = 'calculator_reconciliation_b31';

    public const POLICY = ['version' => 'B31', 'tolerance_brl' => '0.01', 'engine_rounding' => 'HALF_UP_2',
        'fields' => ['vIBSUF' => 'E1568', 'vIBSMun' => 'E1572', 'vIBSTot' => 'E1556', 'vCBS' => 'E1582'],
        'legacy_vbc' => ['2026' => ['ISS', 'PIS', 'COFINS', 'ICMS'], '2027-2032' => ['ISS', 'ICMS']]];

    /** @param array<string, mixed> $codes */
    public static function enabled(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null)
            && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY);
    }

    /** @return list<string> */
    public static function legacyAllowed(string $date): array
    {
        return match (true) {
            $date < '2027-01-01' => self::POLICY['legacy_vbc']['2026'],
            $date < '2033-01-01' => self::POLICY['legacy_vbc']['2027-2032'],
            default => [],
        };
    }

    /**
     * @param  array{vIBSUF: string, vIBSMun: string, vIBSTot: string, vCBS: string}  $engine
     * @return array<string, mixed>
     */
    public function reconcile(FactBag $facts, array $engine): array
    {
        if (! $facts->has('rtc.official_calculator_result')) {
            return ['status' => 'RESOLVED', 'reason' => 'RTC_CALCULATOR_NOT_SUPPLIED', 'missing_facts' => [],
                'calculator_checked' => false, 'engine_rounding' => self::POLICY['engine_rounding']];
        }
        $rows = $facts->value('rtc.official_calculator_result');
        $row = is_array($rows) && array_is_list($rows) && count($rows) === 1 && is_array($rows[0]) ? $rows[0] : null;
        $evidence = new CreditEvidence156;
        if ($row === null || ($row['source'] ?? null) !== 'CALCULADORA_RTC' || ! $evidence->reference($row['version'] ?? null)
            || ! $evidence->reference($row['evidence_id'] ?? null)) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_CALCULATOR_RESULT_INVALID', 'missing_facts' => ['rtc.official_calculator_result']];
        }
        $tolerance = DecimalValue::fromString(self::POLICY['tolerance_brl']);
        $fields = [];
        $rejections = [];
        foreach (self::POLICY['fields'] as $field => $code) {
            if (! $evidence->money($row[$field] ?? null)) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_CALCULATOR_RESULT_INVALID', 'missing_facts' => ['rtc.official_calculator_result']];
            }
            $difference = DecimalValue::fromString($engine[$field])->minus(DecimalValue::fromString($row[$field]));
            $absolute = $difference->isNegative() ? DecimalValue::fromString('0')->minus($difference) : $difference;
            $within = $absolute->compareTo($tolerance) <= 0;
            $fields[$field] = ['engine' => $engine[$field], 'calculator' => $row[$field],
                'difference' => $difference->rounded(2)->value(), 'within_tolerance' => $within];
            if (! $within) {
                $rejections[] = $code;
            }
        }
        if ($rejections !== []) {
            return ['status' => 'INVALID', 'reason' => 'RTC_CALCULATOR_DIVERGENCE_ABOVE_TOLERANCE', 'missing_facts' => [],
                'rejection_codes' => $rejections, 'fields' => $fields];
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_CALCULATOR_RECONCILED', 'missing_facts' => [], 'calculator_checked' => true,
            'calculator_version' => $row['version'], 'evidence_id' => $row['evidence_id'], 'tolerance_brl' => self::POLICY['tolerance_brl'],
            'engine_rounding' => self::POLICY['engine_rounding'], 'fields' => $fields, 'authenticated' => false];
    }
}
