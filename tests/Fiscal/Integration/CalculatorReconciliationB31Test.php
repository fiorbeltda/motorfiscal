<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CalculatorReconciliationB31;
use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** SaaS 2026 scenario (bundle 09.28-4) with the B31 gate only in memory. */
function b31Case(array $facts = [], bool $enabled = true, mixed $policy = null): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-4.json');
    if ($enabled) {
        $knowledge['documents']['rtc_code_catalog'][CalculatorReconciliationB31::KEY] = $policy ?? CalculatorReconciliationB31::POLICY;
    }
    $knowledge['documents']['fact_definitions'][] = ['key' => 'rtc.official_calculator_result', 'type' => 'ARRAY', 'material' => true];
    $scenario = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b/regular-saas-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'calculator-b31';
    $scenario['knowledge_at'] = '2026-11-05T12:00:00-03:00';
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['facts'] = array_merge($scenario['facts'], $facts);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

/** @return array<string, mixed> */
function b31Calculator(array $values): array
{
    return ['rtc.official_calculator_result' => [$values + ['source' => 'CALCULADORA_RTC', 'version' => 'calc-declared-1',
        'evidence_id' => 'calc-response-1']]];
}

it('reconciles the engine amounts with the official calculator within R$ 0.01', function (): void {
    $plain = b31Case();
    $ibs = $plain['decision']['ibs'];
    $values = ['vIBSUF' => $ibs['state_amount'], 'vIBSMun' => $ibs['municipal_amount'], 'vIBSTot' => $ibs['nominal_amount'],
        'vCBS' => $plain['decision']['cbs']['nominal_amount']];
    $exact = b31Case(b31Calculator($values));
    expect($exact['status'])->toBe('VALID')->and($exact['decision']['calculator_b31']['calculator_checked'])->toBeTrue()
        ->and($exact['decision']['calculator_b31']['authenticated'])->toBeFalse();
    $cent = b31Case(b31Calculator(['vCBS' => bcadd($values['vCBS'], '0.01', 2)] + $values));
    expect($cent['status'])->toBe('VALID')->and($cent['decision']['calculator_b31']['fields']['vCBS']['difference'])->toBe('-0.01');
});

it('rejects divergences above the tolerance with the official rejection code', function (): void {
    $plain = b31Case();
    $ibs = $plain['decision']['ibs'];
    $values = ['vIBSUF' => bcadd($ibs['state_amount'], '0.02', 2), 'vIBSMun' => $ibs['municipal_amount'],
        'vIBSTot' => $ibs['nominal_amount'], 'vCBS' => bcadd($plain['decision']['cbs']['nominal_amount'], '0.05', 2)];
    $result = b31Case(b31Calculator($values));
    expect($result['status'])->toBe('INVALID')
        ->and($result['reason_codes'])->toBe(['RTC_CALCULATOR_DIVERGENCE_ABOVE_TOLERANCE', 'E1568', 'E1582']);
});

it('records that no calculator was supplied without blocking the decision', function (): void {
    $result = b31Case();
    expect($result['status'])->toBe('VALID')->and($result['decision']['calculator_b31']['calculator_checked'])->toBeFalse();
});

it('refuses a malformed calculator result and a malformed policy', function (): void {
    expect(b31Case(b31Calculator(['vIBSUF' => '1', 'vIBSMun' => '0.00', 'vIBSTot' => '1.00', 'vCBS' => '9.00']))['reason_codes'])
        ->toBe(['RTC_CALCULATOR_RESULT_INVALID'])
        ->and(b31Case([], true, ['version' => 'B31'])['reason_codes'])->toBe(['RTC_CALCULATOR_B31_POLICY_INVALID']);
});

it('follows the official vBC formula: PIS/Cofins deducted only in 2026, ISS until 2032', function (): void {
    expect(CalculatorReconciliationB31::legacyAllowed('2026-12-31'))->toContain('PIS', 'COFINS', 'ISS')
        ->and(CalculatorReconciliationB31::legacyAllowed('2027-01-01'))->toBe(['ISS', 'ICMS'])
        ->and(CalculatorReconciliationB31::legacyAllowed('2032-12-31'))->toBe(['ISS', 'ICMS'])
        ->and(CalculatorReconciliationB31::legacyAllowed('2033-01-01'))->toBe([]);
    $pis = ['rtc.legacy_tax_inclusion' => 'INCLUDED', 'rtc.base_adjustments' => [
        ['kind' => 'LEGACY_TAX_EXCLUSION', 'tax' => 'PIS', 'amount' => '6.50', 'evidence_id' => 'pis-1']]];
    expect(b31Case($pis)['decision']['base']['taxable'])->toBe('993.50');
});
