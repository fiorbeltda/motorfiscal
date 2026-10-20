<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string, mixed> */
function importCreditResult(string $scenarioName): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-15.json');
    $path = $root.'/scenarios/consumption-phase4b-2026.09.28-15/'.$scenarioName.'.json';
    $source = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('qualifies import credit from the linked fiscal document and extinguished debt', function (): void {
    $result = importCreditResult('import-credit-proven-2026');
    $credit = array_values(array_filter($result['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'IMPORT_CREDIT'));

    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN')
        ->and($credit[0]['ibs']['amount'])->toBe('1.00')
        ->and($credit[0]['cbs']['amount'])->toBe('9.00')
        ->and($credit[0]['fiscal_document_id'])->toBe('dfe-import-1')
        ->and($credit[0]['extinction_evidence_id'])->toBe('tax-account-1');
});

it('requires import proof and rejects an unrelated fiscal document', function (): void {
    $missing = importCreditResult('import-credit-extinction-proof-missing-2026');
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['missing_facts'])->toContain('rtc.import_credit_extinction_evidence_id');

    $conflict = importCreditResult('import-credit-document-link-conflict-2026');
    expect($conflict['reason_codes'])->toContain('RTC_IMPORT_CREDIT_DOCUMENT_LINK_CONFLICT');
});

it('keeps reversal, disallowance and nonregular claimant amounts separate', function (): void {
    $reversal = importCreditResult('import-credit-reversal-2026');
    $credit = array_values(array_filter($reversal['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'IMPORT_CREDIT'));
    expect($credit[0]['ibs']['amount'])->toBe('0.75')
        ->and($credit[0]['cbs']['amount'])->toBe('8.00');

    $simples = importCreditResult('import-credit-simples-claimant-2026');
    $denied = array_values(array_filter($simples['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'IMPORT_CREDIT'));
    expect($denied[0]['ibs']['status'])->toBe('DENIED')
        ->and($denied[0]['cbs']['status'])->toBe('DENIED');

    $foreignAcquirer = importCreditResult('import-credit-foreign-acquirer-2026');
    $foreignDenied = array_values(array_filter($foreignAcquirer['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'IMPORT_CREDIT'));
    expect($foreignDenied[0]['ibs']['reason'])->toBe('CLAIMANT_NOT_DOMESTIC_ACQUIRER');
});
