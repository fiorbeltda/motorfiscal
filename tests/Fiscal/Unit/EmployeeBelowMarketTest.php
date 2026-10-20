<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function employeeBelowMarketResult(array $overrides = []): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-8.json');
    $source = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-1/non-onerous-employee-below-market-2026.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'employee-below-market-test';
    $source['knowledge_at'] = '2026-09-28T13:13:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['facts']['rtc.consideration'] = 'ONEROUS';
    $source['facts'] = array_merge($source['facts'], $overrides);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        }
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('values an employee below-market supply at the evidenced market value', function (): void {
    $result = employeeBelowMarketResult();
    $base = array_values(array_filter($result['trace'], static fn (array $row): bool => $row['stage'] === 'NON_ONEROUS_MARKET_BASE'))[0];
    $nominal = array_values(array_filter($result['trace'], static fn (array $row): bool => $row['stage'] === 'NON_ONEROUS_NOMINAL_CALCULATION'))[0];

    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED')
        ->and($base['charged_gross_brl'])->toBe('100.00')
        ->and($base['market_value_brl'])->toBe('1000.00')
        ->and($nominal['ibs_state'])->toBe('1.00')
        ->and($nominal['ibs_municipal'])->toBe('0.00')
        ->and($nominal['cbs'])->toBe('9.00');
});

it('rejects below-market status when the charged price reaches market or consideration contradicts the amount', function (): void {
    $atMarket = employeeBelowMarketResult(['rtc.gross_amount' => '1000.00', 'operation.amount' => '1000.00']);
    expect($atMarket['status'])->toBe('INVALID')
        ->and($atMarket['reason_codes'])->toContain('RTC_EMPLOYEE_PRICE_NOT_BELOW_MARKET');

    $contradiction = employeeBelowMarketResult(['rtc.consideration' => 'NON_ONEROUS']);
    expect($contradiction['status'])->toBe('INVALID')
        ->and($contradiction['reason_codes'])->toContain('RTC_NON_ONEROUS_CONSIDERATION_AMOUNT_CONFLICT');
});

it('continues to require evidence of market value and employee personal use', function (): void {
    $missing = employeeBelowMarketResult(['rtc.market_value_evidence_id' => null]);
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['reason_codes'])->toContain('RTC_NON_ONEROUS_EVIDENCE_MISSING')
        ->and($missing['missing_facts'])->toContain('rtc.market_value_evidence_id');

    $business = employeeBelowMarketResult(['rtc.business_use_predominant' => true]);
    expect($business['status'])->toBe('ACTION_REQUIRED')
        ->and($business['reason_codes'])->toContain('RTC_NON_ONEROUS_BUSINESS_USE_EXCEPTION');
});
