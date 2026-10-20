<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereFinancialCurrentBaseCalculator;

/** Declared synthetic composition; source qualification is not supplied by this fixture. @return array<string, mixed> */
function currentBase51(): array
{
    return ['codBC' => '1010', 'iss_incident' => false, 'synthetic' => true, 'transition_present' => false,
        'selective_tax_incident' => false, 'general_revenue' => '1200.00', 'transition_revenue' => '0.00',
        'other_deductible_expenses' => '100.00', 'allocated_funding_expenses' => '200.00',
        'export_reversal' => '0.00', 'cooperative_reversal' => '0.00', 'public_offer_debt_return' => '0.00',
        'rates' => ['IBS_STATE' => '0.10', 'IBS_MUNICIPAL' => '0.00', 'CBS' => '0.90']];
}

it('extracts embedded taxes before supplying the positive base to negative-base consumption', function (): void {
    $result = (new DereFinancialCurrentBaseCalculator)->calculate(currentBase51());
    expect($result['status'])->toBe('LOCAL_CURRENT_BASE_ARITHMETIC_CALCULATED')
        ->and($result['total_partial'])->toBe('900.00')->and($result['general_net'])->toBe('891.08910891')
        ->and($result['pools']['1010I']['positive_base'])->toBe('891.09')
        ->and($result['pools']['1010C']['positive_base'])->toBe('891.09')
        ->and($result['composition_source_qualified'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
});

it('segregates transition income by excluding CBS only from that fraction', function (): void {
    $input = currentBase51();
    $input['general_revenue'] = '1100.00';
    $input['transition_revenue'] = '500.00';
    $input['transition_present'] = true;
    $input['other_deductible_expenses'] = $input['allocated_funding_expenses'] = '0.00';
    $input['rates'] = ['IBS_STATE' => '8.00', 'IBS_MUNICIPAL' => '0.00', 'CBS' => '2.00'];
    $result = (new DereFinancialCurrentBaseCalculator)->calculate($input);
    expect($result['general_net'])->toBe('1000.00000000')->and($result['transition_net'])->toBe('462.96296296')
        ->and($result['pools']['1010I']['positive_base'])->toBe('1462.96')
        ->and($result['pools']['1010C']['positive_base'])->toBe('1000.00');
});

it('generates negative stock separately instead of a positive taxable base', function (): void {
    $input = currentBase51();
    $input['general_revenue'] = '0.00';
    $result = (new DereFinancialCurrentBaseCalculator)->calculate($input);
    expect($result['pools']['1010I']['positive_base'])->toBe('0.00')
        ->and($result['pools']['1010I']['generated_negative'])->toBe('297.03')
        ->and($result['pools']['1010C']['generated_negative'])->toBe('297.03');
});

it('retains zero bases and prevents any declared current generation', function (): void {
    $input = currentBase51();
    $input['general_revenue'] = '300.00';
    $result = (new DereFinancialCurrentBaseCalculator)->calculate($input);
    expect($result['pools']['1010I']['positive_base'])->toBe('0.00')
        ->and($result['pools']['1010C']['generated_negative'])->toBe('0.00');
});

it('adds public-offer debt return to the forming revenue once in the declared composition', function (): void {
    $input = currentBase51();
    $input['general_revenue'] = '1000.00';
    $input['public_offer_debt_return'] = '200.00';
    $result = (new DereFinancialCurrentBaseCalculator)->calculate($input);
    expect($result['total_partial'])->toBe('900.00')->and($result['composition_source_qualified'])->toBeFalse();
});

it('preserves half-even final ties and does not round through floating point', function (): void {
    $input = currentBase51();
    $input['general_revenue'] = '0.01';
    $input['other_deductible_expenses'] = $input['allocated_funding_expenses'] = '0.00';
    $input['rates'] = ['IBS_STATE' => '100.00', 'IBS_MUNICIPAL' => '0.00', 'CBS' => '0.00'];
    $result = (new DereFinancialCurrentBaseCalculator)->calculate($input);
    expect($result['general_net'])->toBe('0.00500000')->and($result['pools']['1010I']['positive_base'])->toBe('0.00');
});

it('rejects compositions that cannot be admitted to financial current-base arithmetic', function (string $field, mixed $value, string $reason): void {
    $input = currentBase51();
    $input[$field] = $value;
    $result = (new DereFinancialCurrentBaseCalculator)->calculate($input);
    expect($result['reason'])->toBe('RTC_DERE_CURRENT_BASE_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['synthetic', false, 'DECLARED_COMPOSITION_SCOPE_REQUIRED'],
    ['iss_incident', true, 'DECLARED_COMPOSITION_SCOPE_REQUIRED'],
    ['selective_tax_incident', true, 'DECLARED_COMPOSITION_SCOPE_REQUIRED'],
    ['general_revenue', 1200, 'CANONICAL_COMPOSITION_AMOUNT_REQUIRED'],
    ['general_revenue', '1200.001', 'CANONICAL_COMPOSITION_AMOUNT_REQUIRED'],
    ['general_revenue', '-1.00', 'CANONICAL_COMPOSITION_AMOUNT_REQUIRED'],
    ['transition_revenue', '1.00', 'DEBT_OR_TRANSITION_COMPOSITION_CONFLICT'],
    ['transition_present', true, 'DEBT_OR_TRANSITION_COMPOSITION_CONFLICT'],
    ['export_reversal', '301.00', 'REVERSALS_EXCEED_DECLARED_DEDUCTIONS'],
    ['rates', ['CBS' => '0.90'], 'SEPARATE_RATE_COMPONENTS_REQUIRED'],
    ['rates', ['IBS_STATE' => '101.00', 'IBS_MUNICIPAL' => '0.00', 'CBS' => '0.90'], 'RATE_PERCENT_INVALID'],
    ['unexpected', '0.00', 'DECLARED_COMPOSITION_SCOPE_REQUIRED'],
]);
