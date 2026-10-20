<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Arithmetic foundation; composition, classification and rates require separate source qualification. */
final readonly class DereFinancialCurrentBaseCalculator
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $fields = ['general_revenue', 'transition_revenue', 'other_deductible_expenses', 'allocated_funding_expenses',
            'export_reversal', 'cooperative_reversal', 'public_offer_debt_return'];
        $keys = [...$fields, 'codBC', 'iss_incident', 'synthetic', 'transition_present', 'selective_tax_incident', 'rates'];
        if (array_diff(array_keys($input), $keys) !== [] || array_diff($keys, array_keys($input)) !== []
            || ($input['synthetic'] ?? null) !== true || ! in_array($input['codBC'] ?? null, ['1005', '1010'], true)
            || ($input['iss_incident'] ?? null) !== ($input['codBC'] === '1005')
            || ! is_bool($input['transition_present'] ?? null) || ($input['selective_tax_incident'] ?? null) !== false) {
            return $this->stop('DECLARED_COMPOSITION_SCOPE_REQUIRED');
        }
        foreach ($fields as $field) {
            if (! is_string($input[$field]) || ! preg_match('/^(0|[1-9][0-9]{0,14})\.[0-9]{2}$/', $input[$field])) {
                return $this->stop('CANONICAL_COMPOSITION_AMOUNT_REQUIRED');
            }
        }
        if (! is_array($input['rates']) || array_diff(array_keys($input['rates']), ['IBS_STATE', 'IBS_MUNICIPAL', 'CBS']) !== []
            || array_diff(['IBS_STATE', 'IBS_MUNICIPAL', 'CBS'], array_keys($input['rates'])) !== []) {
            return $this->stop('SEPARATE_RATE_COMPONENTS_REQUIRED');
        }
        $rates = [];
        foreach ($input['rates'] as $tax => $rate) {
            if (! is_string($rate) || ! preg_match('/^(0|[1-9][0-9]{0,2})\.[0-9]{2}$/', $rate)
                || BigDecimal::of($rate)->isGreaterThan('100')) {
                return $this->stop('RATE_PERCENT_INVALID');
            }
            $rates[$tax] = BigDecimal::of($rate);
        }
        $debt = BigDecimal::of($input['public_offer_debt_return']);
        $transition = BigDecimal::of($input['transition_revenue']);
        if (($input['codBC'] === '1005' && ! $debt->isZero())
            || ($input['transition_present'] === false && ! $transition->isZero())
            || ($input['transition_present'] === true && $transition->isZero())) {
            return $this->stop('DEBT_OR_TRANSITION_COMPOSITION_CONFLICT');
        }
        $general = BigDecimal::of($input['general_revenue'])->plus($debt);
        $revenue = $general->plus($transition);
        $deductions = BigDecimal::of($input['other_deductible_expenses'])->plus($input['allocated_funding_expenses']);
        $reversals = BigDecimal::of($input['export_reversal'])->plus($input['cooperative_reversal']);
        if ($reversals->isGreaterThan($deductions)) {
            return $this->stop('REVERSALS_EXCEED_DECLARED_DEDUCTIONS');
        }
        $total = $revenue->minus($deductions)->plus($reversals);
        // No transition means100% general, including zero revenue with declared deductible costs.
        $generalGross = $input['transition_present']
            ? $total->multipliedBy($general)->dividedBy($revenue, 8, RoundingMode::HalfEven)
            : $total->toScale(8, RoundingMode::HalfEven);
        $transitionGross = $input['transition_present']
            ? $total->multipliedBy($transition)->dividedBy($revenue, 8, RoundingMode::HalfEven)
            : BigDecimal::of('0.00000000');
        $ibsPercent = $rates['IBS_STATE']->plus($rates['IBS_MUNICIPAL']);
        $generalDivisor = $ibsPercent->plus($rates['CBS'])->dividedBy('100', 8, RoundingMode::HalfEven)->plus('1');
        $transitionDivisor = $ibsPercent->dividedBy('100', 8, RoundingMode::HalfEven)->plus('1');
        $generalNet = $generalGross->dividedBy($generalDivisor, 8, RoundingMode::HalfEven);
        $transitionNet = $transitionGross->dividedBy($transitionDivisor, 8, RoundingMode::HalfEven);
        $ibsBase = $generalNet->plus($transitionNet);
        $cbsBase = $generalNet;
        $pools = [];
        foreach (['I' => $ibsBase, 'C' => $cbsBase] as $tax => $signed) {
            $pools[$input['codBC'].$tax] = ['signed_current_base' => (string) $signed,
                'positive_base' => $this->money($signed->isNegative() ? BigDecimal::of('0') : $signed),
                'generated_negative' => $this->money($signed->isNegative() ? $signed->negated() : BigDecimal::of('0'))];
        }
        $embedded = [];
        foreach ($rates as $tax => $rate) {
            $base = $tax === 'CBS' ? $cbsBase : $ibsBase;
            $embedded[$tax] = (string) $base->multipliedBy($rate)->dividedBy('100', 8, RoundingMode::HalfEven);
        }
        $extractedTotal = $ibsBase;
        foreach ($embedded as $amount) {
            $extractedTotal = $extractedTotal->plus($amount);
        }

        return ['status' => 'LOCAL_CURRENT_BASE_ARITHMETIC_CALCULATED', 'contract' => 'DERE-FINANCIAL-CURRENT-BASE-1.3.0-51',
            'input_hash' => CanonicalJson::hash($input), 'codBC' => $input['codBC'], 'synthetic' => true,
            'total_partial' => $this->money($total), 'general_gross' => (string) $generalGross,
            'transition_gross' => (string) $transitionGross, 'general_divisor' => (string) $generalDivisor,
            'transition_divisor' => (string) $transitionDivisor, 'general_net' => (string) $generalNet,
            'transition_net' => (string) $transitionNet, 'pools' => $pools, 'embedded_taxes' => $embedded,
            'allocation_rounding_residual' => (string) $total->minus($generalGross)->minus($transitionGross),
            'gross_down_rounding_residual' => (string) $total->minus($extractedTotal),
            'rounding' => 'DERE_HALF_EVEN_8_2', 'composition_source_qualified' => false,
            'rate_source_qualified' => false, 'negative_base_consumption_applied' => false,
            'eligible_for_posting' => false, 'production_ready' => false];
    }

    private function money(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HalfEven);
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_CURRENT_BASE_'.$reason,
            'eligible_for_posting' => false, 'production_ready' => false];
    }
}
