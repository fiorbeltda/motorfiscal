<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string, mixed> */
function integratedFinancialScopeResult(string $name, ?bool $providerIndicator = true): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-11.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-integrated-2026.10.02-11/integrated-financial-scope-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($providerIndicator === null) {
        unset($input['facts']['rtc.financial_provider']);
    } else {
        $input['facts']['rtc.financial_provider'] = $providerIndicator;
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);

    return $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
}

it('integrates a nonfinancial software service of an article183 supplier under general rules', function (string $name): void {
    $result = integratedFinancialScopeResult($name);
    $rtc = $result['decision']['consumption_reform']['decision'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($rtc['financial_operation']['qualification']['regime'])->toBe('GENERAL_ART184')
        ->and($rtc['financial_operation']['base'])->toBeNull()
        ->and($rtc['base']['taxable'])->toBe('1000.00')
        ->and($rtc['ibs']['nominal_amount'])->toBe('1.00')->and($rtc['cbs']['nominal_amount'])->toBe('9.00')
        ->and($result['decision']['iss']['calculation']['tax_amount'])->toBe('20.00')
        ->and($result['decision']['federal_withholdings']['total_withholding'])->toBe('61.50');
})->with(['bank-software', 'savings-bank-software', 'payment-institution-software']);

it('evaluates supplied scope evidence even when the optional provider indicator is absent', function (): void {
    $result = integratedFinancialScopeResult('bank-software', null);
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($result['decision']['consumption_reform']['decision']['financial_operation']['qualification']['regime'])->toBe('GENERAL_ART184');
});

it('rejects an explicit false provider indicator conflicting with supplied financial evidence', function (): void {
    $result = integratedFinancialScopeResult('bank-software', false);
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toBe(['RTC_FINANCIAL_PROVIDER_INDICATOR_CONFLICT']);
});

it('calculates a qualified financial ledger in the RTC trace while withholding an incomplete final decision', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-11.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-integrated-2026.10.02-11/integrated-financial-scope-bank-software-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $input['competence_date'] = '2026-09-30';
    $input['facts']['rtc.operation_date'] = '2026-09-30';
    $input['facts']['rtc.operation_kind'] = 'FINANCIAL';
    $input['facts']['rtc.financial_scope_proof'][0]['operation_category'] = 'CREDIT';
    $input['facts']['rtc.financial_scope_proof'][0]['provider_authorized_for_operation'] = true;
    $input['facts']['rtc.financial_scope_proof'][0]['operation_authorization_evidence_id'] = 'FIXTURE.CREDIT.AUTHORIZATION';
    $supplier = $input['facts']['rtc.supplier_id'];
    $entry = ['supplier_id' => $supplier, 'recognized_on' => '2026-09-20', 'qualification_evidence_id' => 'FIXTURE.QUALIFIED'];
    $input['facts']['rtc.financial_ledger'] = [['family' => 'CREDIT_FX_SECURITIES_ART192', 'supplier_id' => $supplier,
        'scope_document_id' => $input['facts']['rtc.document_id'], 'currency' => 'BRL',
        'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'observed_on' => '2026-10-01',
        'ledger_complete' => true, 'financial_scope_qualified' => true, 'recognition_dates_qualified' => true,
        'net_of_ibs_cbs_iss' => true, 'cooperative_art271_option' => false, 'ledger_evidence_id' => 'FIXTURE.COMPLETE.LEDGER',
        'scope_evidence_id' => 'FIXTURE.SCOPE', 'recognition_evidence_id' => 'FIXTURE.RECOGNITION', 'tax_exclusion_evidence_id' => 'FIXTURE.NET',
        'entries' => [[...$entry, 'id' => 'interest', 'kind' => 'FINANCIAL_REVENUE', 'document_id' => 'interest-1', 'amount' => '1000.00'],
            [...$entry, 'id' => 'principal', 'kind' => 'CREDIT_PRINCIPAL', 'document_id' => 'principal-1', 'amount' => '10000.00'],
            [...$entry, 'id' => 'funding', 'kind' => 'FUNDING_EXPENSE', 'document_id' => 'funding-1', 'amount' => '200.00',
                'operation_authorized' => true, 'within_operational_limits' => true, 'not_administrative_expense' => true,
                'authorization_evidence_id' => 'FIXTURE.FUNDING.AUTHORIZATION']]]];
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $documents = $knowledge['documents'];
    $result = (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    $stages = array_column($result['trace'], null, 'stage');
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['decision'])->toBe([])
        ->and($result['reason_codes'])->toBe(['RTC_FINANCIAL_RATE_CREDIT_AND_DOCUMENT_PENDING'])
        ->and($stages['FINANCIAL_OPERATION_AND_BASE']['base']['net_base'])->toBe('800.00')
        ->and($stages['FINANCIAL_OPERATION_AND_BASE']['base']['excluded'])->toBe('10000.00');
});

it('blocks absent or conflicting financial qualification before final calculation', function (string $name, string $status, string $reason): void {
    $result = integratedFinancialScopeResult($name);
    expect($result['status'])->toBe($status)->and($result['reason_codes'])->toBe([$reason]);
})->with([
    ['missing-proof', 'ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_PROOF_MISSING'],
    ['wrong-supplier', 'INVALID', 'RTC_FINANCIAL_SCOPE_PARTIES_OR_DOCUMENT_CONFLICT'],
    ['wrong-document', 'INVALID', 'RTC_FINANCIAL_SCOPE_PARTIES_OR_DOCUMENT_CONFLICT'],
    ['unknown-registry', 'ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_SUPERVISION_UNPROVEN'],
    ['future-observation', 'INVALID', 'RTC_FINANCIAL_SCOPE_PROOF_PERIOD_CONFLICT'],
    ['expired-proof', 'ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_PROOF_OUTSIDE_PERIOD'],
    ['unknown-nonfinancial-scope', 'ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_NON_FINANCIAL_SCOPE_UNPROVEN'],
    ['unknown-role', 'ACTION_REQUIRED', 'RTC_FINANCIAL_SCOPE_SUPERVISION_UNPROVEN'],
    ['wrong-general-kind', 'INVALID', 'RTC_FINANCIAL_GENERAL_OPERATION_KIND_CONFLICT'],
    ['specific-ledger-missing', 'ACTION_REQUIRED', 'RTC_FINANCIAL_QUALIFIED_LEDGER_MISSING'],
]);
