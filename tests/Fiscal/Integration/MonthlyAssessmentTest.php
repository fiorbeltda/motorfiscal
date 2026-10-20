<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed>|null $ledger
 * @return array<string, mixed>
 */
function monthlyAssessmentResult(string $name = 'mixed', ?array $ledger = null): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-7.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-integrated-2026.10.02-7/integrated-monthly-assessment-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($ledger !== null) {
        $input['facts']['rtc.monthly_assessment'] = [$ledger];
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);

    return $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
}

it('consolidates branches without cross compensating IBS and CBS or creating a fiscal filing', function (): void {
    $result = monthlyAssessmentResult();
    $decision = $result['decision'];
    $period = $decision['consumption_reform']['decision']['monthly_assessment'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($period['taxes']['IBS']['debits'])->toBe('120.00')
        ->and($period['taxes']['IBS']['carryforward'])->toBe('20.00')
        ->and($period['taxes']['IBS']['credit_allocated_to_prior_debts'])->toBe('10.00')
        ->and($period['taxes']['IBS']['payable'])->toBe('65.00')
        ->and($period['taxes']['CBS']['recoverable'])->toBe('90.00')
        ->and($period['taxes']['CBS']['payable'])->toBe('0.00')
        ->and($period['payment_due_date'])->toBeNull()
        ->and($period['filing_performed'])->toBeFalse()
        ->and($period['debt_confession'])->toBeFalse()
        ->and($decision['iss']['calculation']['tax_amount'])->toBe('50.00');
});

it('calculates balanced adjusted and prequalified presumed components', function (string $name, string $ibs, string $cbs): void {
    $period = monthlyAssessmentResult($name)['decision']['consumption_reform']['decision']['monthly_assessment'];
    expect($period['taxes']['IBS']['final_balance'])->toBe($ibs)
        ->and($period['taxes']['CBS']['final_balance'])->toBe($cbs);
})->with([['balanced', '0.00', '0.00'], ['adjusted', '70.00', '-100.00'], ['presumed-qualified', '65.00', '-90.00'], ['after-close-extinction', '65.00', '-90.00']]);

it('rejects incomplete or conflicting monthly evidence', function (string $name, string $status, string $reason): void {
    $result = monthlyAssessmentResult($name);
    expect($result['status'])->toBe($status)->and($result['reason_codes'])->toBe(['RTC_MONTHLY_ASSESSMENT_'.$reason]);
})->with([
    ['incomplete', 'ACTION_REQUIRED', 'LEDGER_INCOMPLETE:all_establishments_included'],
    ['future-period', 'ACTION_REQUIRED', 'PERIOD_NOT_CLOSED'],
    ['wrong-owner', 'INVALID', 'OWNER_OR_CURRENCY_CONFLICT'],
    ['duplicate', 'INVALID', 'ENTRY_INVALID:credits'],
    ['excess-extinction', 'INVALID', 'EXTINCTION_EXCEEDS_DEBIT'],
    ['wrong-extinction-tax', 'INVALID', 'EXTINCTION_LINK_CONFLICT'],
    ['compensation-double-count', 'INVALID', 'EXTINCTION_NOT_ART27_III_V'],
    ['unqualified-credit', 'ACTION_REQUIRED', 'ENTRY_NOT_QUALIFIED:credits'],
    ['carryforward-overdrawn', 'INVALID', 'CARRYFORWARD_DISPOSITIONS_EXCEED_BALANCE'],
    ['nominal-not-due', 'INVALID', 'DEBIT_DUE_STATUS_CONFLICT'],
    ['future-evidence', 'INVALID', 'ENTRY_DATE_CONFLICT:credits'],
    ['missing-prior-debts', 'ACTION_REQUIRED', 'LEDGER_INCOMPLETE:prior_debts_complete'],
    ['unqualified-adjustment', 'ACTION_REQUIRED', 'ADJUSTMENT_RULE_NOT_QUALIFIED'],
    ['unqualified-presumed', 'ACTION_REQUIRED', 'PRESUMED_CREDIT_RULE_NOT_QUALIFIED'],
]);

it('does not reapply an extinction proof through another entry identifier', function (): void {
    $ledger = monthlyAssessmentResult()['decision']['consumption_reform']['decision']['monthly_assessment']['ledger'];
    $duplicate = $ledger['extinctions'][0];
    $duplicate['id'] = 'second-use-of-the-same-payment';
    $ledger['extinctions'][] = $duplicate;
    expect(monthlyAssessmentResult(ledger: $ledger)['reason_codes'])->toBe(['RTC_MONTHLY_ASSESSMENT_DUPLICATE_EXTINCTION_PROOF']);
});

it('keeps an unpaid prior debt when credits cannot extinguish it completely', function (): void {
    $ledger = monthlyAssessmentResult()['decision']['consumption_reform']['decision']['monthly_assessment']['ledger'];
    $ledger['prior_debts'][0]['amount'] = '70.00';
    $period = monthlyAssessmentResult(ledger: $ledger)['decision']['consumption_reform']['decision']['monthly_assessment'];
    expect($period['taxes']['IBS']['prior_debt_remaining'])->toBe('20.00')
        ->and($period['taxes']['IBS']['credit_available_for_current_period'])->toBe('0.00')
        ->and($period['taxes']['IBS']['payable'])->toBe('105.00');
});

it('integrates the monthly ledger with immune ISS RTC and an actual 4A evaluation', function (): void {
    $result = monthlyAssessmentResult('family-4a');
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($result['decision']['iss']['incidence']['value'])->toBe('IMMUNE')
        ->and($result['decision']['federal_withholdings']['status'])->toBe('RESOLVED')
        ->and($result['decision']['consumption_reform']['decision']['monthly_assessment']['taxes']['IBS']['payable'])->toBe('65.00');
});
