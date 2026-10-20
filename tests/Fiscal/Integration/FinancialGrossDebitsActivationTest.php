<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string, mixed> */
function activatedFinancialTaxResult(string $name): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-12.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.10.02-12/financial-gross-debits-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('activates published test rates on the qualified specific base while blocking credits and documents', function (string $name, string $ibs, string $cbs): void {
    $result = activatedFinancialTaxResult($name);
    $stages = array_column($result['trace'], null, 'stage');
    $tax = $stages['FINANCIAL_GROSS_DEBITS'];
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['decision'])->toBe([])
        ->and($result['reason_codes'])->toBe(['RTC_FINANCIAL_CREDIT_AND_DOCUMENT_PENDING'])
        ->and($tax['status'])->toBe('RESOLVED')->and($tax['base'])->toBe('800.00')
        ->and($tax['ibs_nominal'])->toBe('0.80')->and($tax['cbs_nominal'])->toBe('7.20')
        ->and($tax['collection_before_period_credit_offset'])->toBe(['IBS' => $ibs, 'CBS' => $cbs]);
})->with([['dispensed', '0.00', '0.00'], ['not-dispensed', '0.80', '7.20'], ['factoring', '0.00', '0.00']]);

it('requires the missing collection condition rather than treating it as fulfilled', function (): void {
    $result = activatedFinancialTaxResult('unknown-condition');
    expect($result['reason_codes'])->toBe(['RTC_FINANCIAL_TAX_2026_COLLECTION_CONDITION_UNKNOWN'])
        ->and($result['missing_facts'])->toBe(['rtc.accessory_obligations_fulfilled']);
});

it('blocks negative or mixed zero financial bases until their specific allocation is materialized', function (string $name, string $reason): void {
    $result = activatedFinancialTaxResult($name);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toBe(['RTC_FINANCIAL_TAX_'.$reason]);
})->with([['negative-base', 'NEGATIVE_PERIOD_BASE_RULE_REQUIRED'], ['mixed-cooperative', 'COOPERATIVE_MIXED_RATE_ALLOCATION_REQUIRED']]);
