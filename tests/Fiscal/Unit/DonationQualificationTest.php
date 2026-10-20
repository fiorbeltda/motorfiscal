<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string, mixed> */
function donationResult(string $name): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-16.json');
    $source = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-16/'.$name.'.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('qualifies a gift with no counterbenefit and no eligible input credit', function (): void {
    $result = donationResult('donation-no-credit-2026');
    $qualified = array_values(array_filter($result['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'DONATION_INCIDENCE'));

    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_DONATION_DOCUMENT_OBLIGATION_NOT_MATERIALIZED')
        ->and($qualified[0]['treatment'])->toBe('NON_INCIDENCE')
        ->and($qualified[0]['source_article'])->toBe('LC214 art. 6º, VIII e §2º');
});

it('distinguishes credit annulment and market value taxation elections', function (): void {
    $annulled = donationResult('donation-credit-annulled-2026');
    $annulledTrace = array_values(array_filter($annulled['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'DONATION_INCIDENCE'));
    expect($annulledTrace[0]['treatment'])->toBe('CREDITS_ANNULLED')
        ->and($annulledTrace[0]['credit_annulment_evidence_id'])->toBe('credit-reversal-ledger-1');

    $taxed = donationResult('donation-tax-market-value-2026');
    $taxedTrace = array_values(array_filter($taxed['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'DONATION_INCIDENCE'));
    expect($taxedTrace[0]['treatment'])->toBe('MARKET_VALUE_TAXATION')
        ->and($taxed['reason_codes'])->toContain('RTC_DONATION_MARKET_CALCULATION_AND_DOCUMENT_NOT_MATERIALIZED');
});

it('rejects disguised consideration and identifies the exact missing donation proof', function (): void {
    $conflict = donationResult('donation-counterbenefit-2026');
    expect($conflict['status'])->toBe('INVALID')
        ->and($conflict['reason_codes'])->toContain('RTC_DONATION_COUNTERBENEFIT_CONFLICT');

    $onerous = donationResult('donation-onerous-conflict-2026');
    expect($onerous['reason_codes'])->toContain('RTC_DONATION_CONSIDERATION_CONFLICT');

    $amount = donationResult('donation-amount-conflict-2026');
    expect($amount['reason_codes'])->toContain('RTC_DONATION_CONSIDERATION_AMOUNT_CONFLICT');

    $missing = donationResult('donation-counterbenefit-proof-missing-2026');
    expect($missing['missing_facts'])->toContain('rtc.donation_counterbenefit_evidence_id');

    $election = donationResult('donation-credit-option-missing-2026');
    expect($election['missing_facts'])->toContain('rtc.donation_credit_option');

    $annulment = donationResult('donation-credit-annulment-unproven-2026');
    expect($annulment['missing_facts'])->toContain('rtc.donation_credit_annulment_evidence_id');
});
