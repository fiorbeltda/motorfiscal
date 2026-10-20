<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\InputCreditTreatmentQualifier;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array{0: array<string, mixed>, 1: mixed}
 */
function inputCreditCase(string $name, array $overrides = []): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-1.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.10.02-1/input-credit-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($input['facts'][$key]);
        } else {
            $input['facts'][$key] = $value;
        }
    }

    return [$knowledge, $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input)];
}

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function inputCreditResult(string $name, array $overrides = []): array
{
    [$knowledge, $scenario] = inputCreditCase($name, $overrides);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('separates ordinary acquisition credits from the reduced output debit', function (string $name): void {
    $result = inputCreditResult($name);
    expect($result['status'])->toBe('VALID')->and($result['decision']['ibs']['nominal_amount'])->toBe('0.40')
        ->and($result['decision']['ibs']['credit']['amount'])->toBe('2.00')
        ->and($result['decision']['cbs']['credit']['amount'])->toBe('18.00');
})->with(['standard', 'reduced-30', 'reduced-60']);

it('denies acquisition credit under art49 even without a prior taxable debit', function (string $name): void {
    $result = inputCreditResult($name);
    expect($result['status'])->toBe('VALID')->and($result['decision']['ibs']['credit']['status'])->toBe('DENIED')
        ->and($result['decision']['cbs']['credit']['amount'])->toBe('0.00')
        ->and($result['decision']['cbs']['credit']['treatment_qualification']['source_article'])->toBe('LC214 art. 49');
})->with(['immunity', 'exempt', 'zero-rate', 'deferral', 'suspension']);

it('keeps separate IBS and CBS treatments and does not infer treatment from the output', function (): void {
    $result = inputCreditResult('asymmetric');
    expect($result['decision']['ibs']['credit']['status'])->toBe('ALLOWED')
        ->and($result['decision']['cbs']['credit']['status'])->toBe('DENIED');
    $pending = inputCreditResult('asymmetric', ['rtc.prior_tax_extinguished' => false]);
    expect($pending['decision']['ibs']['credit']['status'])->toBe('CONDITIONAL')
        ->and($pending['decision']['cbs']['credit']['status'])->toBe('DENIED');
});

it('credits only the extinguished principal of subsequently demanded suspended tax', function (): void {
    $result = inputCreditResult('suspension-principal-only');
    $credit = $result['decision']['cbs']['credit'];
    expect($result['status'])->toBe('VALID')->and($credit['amount'])->toBe('18.00')
        ->and($credit['treatment_qualification']['demanded_principal'])->toBe('27.00')
        ->and($credit['treatment_qualification']['maximum_principal_credit'])->toBe('18.00')
        ->and($credit['treatment_qualification']['legal_additions_excluded'])->toBe('2.25');
    $pending = inputCreditResult('suspension-pending');
    expect($pending['decision']['ibs']['credit']['status'])->toBe('CONDITIONAL')
        ->and($pending['decision']['cbs']['credit']['amount'])->toBe('0.00');
});

it('requires the acquisition contract and blocks unproved presumed credits', function (): void {
    $missing = inputCreditResult('missing-treatment');
    expect($missing['missing_facts'])->toBe(['rtc.input_tax_treatments']);
    $presumed = inputCreditResult('presumed-rule-missing');
    expect($presumed['status'])->toBe('ACTION_REQUIRED')->and($presumed['reason_codes'])->toBe(['RTC_PRESUMED_CREDIT_RULE_NOT_PROVEN']);
});

it('refuses inconsistent credit evidence and claims', function (string $name, string $reason): void {
    $result = inputCreditResult($name);
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toBe([$reason]);
})->with([
    ['unlinked-document', 'RTC_INPUT_CREDIT_TREATMENT_COMPONENT_INVALID'],
    ['duplicate-tax', 'RTC_INPUT_CREDIT_TREATMENT_COMPONENT_INVALID'],
    ['zero-positive-claim', 'RTC_INPUT_CREDIT_EXCEEDS_TREATMENT_LIMIT:CBS'],
    ['suspension-additions-claim', 'RTC_INPUT_CREDIT_EXCEEDS_TREATMENT_LIMIT:IBS'],
    ['suspension-future-extinction', 'RTC_SUSPENDED_INPUT_EXTINCTION_CONFLICT'],
]);

it('requires suspension demand and extinction evidence and preserves credit adjustments', function (): void {
    [$knowledge, $scenario] = inputCreditCase('suspension-principal-only');
    $rows = $scenario->input->context->facts->value('rtc.input_tax_treatments');
    unset($rows[0]['extinction_evidence_id']);
    $missing = inputCreditResult('suspension-principal-only', ['rtc.input_tax_treatments' => $rows]);
    expect($missing['reason_codes'])->toBe(['RTC_SUSPENDED_INPUT_PRINCIPAL_PROOF_MISSING']);
    $adjusted = inputCreditResult('suspension-principal-only', ['rtc.credit_adjustments' => [
        ['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'FIXTURE.REVERSAL'],
    ]]);
    expect($adjusted['decision']['ibs']['credit']['amount'])->toBe('1.75');
});

it('refuses revoked or unavailable normative versions and leaves historical contracts unchanged', function (): void {
    [$knowledge, $scenario] = inputCreditCase('standard');
    $rule = $knowledge['documents']['rtc_code_catalog']['input_credit_treatment'];
    foreach (['revoked_at', 'superseded_at'] as $key) {
        $changed = [...$rule, $key => $scenario->input->context->knowledgeAt->format(DATE_ATOM)];
        $result = (new InputCreditTreatmentQualifier)->resolve($scenario->input->context->facts,
            $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt, $changed);
        expect($result['reason'])->toBe('RTC_INPUT_CREDIT_TREATMENT_RULE_NOT_AVAILABLE');
    }
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $historic = (new FiscalEngineFactory)->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.01-6.json');
    expect($historic['documents']['rtc_code_catalog'])->not->toHaveKey('input_credit_treatment');
});

it('keeps denied import credits separate from the extinction proof of taxable imports', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-1.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-15/import-credit-proven-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $input['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $input['catalog_versions'] = $knowledge['catalog_versions'];
    $input['knowledge_at'] = $knowledge['knowledge_available_at'];
    $input['facts']['rtc.presumed_credit_requested'] = false;
    $input['facts']['rtc.input_ibs_amount'] = '0.00';
    $input['facts']['rtc.input_cbs_amount'] = '0.00';
    $input['facts']['rtc.prior_tax_extinguished'] = false;
    unset($input['facts']['rtc.import_credit_extinction_evidence_id']);
    $input['facts']['rtc.input_tax_treatments'] = array_map(static fn (string $tax): array => [
        'tax' => $tax, 'treatment' => 'ZERO_RATE', 'document_id' => 'dfe-import-1', 'evidence_id' => 'FIXTURE.ZERO.'.$tax,
    ], ['IBS', 'CBS']);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    $trace = array_values(array_filter($result['trace'], static fn (array $row): bool => $row['stage'] === 'IMPORT_CREDIT'));
    expect($result['reason_codes'])->toBe(['RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'])
        ->and($trace[0]['ibs']['status'])->toBe('DENIED')->and($trace[0]['cbs']['amount'])->toBe('0.00');
    foreach ($input['facts']['rtc.input_tax_treatments'] as &$row) {
        $row['treatment'] = 'STANDARD';
    }
    unset($row);
    $input['facts']['rtc.prior_tax_extinguished'] = true;
    $input['facts']['rtc.input_ibs_amount'] = '1.00';
    $input['facts']['rtc.input_cbs_amount'] = '9.00';
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    expect($result['reason_codes'])->toBe(['RTC_IMPORT_CREDIT_PROOF_MISSING'])
        ->and($result['missing_facts'])->toBe(['rtc.import_credit_extinction_evidence_id']);
});
