<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string, mixed> */
function activatedFinancialCreditResult(string $name): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-13.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.10.02-13/financial-borrower-credit-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('integrates qualified cash credit without the generic input strategy or approving a financial document', function (string $name): void {
    $result = activatedFinancialCreditResult($name);
    $stages = array_column($result['trace'], null, 'stage');
    $credit = $stages['FINANCIAL_BORROWER_CREDIT'];
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['decision'])->toBe([])
        ->and($result['reason_codes'])->toBe(['RTC_FINANCIAL_DOCUMENT_PENDING'])
        ->and(isset($stages['INPUT_CREDIT_TREATMENT']))->toBeFalse()
        ->and($credit['eligibility'])->toBe('ALLOWED')
        ->and($credit['components']['IBS']['requested_credit'])->toBe('0.15')
        ->and($credit['components']['CBS']['requested_credit'])->toBe('1.35')
        ->and($credit['appropriation_performed'])->toBeFalse();
})->with(['loan', 'receivable', 'private-debt', 'fee']);

it('preserves denied conditional and independent component outcomes in the integrated trace', function (string $name, string $eligibility, string $ibs, string $cbs): void {
    $result = activatedFinancialCreditResult($name);
    $credit = array_column($result['trace'], null, 'stage')['FINANCIAL_BORROWER_CREDIT'];
    expect($credit['eligibility'])->toBe($eligibility)
        ->and($credit['components']['IBS']['requested_credit'])->toBe($ibs)
        ->and($credit['components']['CBS']['requested_credit'])->toBe($cbs);
})->with([['public-debt', 'DENIED', '0.00', '0.00'], ['currency-denied', 'DENIED', '0.00', '0.00'],
    ['unpaid', 'CONDITIONAL', '0.00', '0.00'], ['mixed-extinction', 'MIXED', '0.00', '1.35']]);

it('requires borrower proof on input and validates identity scope and benchmark qualification', function (string $name, string $status, string $reason): void {
    $result = activatedFinancialCreditResult($name);
    expect($result['status'])->toBe($status)->and($result['reason_codes'])->toBe([$reason]);
})->with([['missing-proof', 'ACTION_REQUIRED', 'RTC_FINANCIAL_CREDIT_PROOF_MISSING'],
    ['identity-conflict', 'INVALID', 'RTC_FINANCIAL_CREDIT_PARTIES_OR_DOCUMENT_CONFLICT'],
    ['category-conflict', 'INVALID', 'RTC_FINANCIAL_CREDIT_SCOPE_CONFLICT'],
    ['benchmark-unproven', 'ACTION_REQUIRED', 'RTC_FINANCIAL_CREDIT_BENCHMARK_CALCULATION_UNPROVEN']]);

it('does not request borrower evidence on an output without assessing it', function (): void {
    $result = activatedFinancialCreditResult('output-unassessed');
    $credit = array_column($result['trace'], null, 'stage')['FINANCIAL_BORROWER_CREDIT'];
    expect($credit['status'])->toBe('NOT_ASSESSED_ON_OUTPUT')
        ->and($result['reason_codes'])->toBe(['RTC_FINANCIAL_DOCUMENT_PENDING']);
});
